@extends('site.layouts.app')

@section('title', 'Cauchos Alfa | Mayorista de Repuestos y Llantas para Bicicletas en Cali')
@section('description', 'Distribuidor mayorista de cauchos, llantas y repuestos de bicicletas en Cali. Encuentra la mejor calidad, rendimiento y marcas líderes para tu negocio o bicicleta.')
@section('title', 'Cauchos Alfa | Fábrica y Distribuidor de Llantas para Motos y Bicicletas')
@section('description', 'Fabricantes e importadores directos de cauchos y llantas para motos y bicicletas en Colombia. Venta de bicicletas y distribución mayorista en Cali.')

@section('schema')
    <script type="application/ld+json">
        {
          "@@context": "https://schema.org",
          "@@type": "BicycleStore",
          "@@type": "AutoPartsStore",
          "name": "Cauchos Alfa",
          "image": {!! json_encode(asset('img/hero-bike.png')) !!},
          "@@id": {!! json_encode(url('/')) !!},
          "url": {!! json_encode(url('/')) !!},
          "telephone": "+573128416915",
          "address": {
            "@@type": "PostalAddress",
            "streetAddress": "Cr28 A 1-72 Y 1 El Poblado II",
            "addressLocality": "Cali",
            "addressRegion": "Valle del Cauca",
            "postalCode": "760001",
            "addressCountry": "CO"
          },
          "geo": {
            "@@type": "GeoCoordinates",
            "latitude": 3.42158,
            "longitude": -76.5205
          },
          "sameAs": [
            {!! json_encode(url('/')) !!}
          ]
        }
    </script>
@endsection

@section('content')
    <header class="hero-section">
        <div class="container">
            <p class="text-red font-weight-bold mb-3" style="letter-spacing:.15em; font-size:.85rem;">
                CATÁLOGO DE REPUESTOS Y LLANTAS 2026
                CATÁLOGO DE CAUCHOS Y LLANTAS 2026
            </p>
            <h1 class="hero-title">Llantas y Repuestos para Bicicletas en Cali.</h1>
            <h1 class="hero-title">Fabricantes de Cauchos para Motos y Bicicletas.</h1>
            <p class="hero-subtitle">
                Distribuidores mayoristas e importadores de llantas tubeless, neumáticos MTB 29, repuestos Shimano originales, frenos, cadenas y corazas de alto rendimiento en Colombia. La máxima adherencia y seguridad para ciclistas exigentes y tiendas especializadas.
                Somos fabricantes e importadores directos de cauchos, llantas y neumáticos para motos y bicicletas. Distribuidores mayoristas y venta de bicicletas completas en Cali con envíos a toda Colombia.
            </p>
            <div class="d-flex flex-wrap justify-content-center btn-group-hero">
                <a href="{{ route('site.catalog') }}" class="btn btn-red btn-lg">Ver Catálogo</a>
                <a href="#productos-destacados" class="btn btn-outline-red btn-lg">Productos Destacados</a>
            </div>
        </div>
    </header>
    <section id="Descripción" class="py-5 bg-void">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h2 class="section-title text-left">Sobre Cauchos Alfa</h2>
                    <p class="text-muted-custom lead">
                        Somos líderes en la distribución de neumáticos de alta gama, comprometidos con la seguridad y el
                        rendimiento en cada kilómetro.
                        Nuestra pasión por la excelencia nos ha llevado a seleccionar solo las mejores marcas y tecnologías
                        del mercado mundial.
                    </p>
                    <p class="text-muted-custom">
                        Desde vehículos compactos hasta maquinaria pesada, ofrecemos soluciones a medida que garantizan la
                        máxima adherencia y durabilidad,
                        asegurando que cada viaje sea una experiencia de confianza y control total sobre el asfalto.
                    </p>
                </div>
                <div class="col-lg-6">
                    <div class="p-2" style="border: 2px solid var(--bike-red); border-radius: 28px; overflow: hidden;">
                        <img src="{{ asset('img/bike-mountain.png') }}" alt="Venta de Cauchos y Neumáticos de Alto Rendimiento en Cali" class="img-fluid rounded"
                            style="filter: grayscale(0.5); transition: 0.3s;" onmouseover="this.style.filter='grayscale(0)'"
                            onmouseout="this.style.filter='grayscale(0.5)'">
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section id="MisiónVisión" class="py-5 bg-charcoal">
        <div class="container py-5">
            <div class="row">
                <div class="col-md-6 mb-4" id="Misión">
                    <div class="feature-card h-100">
                        <div class="feature-icon">
                            <i class="bi bi-flag"></i>
                        </div>
                        <h3 class="h4 font-weight-bold mb-3 text-white">Nuestra Misión</h3>
                        <p class="text-muted-custom mb-0">
                            Proporcionar a nuestros clientes los neumáticos de más alta calidad, combinando asesoría experta
                            y un servicio excepcional
                            para garantizar la seguridad y el desempeño óptimo de sus vehículos en cualquier terreno.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 mb-4" id="Visión">
                    <div class="feature-card h-100">
                        <div class="feature-icon">
                            <i class="bi bi-eye"></i>
                        </div>
                        <h3 class="h4 font-weight-bold mb-3 text-white">Nuestra Visión</h3>
                        <p class="text-muted-custom mb-0">
                            Consolidarnos como la empresa referente en el sector de neumáticos a nivel nacional, siendo
                            reconocidos por nuestra
                            innovación constante, integridad y la capacidad de superar las expectativas de nuestros clientes
                            más exigentes.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section id="productos-destacados" class="py-5 bg-charcoal">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="section-title">Cauchos y Llantas Destacadas</h2>
                <p class="section-subtitle">
                    Nuestra selección de los neumáticos más solicitados por rendimiento y durabilidad.
                </p>
            </div>
            <div class="row">
                @if (count($featured) > 0)
                    @foreach ($featured as $product)
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="product-card h-100">
                                <div class="bg-light d-flex align-items-center justify-content-center"
                                    style="height: 250px;">
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
                                                        <img src="{{ $url }}" class="d-block w-100 card-img-top"
                                                            alt="{{ $product->name }} - imagen {{ $i + 1 }}"
                                                            style="aspect-ratio: 4 / 3; object-fit: cover;">
                                                    </div>
                                                @endforeach
                                            </div>
                                            <button class="carousel-control-prev" type="button"
                                                data-target="#gallery-{{ $product->id }}" data-slide="prev">
                                                <span
                                                    class="bg-dark rounded-circle d-flex align-items-center justify-content-center"
                                                    style="width: 35px; height: 35px; opacity: 0.8;">
                                                    <span class="carousel-control-prev-icon"
                                                        style="width: 15px; height: 15px;" aria-hidden="true"></span>
                                                </span>
                                                <span class="sr-only">Anterior</span>
                                            </button>
                                            <button class="carousel-control-next" type="button"
                                                data-target="#gallery-{{ $product->id }}" data-slide="next">
                                                <span
                                                    class="bg-dark rounded-circle d-flex align-items-center justify-content-center"
                                                    style="width: 35px; height: 35px; opacity: 0.8;">
                                                    <span class="carousel-control-next-icon"
                                                        style="width: 15px; height: 15px;" aria-hidden="true"></span>
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
                                        <img src="{{ $urls[0] }}" class="card-img-top w-100"
                                            alt="{{ $product->name }}" style="aspect-ratio: 4 / 3; object-fit: cover;">
                                    @else
                                        <svg class="card-img-top w-100"
                                            style="aspect-ratio: 4 / 3; background-color: #2b3035;"
                                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">
                                            <rect width="100%" height="100%" fill="#2b3035" />
                                            <text x="50%" y="50%" fill="#6c757d" font-family="sans-serif" font-size="18"
                                                text-anchor="middle" dominant-baseline="middle">Sin Imagen</text>
                                        </svg>
                                    @endif
                                </div>
                                <div class="p-4">
                                    <p class="text-red font-weight-bold mb-2"
                                        style="font-size:.8rem; letter-spacing:.1em;">
                                        {{ $product->category->name ?? 'General' }}
                                    </p>
                                    <h3 class="h4 font-weight-bold mb-2">{{ $product->name }}</h3>
                                    <p class="text-muted-custom mb-4">
                                        {{ \Illuminate\Support\Str::limit($product->description, 100) }}
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="h4 font-weight-bold mb-0">Ref
                                            #{{ $product->reference ?? 'N/A' }}</span>
                                        <a href="{{ route('site.product', ['slug' => \Illuminate\Support\Str::slug($product->name), 'id' => $product->id]) }}" class="btn btn-sm btn-red">Ver detalles</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="col-12 text-center py-5">
                        <p class="text-center text-muted">No hay productos en esta categoría por el momento.</p>
                    </div>
                @endif
            </div>
    </section>
    <section id="contacto" class="cta-section py-5">
        <div class="container py-5 text-center">
            <h2 class="section-title mb-3">¿Necesitas asesoría?</h2>
            <p class="section-subtitle mb-4">
                Contacta con nuestros expertos para encontrar el neumático ideal para tu vehículo.
            </p>
            <div class="d-flex flex-wrap justify-content-center btn-group-hero">
                <a href="{{config("whatsapp.phone")}}?text={{ urlencode('Hola Cauchos Alfa, estoy interesado en adquirir productos. ¿Me podrían dar más información?') }}" class="btn btn-red btn-lg" target="_blank">Contactar ahora</a>
            </div>
        </div>
    </section>
@endsection
