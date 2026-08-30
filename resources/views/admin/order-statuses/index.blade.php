@extends('admin.layout.plantilla')
@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row">
        <div><h2 class="text-white pb-2 fw-bold">Estados de pedido</h2><h5 class="text-white op-7 mb-2">Flujo de trazabilidad</h5></div>
        <div class="ml-md-auto py-2 py-md-0"><a href="{{ route('estados-pedido.create') }}" class="btn btn-primary">Crear estado</a></div>
    </div>
@endsection
@section('contenido')
    <div class="page-inner mt--5"><div class="card"><div class="card-body table-responsive">
        <table class="table table-head-bg-primary">
            <thead><tr><th>Orden</th><th>Nombre</th><th>Color</th><th>Final</th><th>Cancelado</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                @foreach ($statuses as $status)
                    <tr><td>{{ $status->sort_order }}</td><td>{{ $status->name }}</td><td><span class="badge" style="background: {{ $status->color }}; color: #fff">{{ $status->color }}</span></td><td>{{ $status->is_final ? 'Sí' : 'No' }}</td><td>{{ $status->is_cancelled ? 'Sí' : 'No' }}</td><td>{{ $status->is_active ? 'Activo' : 'Inactivo' }}</td><td>@include('admin.partials.actions', ['edit' => route('estados-pedido.edit', $status), 'destroy' => route('estados-pedido.destroy', $status), 'active' => $status->is_active])</td></tr>
                @endforeach
            </tbody>
        </table>
        {{ $statuses->links() }}
    </div></div></div>
@endsection
