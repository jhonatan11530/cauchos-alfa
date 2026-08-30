@extends('site.layout')

@section('title', 'Catálogo | Cauchos Alfa')

@section('contenido')
    <section class="bg-dark text-white py-5 text-center">
        <div class="container">
            <h1 class="fw-bold">Nuestro catálogo</h1>
            <p class="lead mb-0">Explora los productos disponibles en Cauchos Alfa.</p>
        </div>
    </section>

    <section class="container py-5">
        {{-- Categorías --}}
        <h3 class="fw-bold mb-3">Categorías</h3>
        <div class="row g-3 mb-5">
            <div class="col-6 col-md-4 col-lg-3">
                <a href="{{ route('site.catalog') }}" class="text-decoration-none">
                    <div class="card h-100 shadow-sm text-center {{ empty(request('categoria')) ? 'border-danger border-2' : '' }}">
                        <div class="card-body">
                            <i class="bi bi-grid-3x3-gap-fill text-danger fs-1"></i>
                            <h5 class="card-title mt-2 mb-0 text-dark">Todos</h5>
                        </div>
                    </div>
                </a>
            </div>
            @foreach ($categories as $category)
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="{{ route('site.catalog', ['categoria' => $category->id]) }}" class="text-decoration-none">
                        <div class="card h-100 shadow-sm text-center {{ request('categoria') == $category->id ? 'border-danger border-2' : '' }}">
                            <div class="card-body">
                                <i class="bi bi-tag-fill text-danger fs-1"></i>
                                <h5 class="card-title mt-2 mb-0 text-dark">{{ $category->name }}</h5>
                                <p class="text-muted small mb-0">{{ $category->products_count }} producto(s)</p>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        {{-- Productos --}}
        <h3 class="fw-bold mb-3">
            @if ($selectedCategory)
                Productos de: {{ $selectedCategory->name }}
            @else
                Todos los productos
            @endif
        </h3>
        <div class="row g-4">
            @forelse ($products as $product)
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card card-product h-100 shadow-sm">
                        @if ($product->image_path)
                            <img src="{{ asset('storage/'.$product->image_path) }}" class="card-img-top" alt="{{ $product->name }}">
                        @else
                            <img src="https://via.placeholder.com/300x200?text=Producto" class="card-img-top" alt="{{ $product->name }}">
                        @endif
                        <div class="card-body">
                            <h5 class="card-title">{{ $product->name }}</h5>
                            <p class="card-text text-muted small">{{ \Illuminate\Support\Str::limit($product->description, 80) }}</p>
                            <p class="small mb-1"><strong>Referencia:</strong> {{ $product->reference ?? 'N/A' }}</p>
                            <p class="small mb-1"><strong>Categoría:</strong> {{ $product->category->name ?? 'General' }}</p>
                            <span class="badge {{ $product->availability === 'disponible' ? 'bg-success' : 'bg-secondary' }}">
                                {{ ($product->availability === 'disponible') ? 'Disponible' : 'No disponible' }}
                            </span>

                            @auth
                                @if (auth()->user()->isSeller())
                                    <form method="POST" action="{{ route('site.seller.add') }}" class="mt-3">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="quantity" value="1" min="1" class="form-control" style="max-width: 80px" required>
                                            <button type="submit" class="btn btn-brand" title="Agregar a mi pedido" {{ $product->availability === 'disponible' ? '' : 'disabled' }}>
                                                <i class="bi bi-cart-plus"></i> Agregar
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            @endauth
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-center text-muted">No hay productos en esta categoría por el momento.</p>
            @endforelse
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $products->appends(request()->query())->links() }}
        </div>
    </section>
@endsection