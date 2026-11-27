@extends('site.layouts.app')

@section('title', 'Catálogo Mayorista | Cauchos para Motos y Bicicletas | Cauchos Alfa')

@section('schema')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "CollectionPage",
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
            @auth
                @if (auth()->user()->isSeller())
                    <div class="mt-3">
                        <a href="{{ route('site.seller.catalog.latest') }}" class="btn btn-red">
                            <i class="bi bi-file-earmark-pdf"></i> Descargar último catálogo (PDF)
                        </a>
                    </div>
                @endif
            @endauth
        </div>
    </section>

    <section class="container py-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
            <div class="mb-2 mb-md-0">
                <h3 class="font-weight-bold text-white mb-1">Categorías</h3>
                <p class="text-muted-custom small mb-0">Filtra por el tipo de neumático o repuesto que necesitas</p>
            </div>
            <div style="min-width: 260px; max-width: 360px; width: 100%;">
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text border-secondary text-muted-custom" style="background: var(--bike-charcoal);">
                            <i class="bi bi-search"></i>
                        </span>
                    </div>
                    <input type="text"
                        id="categorySearchInput"
                        class="form-control text-white border-secondary"
                        style="background: var(--bike-charcoal);"
                        placeholder="Buscar categoría..."
                        aria-label="Buscar categoría"
                        autocomplete="off">
                    <div class="input-group-append d-none" id="clearCategorySearchWrap">
                        <button type="button" id="clearCategorySearchBtn" class="btn btn-outline-secondary text-white" title="Limpiar búsqueda">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="categoryPillsContainer" class="d-flex flex-nowrap flex-md-wrap overflow-auto pb-2 mb-4" style="gap: 8px;">
            <a href="{{ route('site.catalog') }}"
                class="btn btn-sm d-inline-flex align-items-center category-pill-all {{ !$selectedCategory ? 'border-danger text-white' : 'text-white-50' }}"
                style="border-radius: 20px; white-space: nowrap; padding: 6px 14px; transition: 0.2s; {{ !$selectedCategory ? 'background: var(--bike-red); border: 1px solid var(--bike-red); font-weight: 600;' : 'background: var(--bike-charcoal); border: 1px solid rgba(255,255,255,0.15);' }}">
                <i class="bi bi-grid-3x3-gap-fill mr-1"></i> Todos
            </a>
            @foreach ($categories as $category)
                @php
                    $isActiveCat = $selectedCategory && $selectedCategory->id == $category->id;
                @endphp
                <a href="{{ route('site.catalog.category', ['slug' => \Illuminate\Support\Str::slug($category->name), 'categoriaId' => $category->id]) }}"
                    class="btn btn-sm d-inline-flex align-items-center category-pill-item {{ $isActiveCat ? 'border-danger text-white' : 'text-white-50' }}"
                    data-category-name="{{ mb_strtolower($category->name) }}"
                    style="border-radius: 20px; white-space: nowrap; padding: 6px 14px; transition: 0.2s; {{ $isActiveCat ? 'background: var(--bike-red); border: 1px solid var(--bike-red); font-weight: 600;' : 'background: var(--bike-charcoal); border: 1px solid rgba(255,255,255,0.15);' }}">
                    <span>{{ $category->name }}</span>
                    <span class="badge ml-2" style="background: rgba(0,0,0,0.28); color: #fff; font-weight: 500;">{{ $category->products_count }}</span>
                </a>
            @endforeach
        </div>
        <p id="noCategoriesMatch" class="text-muted-custom small mb-4 d-none">No se encontraron categorías con ese nombre.</p>

        <div id="productos-catalogo" class="d-flex justify-content-between align-items-end mb-4">
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
                                                        loading="lazy"
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
                                        loading="lazy"
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
                                <h5 class="font-weight-bold mb-2">
                                    <a href="{{ route('site.product', ['slug' => \Illuminate\Support\Str::slug($product->name), 'id' => $product->id]) }}" class="text-white text-decoration-none">
                                        {{ $product->name }}
                                    </a>
                                </h5>
                                <p class="text-muted-custom small mb-2">{{ \Illuminate\Support\Str::limit($product->description, 60) }}</p>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="small font-weight-bold text-white">Ref:
                                        {{ $product->reference ?? 'N/A' }}</span>
                                    <span
                                        class="badge {{ $product->availability === 'disponible' ? 'badge-success' : 'badge-secondary' }}"
                                        style="font-size: .7rem;">
                                        {{ $product->availability === 'disponible' ? 'Disponible' : 'No disponible' }}
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <a href="{{ route('site.product', ['slug' => \Illuminate\Support\Str::slug($product->name), 'id' => $product->id]) }}" class="btn btn-sm btn-outline-danger w-100" style="border-radius: 20px;">Ver más detalles</a>
                                </div>

                                @auth
                                    @if (auth()->user()->isSeller())
                                        <form method="POST" action="{{ route('site.seller.add') }}" id="form-add-to-cart-{{ $product->id }}" class="mt-2 form-seller-add-to-cart">
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
                <h5>Fabricantes e Importadores de Cauchos para Motocicletas</h5>
                <p class="text-muted" style="font-size: 0.9rem;">
                En <strong>Cauchos Alfa</strong> nos especializamos en la <strong>fabricación de cauchos para motocicletas</strong>, así como en la importación y distribución mayorista y venta al detal de repuestos para motos en Cali, con envíos a toda Colombia. Contamos con un amplio portafolio de productos de caucho compatibles con diferentes marcas y modelos de motocicletas, diseñados para ofrecer resistencia, durabilidad y un óptimo rendimiento.</p>
                <p class="text-muted">Nuestro catálogo incluye cadenas, pastillas de freno, componentes de transmisión y una amplia variedad de repuestos para motocicletas, ofreciendo soluciones confiables para talleres, distribuidores, almacenes de repuestos y clientes particulares.</p>
                <p class="text-muted">En <strong>Cauchos Alfa</strong> trabajamos para satisfacer las necesidades del sector motociclista, ofreciendo productos de calidad, precios competitivos y soluciones especializadas que contribuyen al rendimiento, la seguridad y la durabilidad de las motocicletas.
                </p>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    const normalizeText = (text) => (text || '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const $categorySearch = $('#categorySearchInput');
    const $clearWrap = $('#clearCategorySearchWrap');
    const $categoryPills = $('.category-pill-item');
    const $noCategoriesMatch = $('#noCategoriesMatch');

    function filterCategories() {
        const term = normalizeText($categorySearch.val());
        $clearWrap.toggleClass('d-none', term.length === 0);

        let visibleCount = 0;
        $categoryPills.each(function () {
            const name = normalizeText($(this).attr('data-category-name'));
            const matches = term === '' || name.includes(term);
            $(this).toggleClass('d-none', !matches).toggleClass('d-inline-flex', matches);
            if (matches) {
                visibleCount++;
            }
        });

        $noCategoriesMatch.toggleClass('d-none', visibleCount > 0 || term === '');
    }

    $categorySearch.on('input', filterCategories);
    $('#clearCategorySearchBtn').on('click', function () {
        $categorySearch.val('').trigger('input').focus();
    });

    const productosSection = document.getElementById('productos-catalogo');
    if (productosSection) {
        const navbar = document.querySelector('nav.navbar, .navbar');
        const offset = (navbar ? navbar.offsetHeight : 70) + 16;
        const targetTop = productosSection.getBoundingClientRect().top + window.pageYOffset - offset;
        window.scrollTo({
            top: Math.max(0, targetTop),
            behavior: 'smooth'
        });
    }

    $('form.form-seller-add-to-cart').each(function () {
        $(this).validate({
            rules: {
                quantity: {
                    required: true,
                    digits: true,
                    min: 1
                }
            },
            messages: {
                quantity: {
                    required: 'Ingresa la cantidad.',
                    digits: 'Solo se permiten números enteros.',
                    min: 'La cantidad mínima debe ser al menos {0}.'
                }
            },
            errorElement: 'span',
            errorClass: 'invalid-feedback d-block',
            highlight: function (element) {
                $(element).addClass('is-invalid').removeClass('is-valid');
            },
            unhighlight: function (element) {
                $(element).removeClass('is-invalid');
            },
            errorPlacement: function (error, element) {
                if (element.closest('.input-group').length) {
                    error.insertAfter(element.closest('.input-group'));
                } else {
                    error.insertAfter(element);
                }
            },
            submitHandler: function (form) {
                form.submit();
            }
        });
    });
});
</script>
@endpush

