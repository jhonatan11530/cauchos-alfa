@extends('site.layouts.app')

@section('title', 'Catálogo Mayorista de Repuestos y Llantas para Bicicletas | Cauchos Alfa')
@section('title', 'Catálogo Mayorista | Cauchos para Motos y Bicicletas | Cauchos Alfa')

@section('schema')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "CollectionPage",
  "name": "Catálogo de Repuestos y Llantas para Bicicletas - Cauchos Alfa",
  "description": "Explora nuestro catálogo completo de llantas, cauchos y repuestos para bicicletas en Cali.",
  "name": "Catálogo de Cauchos para Motos y Bicicletas - Cauchos Alfa",
  "description": "Explora nuestro catálogo de fábrica e importación directa de cauchos, llantas para motos, repuestos de bicicletas y bicicletas en Cali.",
  "url": {!! json_encode(url()->current()) !!},
  "mainEntity": {
    "@@type": "ItemList",
    "itemListElement": [
      @foreach($products as $index => $product)
      {
        "@@type": "ListItem",
        "position": {{ $index + 1 }},
        "item": {
          "@@type": "Product",
          "name": {!! json_encode($product->name) !!},
          "image": {!! json_encode(count($product->imageUrls()) > 0 ? $product->imageUrls()[0] : '') !!}
        }
      }@if(!$loop->last),@endif
      @endforeach
    ]
  }
}
</script>
@endsection

@section('content')
    <section class="bg-void text-white py-5 text-center" style="margin-top: 70px;">
        <div class="container">
            <h1 class="section-title">Catálogo de Cauchos y Neumáticos</h1>
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
            @if (count($products) > 0)
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
                                    <div id="gallery-{{ $product->id }}" class="carousel slide carousel-fade w-100"
                                        data-ride="carousel">
                                        <div class="carousel-inner">
                                            @foreach ($urls as $i => $url)
                                                <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                                                    <img src="{{$url }}" class="d-block w-100 card-img-top"
                                                        alt="{{ $product->name }} - imagen {{ $i + 1 }}"
                                                        style="aspect-ratio: 4 / 3; object-fit: cover;max-height: 100%; transition: 0.3s;"
                                                        onmouseover="this.style.transform='scale(1.1)'"
                                                        onmouseout="this.style.transform='scale(1)'">
                                                </div>
                                            @endforeach
                                        </div>
                                        <button class="carousel-control-prev" type="button"
                                            data-target="#gallery-{{ $product->id }}" data-slide="prev">
                                            <span class="bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; opacity: 0.8;">
                                                <span class="carousel-control-prev-icon" style="width: 15px; height: 15px;" aria-hidden="true"></span>
                                            </span>
                                            <span class="sr-only">Anterior</span>
                                        </button>
                                        <button class="carousel-control-next" type="button"
                                            data-target="#gallery-{{ $product->id }}" data-slide="next">
                                            <span class="bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; opacity: 0.8;">
                                                <span class="carousel-control-next-icon" style="width: 15px; height: 15px;" aria-hidden="true"></span>
                                            </span>
                                            <span class="sr-only">Siguiente</span>
                                        </button>
                                        <ol class="carousel-indicators">
                                            @foreach ($urls as $i => $url)
                                                <li data-target="#gallery-{{ $product->id }}"
                                                    data-slide-to="{{ $i }}"
                                                    class="{{ $i === 0 ? 'active' : '' }}"
                                                    aria-label="Imagen {{ $i + 1 }}"></li>
                                            @endforeach
                                        </ol>
                                    </div>
                                @elseif (count($urls) === 1)
                                    <img src="{{ $urls[0] }}" class="card-img-top w-100" alt="{{ $product->name }}"
                                        style="aspect-ratio: 4 / 3; object-fit: cover;max-height: 100%; transition: 0.3s;"
                                        onmouseover="this.style.transform='scale(1.1)'"
                                        onmouseout="this.style.transform='scale(1)'">
                                @else
                                    <svg class="card-img-top w-100" style="aspect-ratio: 4 / 3; background-color: #2b3035;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">
                                        <rect width="100%" height="100%" fill="#2b3035"/>
                                        <text x="50%" y="50%" fill="#6c757d" font-family="sans-serif" font-size="18" text-anchor="middle" dominant-baseline="middle">Sin Imagen</text>
                                    </svg>
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

        <!-- SEO Text Block -->
        <div class="row mt-5 pt-4 border-top">
            <div class="col-12">
                <h3 class="h5 font-weight-bold text-dark">Llantas, Repuestos y Accesorios para Bicicletas en Colombia</h3>
                <h3 class="h5 font-weight-bold text-dark">Fabricantes e Importadores de Cauchos para Motos y Bicicletas</h3>
                <p class="text-muted" style="font-size: 0.9rem;">
                    En Cauchos Alfa nos especializamos en la distribución mayorista y venta al detal de <strong>repuestos para bicicletas en Cali</strong> y envíos a toda Colombia. Contamos con un amplio inventario de <strong>llantas tubeless, neumáticos MTB 29, llantas para ruta y gravel</strong>, así como <strong>repuestos Shimano originales</strong>, cadenas, pastillas de freno y componentes de transmisión. Ya sea que busques mejorar el rendimiento de tus ruedas, hacer conversión a tubeless, o abastecer tu tienda, tenemos la calidad y durabilidad que necesitas.
                    En <strong>Cauchos Alfa</strong> somos fabricantes directos e importadores especializados en <strong>cauchos y llantas para motos</strong> y <strong>bicicletas</strong> en Cali, Colombia. Nuestro catálogo incluye desde llantas tubeless y neumáticos para bicicletas de ruta y MTB, hasta llantas de alto rendimiento y durabilidad extrema para motocicletas de trabajo y calle. Además, somos distribuidores mayoristas de bicicletas completas y repuestos. Compra con la confianza de fábrica y mejora la adherencia y seguridad de tu vehículo.
                </p>
            </div>
        </div>
    </section>
@endsection
