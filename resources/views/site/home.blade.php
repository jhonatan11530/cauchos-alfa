@extends('site.layouts.app')

@section('title', 'Cauchos Alfa | Fábrica y Distribuidor de Llantas para Motos en Cali')
@section('description', 'Fabricantes e importadores directos de cauchos, llantas y repuestos para motos en Colombia. Distribución mayorista y envíos a todo el país.')

@section('schema')
    <script type="application/ld+json">
              {
              "@@context": "https://schema.org",
              "@@type": ["AutoPartsStore", "BicycleStore"],
              "name": "Cauchos Alfa",
              "image": {!!json_encode(asset('img/hero-bike.png'))!!},
              "@@id": {!!json_encode(url('/'))!!},
              "url": {!!json_encode(url('/'))!!},
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
              {!!json_encode(url('/'))!!}
              ]
            }
            </script>
@endsection

@section('content')
    <header class="hero-section">
        <div class="container">
            <p class="text-red font-weight-bold mb-3" style="letter-spacing:.15em; font-size:.85rem;">
                CATÁLOGO DE CAUCHOS {{ date('Y') }}
            </p>
            <h1 class="hero-title">Fabricantes y Distribuidores de Cauchos para motos en Cali</h1>
            <p class="text-muted-custom lead">
                Somos fabricantes e importadores directos de cauchos para motocicletas, comprometidos con ofrecer productos
                de calidad, resistencia y durabilidad. Realizamos envíos a nivel nacional, llevando confianza y calidad a
                toda Colombia.
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
                        En Cauchos Alfa somos fabricantes de cauchos para motocicletas, con más de 40 años de experiencia en
                        el mercado. Nos caracterizamos por nuestro compromiso con la calidad, la resistencia y la
                        durabilidad de nuestros productos.
                    </p>
                    <p class="text-muted-custom">
                        Trabajamos cada día para ofrecer soluciones confiables que respondan a las necesidades de nuestros
                        clientes, respaldados por nuestra experiencia, tradición y pasión por seguir creciendo e innovando
                        en la industria.
                    </p>
                </div>
                <div class="col-lg-6">
                    <div class="p-2" style="border: 2px solid var(--bike-red); border-radius: 28px; overflow: hidden;">
                        <img src="{{ asset('img/motorcycle.png') }}"
                            alt="Venta de Cauchos y Neumáticos de Alto Rendimiento en Cali" class="img-fluid rounded"
                            loading="lazy" style="filter: grayscale(0.5); transition: 0.3s;"
                            onmouseover="this.style.filter='grayscale(0)'" onmouseout="this.style.filter='grayscale(0.5)'">
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section id="MisiónVisión" class="py-5 bg-charcoal">
        <div class="container-fluid py-5">
            <div class="row">
                <div class="col-md-12 mb-4" id="ValoresCorporativos">
                    <div class="text-center mb-5">
                        <h2 class="section-title">Valores corporativos</h2>
                        <p class="text-muted">
                            Nos caracterizamos por ofrecer productos de alta calidad, resistencia y durabilidad, respaldados
                            por más de 40 años de experiencia y conocimiento en el sector. Trabajamos con compromiso,
                            responsabilidad y honestidad, construyendo relaciones sólidas y transparentes con nuestros
                            clientes, colaboradores y proveedores. Promovemos la innovación y la mejora continua de nuestros
                            procesos y productos, fomentando el trabajo en equipo, la orientación al cliente y la búsqueda
                            constante de soluciones eficientes que generen valor, confianza y satisfacción.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 mb-4" id="Misión">
                    <div class="feature-card h-100">
                        <div class="feature-icon">
                            <i class="bi bi-flag"></i>
                        </div>
                        <h3 class="h4 font-weight-bold mb-3 text-white">Nuestra Misión</h3>
                        <p class="text-muted-custom mb-0">
                            Fabricar y comercializar productos de caucho de alta calidad para motocicletas,
                            ofreciendo soluciones confiables, duraderas y competitivas que satisfagan las necesidades de
                            nuestros clientes.
                            Con más de 40 años de experiencia en el sector, trabajamos con compromiso, responsabilidad e
                            innovación, garantizando procesos eficientes, excelencia en nuestros productos y relaciones
                            comerciales basadas en la confianza, contribuyendo al desarrollo de la industria y al bienestar
                            de nuestros colaboradores, clientes y aliados.
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
                            Ser una empresa líder y referente en la fabricación de productos de caucho para motocicletas en
                            Colombia y Latinoamérica, reconocida por la calidad de nuestros productos, la
                            innovación, el cumplimiento y la confianza construida durante más de cuatro décadas de
                            trayectoria.
                            Proyectamos un crecimiento sostenible, fortaleciendo nuestra capacidad productiva, ampliando
                            nuestros mercados y evolucionando continuamente para responder a las necesidades de nuestros
                            clientes, manteniendo siempre la esencia, los valores y la experiencia que nos han distinguido a
                            lo largo de nuestra historia.
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
        </div>
        <div class="container-fluid">
            <div class="row">
                @if (count($featured) > 0)
                    @foreach ($featured as $product)
                        <div class="col-lg-3 col-md-2 mb-4">
                            <div class="product-card h-100">
                                <div class="bg-light d-flex align-items-center justify-content-center" style="height: 250px;">
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
                                                            alt="{{ $product->name }} - imagen {{ $i + 1 }}" loading="lazy"
                                                            style="aspect-ratio: 4 / 3; object-fit: cover;">
                                                    </div>
                                                @endforeach
                                            </div>
                                            <button class="carousel-control-prev" type="button"
                                                data-target="#gallery-{{ $product->id }}" data-slide="prev">
                                                <span class="bg-dark rounded-circle d-flex align-items-center justify-content-center"
                                                    style="width: 35px; height: 35px; opacity: 0.8;">
                                                    <span class="carousel-control-prev-icon" style="width: 15px; height: 15px;"
                                                        aria-hidden="true"></span>
                                                </span>
                                                <span class="sr-only">Anterior</span>
                                            </button>
                                            <button class="carousel-control-next" type="button"
                                                data-target="#gallery-{{ $product->id }}" data-slide="next">
                                                <span class="bg-dark rounded-circle d-flex align-items-center justify-content-center"
                                                    style="width: 35px; height: 35px; opacity: 0.8;">
                                                    <span class="carousel-control-next-icon" style="width: 15px; height: 15px;"
                                                        aria-hidden="true"></span>
                                                </span>
                                                <span class="sr-only">Siguiente</span>
                                            </button>
                                            <ol class="carousel-indicators">
                                                @foreach ($urls as $i => $url)
                                                    <li data-target="#gallery-{{ $product->id }}" data-slide-to="{{ $i }}"
                                                        class="{{ $i === 0 ? 'active' : '' }}" aria-label="Imagen {{ $i + 1 }}"></li>
                                                @endforeach
                                            </ol>
                                        </div>
                                    @elseif (count($urls) === 1)
                                        <img src="{{ $urls[0] }}" class="card-img-top w-100" alt="{{ $product->name }}" loading="lazy"
                                            style="aspect-ratio: 4 / 3; object-fit: cover;">
                                    @else
                                        <svg class="card-img-top w-100" style="aspect-ratio: 4 / 3; background-color: #2b3035;"
                                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">
                                            <rect width="100%" height="100%" fill="#2b3035" />
                                            <text x="50%" y="50%" fill="#6c757d" font-family="sans-serif" font-size="18"
                                                text-anchor="middle" dominant-baseline="middle">Sin Imagen</text>
                                        </svg>
                                    @endif
                                </div>
                                <div class="p-4">
                                    <p class="text-red font-weight-bold mb-2" style="font-size:.8rem; letter-spacing:.1em;">
                                        {{ $product->category->name ?? 'General' }}
                                    </p>
                                    <h3 class="h4 font-weight-bold mb-2">{{ $product->name }}</h3>
                                    <p class="text-muted-custom mb-4">
                                        {{ \Illuminate\Support\Str::limit($product->description, 100) }}
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="h4 font-weight-bold mb-0">Ref
                                            #{{ $product->reference ?? 'N/A' }}</span>
                                        <a href="{{ route('site.product', ['slug' => \Illuminate\Support\Str::slug($product->name), 'id' => $product->id]) }}"
                                            class="btn btn-sm btn-red">Ver detalles</a>
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
        </div>
    </section>
    <section id="contacto" class="cta-section py-5">
        <div class="container py-5 text-center">
            <h2 class="section-title mb-3">¿Necesitas asesoría?</h2>
            <p class="section-subtitle mb-4">
                Contacta con nuestros expertos para encontrar el neumático ideal para tu vehículo.
            </p>
            <div class="d-flex flex-wrap justify-content-center btn-group-hero">
                <a href="tel:{{ config('app.phone') }}" class="btn btn-red btn-lg">Contactar ahora</a>
            </div>
        </div>
    </section>
@endsection
