@extends('admin.layout.plantilla')
@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row">
        <div><h2 class="text-white pb-2 fw-bold">{{ $catalog->name }}</h2><h5 class="text-white op-7 mb-2">Detalle del catálogo</h5></div>
        <div class="ml-md-auto py-2 py-md-0"><a href="{{ route('catalogos.pdf', $catalog) }}" class="btn btn-info">Descargar PDF</a></div>
    </div>
@endsection
@section('contenido')
    <div class="page-inner mt--5"><div class="card"><div class="card-body">
        <p>{{ $catalog->description }}</p>
        <table class="table">
            <thead><tr><th>Producto</th><th>Referencia</th><th>Categoría</th><th>Precio</th></tr></thead>
            <tbody>
                @forelse ($catalog->products as $product)
                    <tr><td>{{ $product->name }}</td><td>{{ $product->reference }}</td><td>{{ $product->category->name ?? '' }}</td><td>{{ $product->price !== null ? '$'.number_format($product->price, 2) : '' }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center">Este catálogo no tiene productos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div></div>
@endsection
