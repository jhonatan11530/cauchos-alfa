@extends('admin.layout.plantilla')
@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row">
        <div><h2 class="text-white pb-2 fw-bold">Clientes</h2><h5 class="text-white op-7 mb-2">Gestión de clientes</h5></div>
        <div class="ml-md-auto py-2 py-md-0 d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-light mr-2 mb-2 mb-md-0" data-toggle="modal" data-target="#importModal">
                <i class="fas fa-file-import mr-1"></i> Importar CSV
            </button>
            <a href="{{ route('export.clients', request()->all()) }}" class="btn btn-outline-light mr-2 mb-2 mb-md-0">
                <i class="fas fa-file-excel mr-1"></i> Exportar CSV
            </a>
            <a href="{{ route('clientes.create') }}" class="btn btn-primary mb-2 mb-md-0">Crear cliente</a>
        </div>
    </div>
@endsection
@section('contenido')
    @if (session('import_errors'))
        <div class="page-inner mt--5 pb-0">
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <strong><i class="fas fa-exclamation-triangle mr-1"></i> Observaciones de la importación:</strong>
                <ul class="mb-0 mt-1 pl-3">
                    @foreach (session('import_errors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        </div>
    @endif
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

    @include('admin.imports.modal', [
        'importTitle' => 'Importar Clientes desde CSV',
        'importAction' => route('import.clients'),
        'importTemplateUrl' => route('import.template', ['tipo' => 'clientes']),
    ])
@endsection
