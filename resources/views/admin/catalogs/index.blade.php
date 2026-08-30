@extends('admin.layout.plantilla')
@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row">
        <div>
            <h2 class="text-white pb-2 fw-bold">Catálogos</h2>
            <h5 class="text-white op-7 mb-2">Gestión y exportación de catálogos</h5>
        </div>
        <div class="ml-md-auto py-2 py-md-0"><a href="{{ route('catalogos.create') }}" class="btn btn-primary">Crear
                catálogo</a></div>
    </div>
@endsection
@section('contenido')
    <div class="page-inner mt--5">
        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-head-bg-primary">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Empresa</th>
                            <th>Productos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($catalogs as $catalog)
                            <tr>
                                <td><a href="{{ route('catalogos.show', $catalog) }}">{{ $catalog->name }}</a></td>
                                <td>{{ $catalog->company_name }}</td>
                                <td>{{ $catalog->products_count }}</td>
                                <td>{{ $catalog->is_active ? 'Activo' : 'Inactivo' }}</td>
                                <td>
                                    <a href="{{ route('catalogos.pdf', $catalog) }}" class="btn btn-sm btn-info"
                                        target="_blank">PDF</a>
                                    @include('admin.partials.actions', [
                                        'edit' => route('catalogos.edit', $catalog),
                                        'destroy' => route('catalogos.destroy', $catalog),
                                        'active' => $catalog->is_active,
                                    ])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">No hay catálogos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $catalogs->links() }}
            </div>
        </div>
    </div>
@endsection
