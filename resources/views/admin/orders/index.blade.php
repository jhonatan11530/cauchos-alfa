@extends('admin.layout.plantilla')
@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row">
        <div><h2 class="text-white pb-2 fw-bold">Pedidos</h2><h5 class="text-white op-7 mb-2">Gestión y seguimiento de pedidos</h5></div>
    </div>
@endsection
@section('contenido')
    <div class="page-inner mt--5"><div class="card mb-3"><div class="card-body py-3">
            <form method="GET" action="{{ route('pedidos.index') }}" class="form-inline">
                <div class="row g-2 align-items-center">
                    <div class="col-md-5"><input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar por código o cliente..."></div>
                    <div class="col-md-4">
                        <select name="status" class="form-control">
                            <option value="">Todos los estados</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}" @selected(request('status') == $status->id)>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary btn-block"><i class="fas fa-search"></i> Buscar</button>
                        <a href="{{ route('pedidos.index') }}" class="btn btn-secondary">Limpiar</a>
                    </div>
                </div>
            </form>
        </div></div></div>
    <div class="page-inner mt--5"><div class="card"><div class="card-body table-responsive">
        <table class="table table-head-bg-primary">
            <thead><tr><th>Código</th><th>Cliente</th><th>Fecha</th><th>Estado</th><th>Total</th><th>Acciones</th></tr></thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td><a href="{{ route('pedidos.show', $order) }}">{{ $order->code }}</a></td>
                        <td>{{ $order->client->name }}</td>
                        <td>{{ optional($order->ordered_at)->format('Y-m-d') }}</td>
                        <td><span class="badge" style="background: {{ $order->status->color }}; color: #fff">{{ $order->status->name }}</span></td>
                        <td>${{ number_format($order->total, 2) }}</td>
                        <td>
                            <a href="{{ route('pedidos.edit', $order) }}" class="btn btn-sm btn-primary">Editar</a>
                            <form action="{{ route('pedidos.destroy', $order) }}" method="POST" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-warning">Cancelar</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center">No hay pedidos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $orders->links() }}
    </div></div></div>
@endsection

