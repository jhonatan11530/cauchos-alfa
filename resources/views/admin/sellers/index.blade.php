@extends('admin.layout.plantilla')
@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row">
        <div>
            <h2 class="text-white pb-2 fw-bold">Vendedores</h2>
            <h5 class="text-white op-7 mb-2">Gestión de vendedores y sus códigos de acceso</h5>
        </div>
        <div class="ml-md-auto py-2 py-md-0"><a href="{{ route('vendedores.create') }}" class="btn btn-primary">Crear
                vendedor</a></div>
    </div>
@endsection
@section('contenido')
    @include('admin.partials.search')
    <div class="page-inner mt--5">
        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-head-bg-primary">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Código de acceso</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sellers as $seller)
                            <tr>
                                <td>{{ $seller->name }}</td>
                                <td>{{ $seller->email }}</td>
                                <td>{{ $seller->phone ?? '-' }}</td>
                                <td><span class="badge badge-secondary">{{ $seller->seller_code ?? '-' }}</span></td>
                                <td>{{ $seller->is_active ? 'Activo' : 'Inactivo' }}</td>
                                <td>@include('admin.partials.actions', [
                                    'edit' => route('vendedores.edit', $seller),
                                    'destroy' => route('vendedores.destroy', $seller),
                                    'active' => $seller->is_active,
                                ])</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No hay vendedores registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $sellers->links() }}
            </div>
        </div>
    </div>
@endsection
