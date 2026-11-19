<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\CsvHandlerService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollectionController extends Controller
{
    public function __construct(protected CsvHandlerService $csvHandler) {}

    /**
     * Listado y métricas de cartera / cuentas por cobrar.
     */
    public function index(Request $request): View
    {
        $baseCreditQuery = Order::query()->where('payment_term', 'credito');

        $today = now()->startOfDay();

        $metrics = [
            'total' => (clone $baseCreditQuery)->count(),
            'pending' => (clone $baseCreditQuery)->whereNull('paid_at')->count(),
            'overdue' => (clone $baseCreditQuery)->whereNull('paid_at')->whereDate('payment_due_date', '<', $today)->count(),
            'due_soon' => (clone $baseCreditQuery)->whereNull('paid_at')
                ->whereDate('payment_due_date', '>=', $today)
                ->whereDate('payment_due_date', '<=', $today->copy()->addDays(7))
                ->count(),
            'collected' => (clone $baseCreditQuery)->whereNotNull('paid_at')->count(),
        ];

        $orders = Order::with(['client', 'creator', 'status', 'paymentResponsible'])
            ->where('payment_term', 'credito')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('code', 'like', $term)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->when($request->filled('client_id'), function ($q) use ($request) {
                $q->where('client_id', $request->input('client_id'));
            })
            ->when($request->input('filter') === 'vencidas', function ($q) use ($today) {
                $q->whereNull('paid_at')->whereDate('payment_due_date', '<', $today);
            })
            ->when($request->input('filter') === 'por_vencer', function ($q) use ($today) {
                $q->whereNull('paid_at')
                    ->whereDate('payment_due_date', '>=', $today)
                    ->whereDate('payment_due_date', '<=', $today->copy()->addDays(7));
            })
            ->when($request->input('filter') === 'pendientes', function ($q) {
                $q->whereNull('paid_at');
            })
            ->when($request->input('filter') === 'cobradas', function ($q) {
                $q->whereNotNull('paid_at');
            })
            ->orderByRaw('CASE WHEN paid_at IS NULL THEN 0 ELSE 1 END, payment_due_date ASC')
            ->paginate(15)
            ->withQueryString();

        $clients = Client::where('is_active', true)->orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('name')->get();

        return view('admin.collections.index', [
            'orders' => $orders,
            'metrics' => $metrics,
            'clients' => $clients,
            'users' => $users,
            'currentFilter' => $request->input('filter', 'todas'),
        ]);
    }

    /**
     * Registra un pago/cobro directo con soporte fotográfico o PDF.
     */
    public function registerPayment(Request $request, Order $pedido): RedirectResponse
    {
        $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_responsible_id' => ['nullable', 'exists:users,id'],
            'payment_support' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'observation' => ['nullable', 'string', 'max:500'],
        ]);

        $pedido->loadMissing('status');
        $cobradoStatus = OrderStatus::where('slug', OrderStatus::SLUG_COBRADO)->first();

        if ($pedido->status && (! $cobradoStatus || ! $pedido->status->canTransitionTo($cobradoStatus))) {
            throw ValidationException::withMessages([
                'order_status_id' => 'No se puede registrar el cobro para un pedido en su estado actual.',
            ]);
        }

        $dataToUpdate = [
            'paid_at' => Carbon::parse($request->input('payment_date')),
            'payment_responsible_id' => $request->input('payment_responsible_id', auth()->id()),
        ];

        if ($request->hasFile('payment_support')) {
            $file = $request->file('payment_support');
            $filename = 'orders/supports/'.Str::uuid().'.'.$file->getClientOriginalExtension();
            Storage::disk('public')->put($filename, file_get_contents($file));
            $dataToUpdate['payment_support_path'] = $filename;
        }

        $previousStatus = $pedido->order_status_id;
        if ($cobradoStatus) {
            $dataToUpdate['order_status_id'] = $cobradoStatus->id;
        }

        $observation = filled($request->input('observation'))
            ? trim((string) $request->input('observation'))
            : 'Pago registrado desde el módulo de cartera.';

        DB::transaction(function () use ($pedido, $dataToUpdate, $cobradoStatus, $previousStatus, $observation): void {
            $pedido->update($dataToUpdate);

            if ($cobradoStatus && $previousStatus !== $cobradoStatus->id) {
                OrderStatusHistory::create([
                    'order_id' => $pedido->id,
                    'previous_status_id' => $previousStatus,
                    'new_status_id' => $cobradoStatus->id,
                    'user_id' => auth()->id(),
                    'observation' => $observation,
                    'changed_at' => now(),
                ]);
            }
        });

        return back()->with('success', "Pago del pedido {$pedido->code} registrado correctamente.");
    }

    /**
     * Exporta el reporte de cartera filtrado a CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $today = now()->startOfDay();

        $query = Order::with(['client', 'creator', 'status', 'paymentResponsible'])
            ->where('payment_term', 'credito')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('code', 'like', $term)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->when($request->filled('client_id'), function ($q) use ($request) {
                $q->where('client_id', $request->input('client_id'));
            })
            ->when($request->input('filter') === 'vencidas', function ($q) use ($today) {
                $q->whereNull('paid_at')->whereDate('payment_due_date', '<', $today);
            })
            ->when($request->input('filter') === 'pendientes', function ($q) {
                $q->whereNull('paid_at');
            })
            ->when($request->input('filter') === 'cobradas', function ($q) {
                $q->whereNotNull('paid_at');
            })
            ->orderByRaw('CASE WHEN paid_at IS NULL THEN 0 ELSE 1 END, payment_due_date ASC');

        $headers = [
            'Código Pedido',
            'Cliente',
            'Documento',
            'Teléfono',
            'Ciudad',
            'Tipo Facturación',
            'Fecha Pedido',
            'Fecha Vencimiento',
            'Estado de Cobro',
            'Días de Mora / Restantes',
            'Fecha de Pago',
            'Responsable del Cobro',
        ];

        $rowsGenerator = function () use ($query, $today) {
            foreach ($query->cursor() as $order) {
                $dueDate = $order->payment_due_date ? Carbon::parse($order->payment_due_date)->startOfDay() : null;
                $statusCobro = 'Pendiente';
                $moraText = 'Sin fecha';

                if ($order->paid_at) {
                    $statusCobro = 'Cobrado';
                    $moraText = 'Al día (Pagado)';
                } elseif ($dueDate) {
                    $diff = $today->diffInDays($dueDate, false);
                    if ($diff < 0) {
                        $statusCobro = 'Vencida';
                        $moraText = 'Vencida hace '.abs($diff).' días';
                    } elseif ($diff === 0) {
                        $statusCobro = 'Vence Hoy';
                        $moraText = 'Vence hoy';
                    } else {
                        $statusCobro = 'Por Vencer';
                        $moraText = 'Vence en '.$diff.' días';
                    }
                }

                yield [
                    $order->code,
                    $order->client?->name ?? 'N/A',
                    $order->client?->document ?? '',
                    $order->client?->phone ?? '',
                    $order->client?->city ?? '',
                    $order->billing_type == 'factura_electronica' ? 'Factura Electrónica' : 'Remisión',
                    $order->ordered_at ? Carbon::parse($order->ordered_at)->format('d/m/Y') : '',
                    $dueDate ? $dueDate->format('d/m/Y') : '',
                    $statusCobro,
                    $moraText,
                    $order->paid_at ? Carbon::parse($order->paid_at)->format('d/m/Y H:i') : '',
                    $order->paymentResponsible?->name ?? ($order->paid_at ? 'No especificado' : ''),
                ];
            }
        };

        $filename = 'cartera_cauchos_alfa_'.date('Ymd_His').'.csv';

        return $this->csvHandler->streamDownload($filename, $headers, $rowsGenerator());
    }
}
