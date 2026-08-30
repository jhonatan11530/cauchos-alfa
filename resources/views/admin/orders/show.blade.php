@extends('admin.layout.plantilla')
@section('banner')
    <div><h2 class="text-white pb-2 fw-bold">Pedido {{ $order->code }}</h2><h5 class="text-white op-7 mb-2">Detalle y trazabilidad</h5></div>
@endsection
@section('contenido')
    <div class="page-inner mt--5">
        <div class="row">
            <div class="col-md-8">
                <div class="card"><div class="card-header"><h4 class="card-title">Detalle</h4></div><div class="card-body">
                    <p><strong>Cliente:</strong> {{ $order->client->name }}</p>
                    <p><strong>Estado actual:</strong> <span class="badge" style="background: {{ $order->status->color }}; color: #fff">{{ $order->status->name }}</span></p>
                    <p><strong>Observaciones:</strong> {{ $order->notes }}</p>
                    <table class="table">
                        <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th></tr></thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr><td>{{ $item->product->name }}</td><td>{{ $item->quantity }}</td><td>${{ number_format($item->unit_price, 2) }}</td><td>${{ number_format($item->subtotal, 2) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><th colspan="3">Total</th><th>${{ number_format($order->total, 2) }}</th></tr></tfoot>
                    </table>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-header"><h4 class="card-title">Cambiar estado</h4></div><div class="card-body">
                    <form action="{{ route('pedidos.estado', $order) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <div class="form-group"><label>Nuevo estado</label><select name="order_status_id" class="form-control">@foreach ($statuses as $status)<option value="{{ $status->id }}" @selected($order->order_status_id == $status->id)>{{ $status->name }}</option>@endforeach</select></div>
                        <div class="form-group"><label>Observación</label><textarea name="observation" class="form-control"></textarea></div>
                        <button class="btn btn-primary btn-block">Actualizar estado</button>
                    </form>
                </div></div>
            </div>
        </div>
        <div class="card"><div class="card-header"><h4 class="card-title">Historial de trazabilidad</h4></div><div class="card-body table-responsive">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Anterior</th><th>Nuevo</th><th>Usuario</th><th>Observación</th></tr></thead>
                <tbody>
                    @forelse ($order->histories as $history)
                        <tr><td>{{ $history->changed_at->format('Y-m-d H:i') }}</td><td>{{ $history->previousStatus->name ?? 'Inicio' }}</td><td>{{ $history->newStatus->name }}</td><td>{{ $history->user->name ?? 'Sistema' }}</td><td>{{ $history->observation }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No hay trazabilidad registrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
    </div>
@endsection
