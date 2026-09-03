<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Support\SellerCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::with(['client', 'status'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->string('q') . '%';
                $query->where(fn ($q) => $q->where('code', 'like', $term)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('order_status_id', $request->input('status')))
            ->latest();

        return view('admin.orders.index', [
            'orders' => $orders->paginate(10)->withQueryString(),
            'statuses' => OrderStatus::orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.orders.form', $this->formData(new Order()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $user = Auth::user();
        $fromSellerSite = $request->boolean('from_seller_site') || ($user && $user->isSeller());

        $order = DB::transaction(function () use ($data, $request) {
            $status = OrderStatus::where('slug', 'pedido-creado')->first() ?? OrderStatus::orderBy('sort_order')->firstOrFail();
            $order = Order::create([
                'code' => $this->generateCode(),
                'client_id' => $data['client_id'],
                'created_by' => Auth::id(),
                'order_status_id' => $status->id,
                'ordered_at' => now(),
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
            'order' => $pedido->load(['client', 'status', 'items.product', 'histories.previousStatus', 'histories.newStatus', 'histories.user']),
            'statuses' => OrderStatus::where('is_active', true)->orderBy('sort_order')->get(),
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
                'ordered_at' => $data['ordered_at'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($pedido, $request->input('items', []));
        });

        return redirect()->route('pedidos.index')->with('success', 'Pedido actualizado correctamente.');
    }

    public function destroy(Order $pedido): RedirectResponse
    {
        $cancelled = OrderStatus::where('slug', 'pedido-cancelado')->first();
        if ($cancelled && $pedido->order_status_id !== $cancelled->id) {
            $previous = $pedido->order_status_id;
            $pedido->update(['order_status_id' => $cancelled->id]);
            $this->recordHistory($pedido, $previous, $cancelled->id, 'Pedido cancelado desde el listado.');
        }

        return back()->with('success', 'Pedido cancelado correctamente.');
    }

    public function updateStatus(Request $request, Order $pedido): RedirectResponse
    {
        $data = $request->validate([
            'order_status_id' => ['required', 'exists:order_statuses,id'],
            'observation' => ['nullable', 'string'],
        ]);

        $previous = $pedido->order_status_id;
        $pedido->update(['order_status_id' => $data['order_status_id']]);
        $this->recordHistory($pedido, $previous, $data['order_status_id'], $data['observation'] ?? null);

        return back()->with('success', 'Estado del pedido actualizado.');
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
            $code = 'PED-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));        } while (Order::where('code', $code)->exists());

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
}
