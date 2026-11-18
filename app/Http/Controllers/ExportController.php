<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Services\CsvHandlerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(protected CsvHandlerService $csvHandler) {}

    /**
     * Exporta la lista de clientes con los filtros activos.
     */
    public function exportClients(Request $request): StreamedResponse
    {
        $query = Client::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)
                    ->orWhere('document', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('city', 'like', $term));
            })
            ->when($request->filled('is_active'), function ($q) use ($request) {
                $q->where('is_active', $request->boolean('is_active'));
            })
            ->orderBy('name');

        $headers = ['ID', 'Nombre', 'Documento', 'Teléfono', 'Email', 'Dirección', 'Ciudad', 'Notas', 'Estado', 'Fecha Registro'];

        $rowsGenerator = function () use ($query) {
            foreach ($query->cursor() as $client) {
                yield [
                    $client->id,
                    $client->name,
                    $client->document,
                    $client->phone,
                    $client->email,
                    $client->address,
                    $client->city,
                    $client->notes,
                    $client->is_active ? 'Activo' : 'Inactivo',
                    $client->created_at ? $client->created_at->format('d/m/Y H:i') : '',
                ];
            }
        };

        $filename = 'clientes_cauchos_alfa_'.date('Ymd_His').'.csv';

        return $this->csvHandler->streamDownload($filename, $headers, $rowsGenerator());
    }

    /**
     * Exporta la lista de productos con los filtros aplicados.
     */
    public function exportProducts(Request $request): StreamedResponse
    {
        $query = Product::with('category')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)
                    ->orWhere('reference', 'like', $term));
            })
            ->when($request->filled('category_id'), function ($q) use ($request) {
                $q->where('category_id', $request->input('category_id'));
            })
            ->when($request->filled('availability'), function ($q) use ($request) {
                $q->where('availability', $request->input('availability'));
            })
            ->orderBy('name');

        $headers = ['ID', 'Nombre', 'Referencia', 'Categoría', 'Disponibilidad', 'Estado', 'Descripción', 'Características', 'Fecha Registro'];

        $rowsGenerator = function () use ($query) {
            foreach ($query->cursor() as $product) {
                yield [
                    $product->id,
                    $product->name,
                    $product->reference,
                    $product->category?->name ?? 'Sin categoría',
                    $product->availability,
                    $product->is_active ? 'Activo' : 'Inactivo',
                    $product->description,
                    $product->features,
                    $product->created_at ? $product->created_at->format('d/m/Y H:i') : '',
                ];
            }
        };

        $filename = 'productos_cauchos_alfa_'.date('Ymd_His').'.csv';

        return $this->csvHandler->streamDownload($filename, $headers, $rowsGenerator());
    }

    /**
     * Exporta los pedidos con desglose por ítem y filtros avanzados.
     */
    public function exportOrders(Request $request): StreamedResponse
    {
        $query = Order::with(['client', 'status', 'creator', 'items.product'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('code', 'like', $term)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('order_status_id', $request->input('status'));
            })
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->whereDate('ordered_at', '>=', $request->input('date_from'));
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $q->whereDate('ordered_at', '<=', $request->input('date_to'));
            })
            ->latest('ordered_at')
            ->latest('id');

        $headers = [
            'Código Pedido',
            'Fecha Pedido',
            'Cliente',
            'Documento Cliente',
            'Teléfono',
            'Ciudad',
            'Vendedor / Creador',
            'Estado',
            'Tipo Facturación',
            'Producto',
            'Referencia',
            'Cantidad',
            'Modalidad Pago',
            'Vencimiento Crédito',
            'Fecha Cobro',
        ];

        $rowsGenerator = function () use ($query) {
            foreach ($query->cursor() as $order) {
                $orderDate = $order->ordered_at ? Carbon::parse($order->ordered_at)->format('d/m/Y') : ($order->created_at ? $order->created_at->format('d/m/Y') : '');
                $dueDate = $order->payment_due_date ? Carbon::parse($order->payment_due_date)->format('d/m/Y') : '';
                $paidDate = $order->paid_at ? Carbon::parse($order->paid_at)->format('d/m/Y H:i') : '';

                if ($order->items->isEmpty()) {
                    yield [
                        $order->code,
                        $orderDate,
                        $order->client?->name ?? 'N/A',
                        $order->client?->document ?? '',
                        $order->client?->phone ?? '',
                        $order->client?->city ?? '',
                        $order->creator?->name ?? 'Sistema',
                        $order->status?->name ?? 'N/A',
                        $order->billing_type == 'factura_electronica' ? 'Factura Electrónica' : 'Remisión',
                        'Sin productos',
                        '',
                        0,
                        $order->payment_term ? ucfirst($order->payment_term) : 'No definida',
                        $dueDate,
                        $paidDate,
                    ];
                } else {
                    foreach ($order->items as $item) {
                        yield [
                            $order->code,
                            $orderDate,
                            $order->client?->name ?? 'N/A',
                            $order->client?->document ?? '',
                            $order->client?->phone ?? '',
                            $order->client?->city ?? '',
                            $order->creator?->name ?? 'Sistema',
                            $order->status?->name ?? 'N/A',
                            $order->billing_type == 'factura_electronica' ? 'Factura Electrónica' : 'Remisión',
                            $item->product?->name ?? 'Producto eliminado',
                            $item->product?->reference ?? '',
                            $item->quantity,
                            $order->payment_term ? ucfirst($order->payment_term) : 'No definida',
                            $dueDate,
                            $paidDate,
                        ];
                    }
                }
            }
        };

        $filename = 'pedidos_cauchos_alfa_'.date('Ymd_His').'.csv';

        return $this->csvHandler->streamDownload($filename, $headers, $rowsGenerator());
    }
}
