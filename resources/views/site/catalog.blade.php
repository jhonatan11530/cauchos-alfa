"@extends('site.layouts.app')

@section('title', 'Catálogo | Cauchos Alfa')

@section('content')
    <section class="bg-void text-white py-5 text-center" style="margin-top: 70px;">
        <div class="container">
            <h1 class="section-title">Nuestro Catálogo</h1>
            <p class="lead mb-0">Explora los neumáticos de alto rendimiento disponibles.</p>
        </div>
    </section>

    <section class="container py-5">
        <div class="text-center mb-5">
            <h3 class="font-weight-bold text-white">Categorías</h3>
            <p class="text-muted-custom">Filtra por el tipo de neumático que necesitas</p>
        </div>

        <div class="row mb-5">
            <div class="col-6 col-md-4 col-lg-3 mb-3">
                <a href="{{ route('site.catalog') }}" style="text-decoration: none;">
                    <div class="card h-100 shadow-sm text-center {{ empty(request('categoria')) ? 'border-danger' : '' }}"
                        style="background: var(--bike-charcoal); border-width: 2px; transition: 0.3s; border-radius: 20px; color: white;">
                        <div class="card-body">
                            <div class="text-red mb-2"><i class="bi bi-grid-3x3-gap-fill display-4"></i></div>
                            <h5 class="card-title mt-2 mb-0 text-white">Todos</h5>
                        </div>
                    </div>
                </a>
            </div>
            @foreach ($categories as $category)
                <div class="col-6 col-md-4 col-lg-3 mb-3">
                    <a href="{{ route('site.catalog', ['categoria' => $category->id]) }}" style="text-decoration: none;">
                        <div class="card h-100 shadow-sm text-center {{ request('categoria') == $category->id ? 'border-danger' : '' }}"
                            style="background: var(--bike-charcoal); border-width: 2px; transition: 0.3s; border-radius: 20px; color: white;">
                            <div class="card-body">
                                <div class="text-red mb-2"><i class="bi bi-tag-fill display-4"></i></div>
                                <h5 class="card-title mt-2 mb-0 text-white">{{ $category->name }}</h5>
                                <p class="text-muted-custom small mb-0">{{ $category->products_count }} producto(s)</p>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-between align-items-end mb-4">
            <h3 class="font-weight-bold text-white mb-0">
                @if ($selectedCategory)
                    {{ $selectedCategory->name }}
                @else
                    Todos los productos
                @endif
            </h3>
            <span class="badge p-2" style="background: var(--bike-red); color: white;">{{ $products->total() }}
                resultados</span>
        </div>

        <div class="row">
            @if (count($products) > 1)
                @foreach ($products as $product)
                    <div class="col-12 col-sm-6 col-lg-3 mb-4">
                        <div class="product-card h-100 shadow-sm">
                            <div class="bg-graphite d-flex align-items-center justify-content-center"
                                style="height: 220px; overflow: hidden; background: var(--bike-graphite) !important;">
                                {{-- Galería de producto: carousel si hay varias imágenes, imagen simple si hay una --}}
                                @php
                                    $urls = $product->imageUrls();
                                @endphp
                                @if (count($urls) > 1)
                                    <div id="gallery-{{ $product->id }}" class="carousel slide carousel-fade"
                                        data-bs-ride="carousel">
                                        <div class="carousel-inner">
                                            @foreach ($urls as $i => $url)
                                                <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                                                    <img src="{{ $url }}" class="d-block card-img-top"
                                                        alt="{{ $product->name }} - imagen {{ $i + 1 }}"
                                                        style="aspect-ratio: 4 / 3; object-fit: cover;max-height: 100%; transition: 0.3s;"
                                                        onmouseover="this.style.transform='scale(1.1)'"
                                                        onmouseout="this.style.transform='scale(1)'">
                                                </div>
                                            @endforeach
                                        </div>
                                        <button class="carousel-control-prev" type="button"
                                            data-bs-target="#gallery-{{ $product->id }}" data-bs-slide="prev">
                                            <span class="carousel-control-prev-icon bg-dark rounded-circle p-2"
                                                aria-hidden="true"></span>
                                            <span class="visually-hidden">Anterior</span>
                                        </button>
                                        <button class="carousel-control-next" type="button"
                                            data-bs-target="#gallery-{{ $product->id }}" data-bs-slide="next">
                                            <span class="carousel-control-next-icon bg-dark rounded-circle p-2"
                                                aria-hidden="true"></span>
                                            <span class="visually-hidden">Siguiente</span>
                                        </button>
                                        <div class="carousel-indicators">
                                            @foreach ($urls as $i => $url)
                                                <button type="button" data-bs-target="#gallery-{{ $product->id }}"
                                                    data-bs-slide-to="{{ $i }}"
                                                    class="{{ $i === 0 ? 'active' : '' }}"
                                                    aria-label="Imagen {{ $i + 1 }}"></button>
                                            @endforeach
                                        </div>
                                    </div>
                                @elseif (count($urls) === 1)
                                    <img src="{{ $urls[0] }}" class="card-img-top" alt="{{ $product->name }}"
                                        style="aspect-ratio: 4 / 3; object-fit: cover;max-height: 100%; transition: 0.3s;"
                                        onmouseover="this.style.transform='scale(1.1)'"
                                        onmouseout="this.style.transform='scale(1)'">
                                @else
                                    <img src="https://via.placeholder.com/400x300?text=Producto" class="card-img-top"
                                        alt="{{ $product->name }}"
                                        style="aspect-ratio: 4 / 3; object-fit: cover;max-height: 100%; transition: 0.3s;"
                                        onmouseover="this.style.transform='scale(1.1)'"
                                        onmouseout="this.style.transform='scale(1)'">
                                @endif
                            </div>
                            <div class="p-3">
                                <p class="text-red font-weight-bold mb-1" style="font-size:.75rem; letter-spacing:.05em;">
                                    {{ $product->category->name ?? 'General' }}
                                </p>
                                <h5 class="font-weight-bold mb-2 text-white">{{ $product->name }}</h5>
                                <p class="text-muted-custom small mb-2">{{ $product->description }}</p>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="small font-weight-bold text-white">Ref:
                                        {{ $product->reference ?? 'N/A' }}</span>
                                    <span
                                        class="badge {{ $product->availability === 'disponible' ? 'badge-success' : 'badge-secondary' }}"
                                        style="font-size: .7rem;">
                                        {{ $product->availability === 'disponible' ? 'Disponible' : 'No disponible' }}
                                    </span>
                                </div>

                                @auth
                                    @if (auth()->user()->isSeller())
                                        <form method="POST" action="{{ route('site.seller.add') }}" class="mt-2">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                                            <div class="input-group input-group-sm">
                                                <input type="number" name="quantity" value="1" min="1"
                                                    class="form-control bg-dark text-white border-secondary"
                                                    style="max-width: 60px" required>
                                                <div class="input-group-append">
                                                    <button type="submit" class="btn btn-red"
                                                        {{ $product->availability !== 'disponible' ? 'disabled' : '' }}>
                                                        <i class="bi bi-cart-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    @endif
                                @endauth
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="col-12 text-center py-5">
                    <p class="text-muted-custom">No se encontraron productos en esta categoría.</p>
                </div>
            @endif
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $products->appends(request()->query())->links() }}
        </div>
    </section>
@endsection"
