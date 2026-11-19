<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use App\Support\SellerCart;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::with(['client', 'status'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($q) => $q->where('code', 'like', $term)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('order_status_id', $request->input('status')))
            ->latest();

        return view('admin.orders.index', [
            'orders' => $orders->paginate(10)->withQueryString(),
            'statuses' => OrderStatus::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $order = new Order;
        $items = collect();

        if ($request->filled('repeat')) {
            $sourceOrder = Order::with('items.product')->find($request->input('repeat'));
            if ($sourceOrder) {
                $order->client_id = $sourceOrder->client_id;
                $order->billing_type = $sourceOrder->billing_type;
                $order->notes = $sourceOrder->notes ? 'Copia de '.$sourceOrder->code.': '.$sourceOrder->notes : 'Copia de '.$sourceOrder->code;
                $items = $sourceOrder->items->filter(fn ($it) => $it->product && $it->product->is_active);
            }
        }

        $formData = $this->formData($order);
        if ($items->isNotEmpty()) {
            $formData['items'] = $items;
        }

        return view('admin.orders.form', $formData);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $user = Auth::user();
        $fromSellerSite = $request->boolean('from_seller_site') || ($user && $user->isSeller());

        $order = DB::transaction(function () use ($data, $request) {
            $status = OrderStatus::where('slug', OrderStatus::SLUG_CREADO)->first() ?? OrderStatus::orderBy('sort_order')->firstOrFail();
            $order = Order::create([
                'code' => ! empty($data['code']) ? $data['code'] : $this->generateCode(),
                'client_id' => $data['client_id'],
                'billing_type' => $data['billing_type'],
                'created_by' => Auth::id(),
                'order_status_id' => $status->id,
                'ordered_at' => ! empty($data['ordered_at']) ? $data['ordered_at'] : now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($order, $request->input('items', []));
            $this->recordHistory($order, null, $status->id, 'Pedido creado.');

            return $order;
        });

        if ($fromSellerSite) {
            app(SellerCart::class)->clear();

            return redirect()->route('site.catalog')->with('success', 'Pedido '.$order->code.' confirmado correctamente.');
        }

        return redirect()->route('pedidos.index')->with('success', 'Pedido creado correctamente.');
    }

    public function show(Order $pedido): View
    {
        return view('admin.orders.show', [
            'order' => $pedido->load([
                'client',
                'status',
                'items.product',
                'histories.previousStatus',
                'histories.newStatus',
                'histories.user',
                'paymentResponsible',
            ]),
            'statuses' => OrderStatus::where('is_active', true)->orderBy('sort_order')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function edit(Order $pedido): View
    {
        return view('admin.orders.form', $this->formData($pedido->load('items')));
    }

    public function update(Request $request, Order $pedido): RedirectResponse
    {
        $data = $this->validatedData($request, $pedido);

        DB::transaction(function () use ($pedido, $data, $request) {
            $pedido->update([
                'code' => $data['code'],
                'client_id' => $data['client_id'],
                'billing_type' => $data['billing_type'],
                'ordered_at' => $data['ordered_at'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($pedido, $request->input('items', []));
        });

        return redirect()->route('pedidos.index')->with('success', 'Pedido actualizado correctamente.');
    }

    public function destroy(Order $pedido): RedirectResponse
    {
        $pedido->loadMissing('status');

        $rejected = OrderStatus::where('slug', OrderStatus::SLUG_RECHAZADO)->first();

        if ($pedido->status && (! $rejected || ! $pedido->status->canTransitionTo($rejected))) {
            return back()->with('error', 'No se puede rechazar un pedido en su estado actual.');
        }

        if (! $rejected) {
            return back()->with('error', 'No se encontró el estado oficial Pedido rechazado.');
        }

        DB::transaction(function () use ($pedido, $rejected): void {
            $previous = $pedido->order_status_id;
            $reason = trim((string) ($pedido->rejection_reason ?? ''));

            $pedido->update([
                'order_status_id' => $rejected->id,
                'rejection_reason' => $reason !== '' ? $reason : 'Pedido rechazado desde el listado.',
            ]);

            $this->recordHistory($pedido, $previous, $rejected->id, 'Pedido rechazado desde el listado.');
        });

        return back()->with('success', 'Pedido rechazado correctamente.');
    }

    public function updateStatus(Request $request, Order $pedido): RedirectResponse
    {
        $pedido->loadMissing('status');

        // Enforce authorization for sellers
        if ($request->user() && $request->user()->isSeller()) {
            if ((int) $pedido->created_by !== (int) $request->user()->id) {
                abort(403, 'No tienes autorización para gestionar este pedido.');
            }

            $currentSlug = $pedido->status?->slug;
            if ($currentSlug !== OrderStatus::SLUG_DESPACHADO) {
                $validator = Validator::make([], []);
                $validator->errors()->add('order', 'El pedido debe estar en estado despachado para registrar la entrega.');
                throw new ValidationException($validator);
            }
        }

        // 1. Determine target status
        $targetStatusId = $request->input('order_status_id');
        if (empty($targetStatusId)) {
            if ($request->hasFile('delivery_photo') || $request->hasFile('photo') || $request->filled('delivery_latitude')) {
                $entregadoStatus = OrderStatus::where('slug', OrderStatus::SLUG_ENTREGADO)->first();
                $targetStatusId = $entregadoStatus?->id ?? $pedido->order_status_id;
            } elseif ($request->hasFile('payment_support') || $request->hasFile('collection_support_file') || $request->filled('payment_responsible_id') || $request->filled('collected_by') || $request->filled('collected_by_id')) {
                $cobradoStatus = OrderStatus::where('slug', OrderStatus::SLUG_COBRADO)->first();
                $targetStatusId = $cobradoStatus?->id ?? $pedido->order_status_id;
            } else {
                $targetStatusId = $pedido->order_status_id;
            }
        }

        $newStatus = is_numeric($targetStatusId)
            ? OrderStatus::find($targetStatusId)
            : OrderStatus::where('slug', $targetStatusId)->first();

        if (! $newStatus || ! $newStatus->is_active || ! $newStatus->isCritical()) {
            $validator = Validator::make(['order_status_id' => $targetStatusId], [
                'order_status_id' => ['required', 'exists:order_statuses,id'],
            ]);
            if ($newStatus && (! $newStatus->is_active || ! $newStatus->isCritical())) {
                $validator->after(fn ($v) => $v->errors()->add('order_status_id', 'El estado seleccionado no está permitido o no está activo.'));
            }
            throw new ValidationException($validator);
        }

        $slug = $newStatus->slug;

        // Sellers are restricted to transition to delivered
        if ($request->user() && $request->user()->isSeller()) {
            if ($slug !== OrderStatus::SLUG_ENTREGADO) {
                abort(403, 'Los vendedores únicamente pueden registrar la entrega de pedidos despachados.');
            }
        }

        // Enforce transition matrix
        if ($pedido->status && ! $pedido->status->canTransitionTo($newStatus, allowSameStatus: ! $pedido->status->isTerminal())) {
            throw ValidationException::withMessages([
                'order_status_id' => "No se permite cambiar el pedido desde '{$pedido->status->name}' a '{$newStatus->name}'.",
            ]);
        }

        $previous = $pedido->order_status_id;
        $dataToUpdate = ['order_status_id' => $newStatus->id];
        $validator = Validator::make([], []);

        // 2. Conditional validations based on target status

        // A. Despachado
        if ($slug === OrderStatus::SLUG_DESPACHADO) {
            // Signature check
            $sigFile = $request->file('signature') ?? $request->file('signature_file') ?? $request->file('dispatch_signature_file');
            $sigString = $request->input('signature') ?? $request->input('dispatch_signature');

            if (! $sigFile && (empty($sigString) || ! is_string($sigString) || trim($sigString) === '')) {
                $validator->errors()->add('signature', 'La firma de autorización es obligatoria.');
                $validator->errors()->add('dispatch_signature', 'La firma de autorización es obligatoria.');
            } elseif ($sigFile) {
                $dataToUpdate['dispatch_signature_path'] = $sigFile->store('orders/signatures', 'public');
            } elseif ($sigString) {
                $dataToUpdate['dispatch_signature_path'] = $this->storeBase64Image($sigString, 'orders/signatures');
            }

            // Photo check
            $dispatchPhoto = $request->file('dispatch_photo') ?? $request->file('photo') ?? $request->file('dispatch_photo_file');
            if (! $dispatchPhoto) {
                $validator->errors()->add('dispatch_photo', 'La fotografía del despacho es obligatoria.');
                $validator->errors()->add('photo', 'La fotografía del despacho es obligatoria.');
            } else {
                $dataToUpdate['dispatch_photo_path'] = $dispatchPhoto->store('orders/dispatches', 'public');
            }

            // Payment modality check
            $term = $request->input('payment_term') ?? $request->input('payment_method') ?? $request->input('payment_modality');
            if (empty($term)) {
                $validator->errors()->add('payment_term', 'La modalidad de pago es obligatoria.');
                $validator->errors()->add('payment_method', 'La modalidad de pago es obligatoria.');
                $validator->errors()->add('payment_modality', 'La modalidad de pago es obligatoria.');
            } elseif (! in_array($term, ['contado', 'credito'])) {
                $validator->errors()->add('payment_term', 'La modalidad de pago debe ser contado o crédito.');
                $validator->errors()->add('payment_method', 'La modalidad de pago debe ser contado o crédito.');
                $validator->errors()->add('payment_modality', 'La modalidad de pago debe ser contado o crédito.');
            } else {
                $dataToUpdate['payment_term'] = $term;
                if ($term === 'credito') {
                    $dueDate = $request->input('payment_due_date') ?? $request->input('due_date');
                    if (empty($dueDate)) {
                        $validator->errors()->add('payment_due_date', 'La fecha de vencimiento es obligatoria para crédito.');
                        $validator->errors()->add('due_date', 'La fecha de vencimiento es obligatoria para crédito.');
                    } else {
                        try {
                            $dataToUpdate['payment_due_date'] = Carbon::parse($dueDate)->toDateString();
                        } catch (\Exception $e) {
                            $validator->errors()->add('payment_due_date', 'La fecha de vencimiento no es válida.');
                            $validator->errors()->add('due_date', 'La fecha de vencimiento no es válida.');
                        }
                    }
                } else {
                    $dataToUpdate['payment_due_date'] = null;
                }
            }
        }

        // B. Entregado
        if ($slug === OrderStatus::SLUG_ENTREGADO) {
            // Delivery photo check
            $deliveryPhoto = $request->file('delivery_photo') ?? $request->file('photo') ?? $request->file('delivery_photo_file');
            if (! $deliveryPhoto) {
                $validator->errors()->add('delivery_photo', 'La fotografía de entrega es obligatoria.');
                $validator->errors()->add('photo', 'La fotografía de entrega es obligatoria.');
            } else {
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower($deliveryPhoto->getClientOriginalExtension() ?: '');
                $mime = strtolower($deliveryPhoto->getMimeType() ?: $deliveryPhoto->getClientMimeType() ?: '');
                $size = $deliveryPhoto->getSize();

                $isImageMime = str_starts_with($mime, 'image/') || in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);
                $isAllowedExt = in_array($ext, $allowedExtensions, true);
                $isUnderSize = $size !== false && $size <= (10 * 1024 * 1024);

                if (! $deliveryPhoto->isValid() || ! $isImageMime || ! $isAllowedExt || ! $isUnderSize) {
                    $validator->errors()->add('delivery_photo', 'El archivo adjunto debe ser una imagen válida (JPG, JPEG, PNG, WEBP).');
                    $validator->errors()->add('photo', 'El archivo adjunto debe ser una imagen válida (JPG, JPEG, PNG, WEBP).');
                } else {
                    $dataToUpdate['delivery_photo_path'] = $deliveryPhoto->store('orders/deliveries', 'public');
                }
            }

            // Client location & coordinates check
            $location = $request->input('client_location') ?? $request->input('delivery_location');
            $lat = $request->input('delivery_latitude') ?? $request->input('latitude');
            $lng = $request->input('delivery_longitude') ?? $request->input('longitude');

            $latitude = null;
            $longitude = null;
            $latNumeric = ($lat !== null && is_numeric($lat));
            $lngNumeric = ($lng !== null && is_numeric($lng));

            if ($latNumeric && $lngNumeric) {
                $latitude = (float) $lat;
                $longitude = (float) $lng;
                if (empty($location)) {
                    $location = "{$lat}, {$lng}";
                }
            } elseif (! empty($location) && is_string($location)) {
                if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/', $location, $matches)) {
                    $latitude = (float) $matches[1];
                    $longitude = (float) $matches[2];
                }
            }

            if ((empty($location) || ! is_string($location) || trim($location) === '') && ($lat === null || $lng === null)) {
                $validator->errors()->add('delivery_location', 'La ubicación del cliente es obligatoria.');
                $validator->errors()->add('client_location', 'La ubicación del cliente es obligatoria.');
                $validator->errors()->add('delivery_latitude', 'Las coordenadas de entrega son obligatorias.');
                $validator->errors()->add('delivery_longitude', 'Las coordenadas de entrega son obligatorias.');
            }

            $latToCheck = $latitude ?? ($latNumeric ? (float) $lat : null);
            $lngToCheck = $longitude ?? ($lngNumeric ? (float) $lng : null);

            $hasRangeError = false;
            if (($latToCheck !== null && ($latToCheck < -90.0 || $latToCheck > 90.0)) ||
                ($lngToCheck !== null && ($lngToCheck < -180.0 || $lngToCheck > 180.0))) {
                $hasRangeError = true;
                $rangeMsg = 'Las coordenadas geográficas están fuera del rango válido (-90 a 90 para latitud, -180 a 180 para longitud).';
                if ($latToCheck !== null && ($latToCheck < -90.0 || $latToCheck > 90.0)) {
                    $validator->errors()->add('delivery_latitude', $rangeMsg);
                    $validator->errors()->add('latitude', $rangeMsg);
                }
                if ($lngToCheck !== null && ($lngToCheck < -180.0 || $lngToCheck > 180.0)) {
                    $validator->errors()->add('delivery_longitude', $rangeMsg);
                    $validator->errors()->add('longitude', $rangeMsg);
                }
                $validator->errors()->add('delivery_location', $rangeMsg);
                $validator->errors()->add('client_location', $rangeMsg);
            }

            if (! $hasRangeError) {
                if ($latitude !== null && $longitude !== null) {
                    $dataToUpdate['delivery_latitude'] = $latitude;
                    $dataToUpdate['delivery_longitude'] = $longitude;
                    $dataToUpdate['client_location'] = ! empty($location) ? trim($location) : "{$latitude}, {$longitude}";
                } elseif (! empty($location) && is_string($location) && trim($location) !== '') {
                    $dataToUpdate['client_location'] = trim($location);
                }
            }

            // Payment modality if not yet set or supplied in request
            $term = $request->input('payment_term') ?? $request->input('payment_method') ?? $request->input('payment_modality');
            if (empty($pedido->payment_term) || ! empty($term)) {
                if (empty($term)) {
                    $validator->errors()->add('payment_term', 'La modalidad de pago es obligatoria.');
                    $validator->errors()->add('payment_method', 'La modalidad de pago es obligatoria.');
                    $validator->errors()->add('payment_modality', 'La modalidad de pago es obligatoria.');
                } elseif (! in_array($term, ['contado', 'credito'])) {
                    $validator->errors()->add('payment_term', 'La modalidad de pago debe ser contado o crédito.');
                    $validator->errors()->add('payment_method', 'La modalidad de pago debe ser contado o crédito.');
                    $validator->errors()->add('payment_modality', 'La modalidad de pago debe ser contado o crédito.');
                } else {
                    $dataToUpdate['payment_term'] = $term;
                    if ($term === 'credito') {
                        $dueDate = $request->input('payment_due_date') ?? $request->input('due_date');
                        if (empty($dueDate)) {
                            $validator->errors()->add('payment_due_date', 'La fecha de vencimiento es obligatoria para crédito.');
                            $validator->errors()->add('due_date', 'La fecha de vencimiento es obligatoria para crédito.');
                        } else {
                            try {
                                $dataToUpdate['payment_due_date'] = Carbon::parse($dueDate)->toDateString();
                            } catch (\Exception $e) {
                                $validator->errors()->add('payment_due_date', 'La fecha de vencimiento no es válida.');
                                $validator->errors()->add('due_date', 'La fecha de vencimiento no es válida.');
                            }
                        }
                    } else {
                        $dataToUpdate['payment_due_date'] = null;
                    }
                }
            }
        }

        // C. Cobrado
        if ($slug === OrderStatus::SLUG_COBRADO || $request->hasFile('payment_support') || $request->hasFile('collection_support_file')) {
            $responsible = $request->input('payment_responsible_id') ?? $request->input('collected_by_id') ?? $request->input('collected_by');
            if (empty($responsible)) {
                $validator->errors()->add('payment_responsible_id', 'El responsable del cobro es obligatorio.');
                $validator->errors()->add('collected_by_id', 'El responsable del cobro es obligatorio.');
                $validator->errors()->add('collected_by', 'El responsable del cobro es obligatorio.');
            } else {
                if (is_numeric($responsible)) {
                    $user = User::find($responsible);
                    if (! $user) {
                        $validator->errors()->add('payment_responsible_id', 'El usuario seleccionado no existe.');
                        $validator->errors()->add('collected_by_id', 'El usuario seleccionado no existe.');
                        $validator->errors()->add('collected_by', 'El usuario seleccionado no existe.');
                    } else {
                        $dataToUpdate['payment_responsible_id'] = $user->id;
                    }
                } else {
                    $user = User::where('name', $responsible)->first() ?? Auth::user();
                    $dataToUpdate['payment_responsible_id'] = $user?->id ?? Auth::id();
                }
            }

            $supportFile = $request->file('payment_support') ?? $request->file('collection_support_file') ?? $request->file('payment_support_file');
            if (! $supportFile) {
                $validator->errors()->add('payment_support', 'El archivo de soporte de cobro es obligatorio.');
                $validator->errors()->add('collection_support_file', 'El archivo de soporte de cobro es obligatorio.');
                $validator->errors()->add('payment_support_file', 'El archivo de soporte de cobro es obligatorio.');
            } else {
                $dataToUpdate['payment_support_path'] = $supportFile->store('orders/payments', 'public');
            }

            $dataToUpdate['paid_at'] = now();
        }

        // D. Devuelto
        if ($slug === OrderStatus::SLUG_DEVUELTO) {
            $justFile = $request->file('return_justification_file') ?? $request->file('justification_file') ?? $request->file('return_justification');
            if (! $justFile) {
                $validator->errors()->add('return_justification_file', 'El archivo de justificación de devolución es obligatorio.');
                $validator->errors()->add('justification_file', 'El archivo de justificación de devolución es obligatorio.');
                $validator->errors()->add('return_justification', 'El archivo de justificación de devolución es obligatorio.');
            } else {
                $dataToUpdate['return_justification_path'] = $justFile->store('orders/returns', 'public');
            }
        }

        // E. Rechazado
        if ($slug === OrderStatus::SLUG_RECHAZADO) {
            $reason = $request->input('cancellation_reason') ?? $request->input('rejection_reason') ?? $request->input('observation');
            if (empty($reason) || ! is_string($reason) || trim($reason) === '' || strlen(trim($reason)) < 3) {
                $validator->errors()->add('cancellation_reason', 'La justificación de rechazo es obligatoria.');
                $validator->errors()->add('rejection_reason', 'La justificación de rechazo es obligatoria.');
                $validator->errors()->add('observation', 'La justificación de rechazo es obligatoria.');
            } else {
                $dataToUpdate['rejection_reason'] = trim($reason);
            }
        }

        // F. Standalone Payment Modality Updates (e.g. testing modality independently)
        if (! in_array($slug, [OrderStatus::SLUG_DESPACHADO, OrderStatus::SLUG_ENTREGADO])) {
            $term = $request->input('payment_term') ?? $request->input('payment_method') ?? $request->input('payment_modality');
            if (! empty($term)) {
                if (! in_array($term, ['contado', 'credito'])) {
                    $validator->errors()->add('payment_term', 'La modalidad de pago debe ser contado o crédito.');
                    $validator->errors()->add('payment_method', 'La modalidad de pago debe ser contado o crédito.');
                    $validator->errors()->add('payment_modality', 'La modalidad de pago debe ser contado o crédito.');
                } else {
                    $dataToUpdate['payment_term'] = $term;
                    if ($term === 'credito') {
                        $dueDate = $request->input('payment_due_date') ?? $request->input('due_date');
                        if (empty($dueDate)) {
                            $validator->errors()->add('payment_due_date', 'La fecha de vencimiento es obligatoria para crédito.');
                            $validator->errors()->add('due_date', 'La fecha de vencimiento es obligatoria para crédito.');
                        } else {
                            try {
                                $dataToUpdate['payment_due_date'] = Carbon::parse($dueDate)->toDateString();
                            } catch (\Exception $e) {
                                $validator->errors()->add('payment_due_date', 'La fecha de vencimiento no es válida.');
                                $validator->errors()->add('due_date', 'La fecha de vencimiento no es válida.');
                            }
                        }
                    } else {
                        $dataToUpdate['payment_due_date'] = null;
                    }
                }
            }
        }

        // 3. Fail if errors were accumulated
        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }

        // 4. Persist and record history atomically
        $observation = $request->input('observation')
            ?? $request->input('rejection_reason')
            ?? $request->input('cancellation_reason')
            ?? $request->input('return_reason');

        DB::transaction(function () use ($pedido, $dataToUpdate, $previous, $newStatus, $observation): void {
            $pedido->update($dataToUpdate);

            if ($previous !== $newStatus->id || filled($observation)) {
                $this->recordHistory($pedido, $previous, $newStatus->id, $observation);
            }
        });

        return back()->with('success', 'Estado del pedido actualizado.');
    }

    private function storeBase64Image(string $base64Data, string $directory): string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $data = substr($base64Data, strpos($base64Data, ',') + 1);
            $type = strtolower($type[1]);
        } else {
            $data = $base64Data;
            $type = 'png';
        }

        if (! in_array($type, ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
            $type = 'png';
        }

        $decoded = base64_decode($data);
        $fileName = $directory.'/'.Str::uuid().'.'.$type;
        Storage::disk('public')->put($fileName, $decoded);

        return $fileName;
    }

    private function formData(Order $order): array
    {
        return [
            'order' => $order,
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'items' => $order->exists ? $order->items : collect(),
        ];
    }

    private function validatedData(Request $request, ?Order $order = null): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:100', 'unique:orders,code'.($order ? ','.$order->id : '')],
            'client_id' => ['required', 'exists:clients,id'],
            'billing_type' => ['required', 'in:remision,factura_electronica'],
            'ordered_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
    }

    /**
     * Código de pedido único: sufijo aleatorio evita colisiones en
     * creaciones simultaneas dentro del mismo segundo.
     */
    private function generateCode(): string
    {
        do {
            $code = 'PED-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));
        } while (Order::where('code', $code)->exists());

        return $code;
    }

    private function syncItems(Order $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $item) {
            $order->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ]);
        }
    }

    private function recordHistory(Order $order, ?int $previousStatusId, int $newStatusId, ?string $observation): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'previous_status_id' => $previousStatusId,
            'new_status_id' => $newStatusId,
            'user_id' => Auth::id(),
            'observation' => $observation,
            'changed_at' => now(),
        ]);
    }

    public function pdf(Order $pedido)
    {
        $pedido->load(['client', 'items.product', 'creator']);
        $pdf = Pdf::loadView('admin.orders.pdf', compact('pedido'));

        return $pdf->stream('pedido-'.$pedido->code.'.pdf');
    }
}
