@extends('admin.layout.plantilla')
@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row">
        <div><h2 class="text-white pb-2 fw-bold">Clientes</h2><h5 class="text-white op-7 mb-2">Gestión de clientes</h5></div>
        <div class="ml-md-auto py-2 py-md-0"><a href="{{ route('clientes.create') }}" class="btn btn-primary">Crear cliente</a></div>
    </div>
@endsection
@section('contenido')
    @include('admin.partials.search')
    <div class="page-inner mt--5">
        <div class="card"><div class="card-body table-responsive">
            <table class="table table-head-bg-primary">
                <thead><tr><th>Nombre</th><th>Documento</th><th>Teléfono</th><th>Ciudad</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    @forelse ($clients as $client)
                        <tr>
                            <td><a href="{{ route('clientes.show', $client) }}">{{ $client->name }}</a></td>
                            <td>{{ $client->document }}</td>
                            <td>{{ $client->phone }}</td>
                            <td>{{ $client->city }}</td>
                            <td>{{ $client->is_active ? 'Activo' : 'Inactivo' }}</td>
                            <td>@include('admin.partials.actions', ['edit' => route('clientes.edit', $client), 'destroy' => route('clientes.destroy', $client), 'active' => $client->is_active])</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center">No hay clientes registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $clients->links() }}
        </div></div>
    </div>
@endsection
