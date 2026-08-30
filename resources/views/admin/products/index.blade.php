@extends('admin.layout.plantilla')
@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row">
        <div><h2 class="text-white pb-2 fw-bold">Productos</h2><h5 class="text-white op-7 mb-2">Gestión de productos</h5></div>
        <div class="ml-md-auto py-2 py-md-0"><a href="{{ route('productos.create') }}" class="btn btn-primary">Crear producto</a></div>
    </div>
@endsection
@section('contenido')
    @include('admin.partials.search')
    <div class="page-inner mt--5"><div class="card"><div class="card-body table-responsive">
        <table class="table table-head-bg-primary">
            <thead><tr><th>Nombre</th><th>Referencia</th><th>Categoría</th><th>Precio</th><th>Disponibilidad</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td>{{ $product->name }}</td><td>{{ $product->reference }}</td><td>{{ $product->category->name ?? 'Sin categoría' }}</td>
                        <td>{{ $product->price !== null ? '$'.number_format($product->price, 2) : '' }}</td><td>{{ $product->availability }}</td><td>{{ $product->is_active ? 'Activo' : 'Inactivo' }}</td>
                        <td>@include('admin.partials.actions', ['edit' => route('productos.edit', $product), 'destroy' => route('productos.destroy', $product), 'active' => $product->is_active])</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center">No hay productos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $products->links() }}
    </div></div></div>
@endsection
