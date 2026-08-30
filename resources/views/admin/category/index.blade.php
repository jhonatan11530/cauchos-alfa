@extends('admin.layout.plantilla')
@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row">
        <div>
            <h2 class="text-white pb-2 fw-bold">Categorías</h2>
            <h5 class="text-white op-7 mb-2">Gestión de categorías</h5>
        </div>
        <div class="ml-md-auto py-2 py-md-0">
            <a href="{{ route('categoria.create') }}" class="btn btn-primary">Crear categoría</a>
        </div>
    </div>
@endsection
@section('contenido')
    <div class="page-inner mt--5">
        <div class="card"><div class="card-body table-responsive">
            <table class="table table-head-bg-primary">
                <thead><tr><th>ID</th><th>Nombre</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category->id }}</td>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->description }}</td>
                            <td>{{ $category->is_active ? 'Activa' : 'Inactiva' }}</td>
                            <td>@include('admin.partials.actions', ['edit' => route('categoria.edit', $category), 'destroy' => route('categoria.destroy', $category), 'active' => $category->is_active])</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No hay categorías registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $categories->links() }}
        </div></div>
    </div>
@endsection
