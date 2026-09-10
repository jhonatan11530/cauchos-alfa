@extends('site.layouts.app')

@section('title', 'Cauchos Alfa | Neumáticos de alto rendimiento')
@section('description',
    'Descubre la mejor selección de cauchos y neumáticos para todo tipo de vehículos. Calidad,
    durabilidad y rendimiento.')

@section('content')
    <header class="hero-section">
        <div class="container">
            <p class="text-red font-weight-bold mb-3" style="letter-spacing:.15em; font-size:.85rem;">
                CATÁLAGO ACTUALIZADO 2026
            </p>
            <h1 class="hero-title">Diseñados para<br>el camino extraordinario.</h1>
            <p class="hero-subtitle">
                La máxima adherencia, seguridad y durabilidad. Pensada para quienes entienden que la calidad
                en los neumáticos es la base de cada viaje.
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
                        <img src="{{ asset('img/bike-mountain.png') }}" class="img-fluid rounded"
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
                <h2 class="section-title">Productos Destacados</h2>
                <p class="section-subtitle">
                    Nuestra selección de los neumáticos más solicitados por rendimiento y durabilidad.
                </p>
            </div>
            <div class="row">
                @if (count($featured) > 1)
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
                                        <div id="gallery-{{ $product->id }}" class="carousel slide carousel-fade"
                                            data-bs-ride="carousel">
                                            <div class="carousel-inner">
                                                @foreach ($urls as $i => $url)
                                                    <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                                                        <img src="{{ $url }}" class="d-block card-img-top"
                                                            alt="{{ $product->name }} - imagen {{ $i + 1 }}"
                                                            style="aspect-ratio: 4 / 3; object-fit: cover;">
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
                                            style="aspect-ratio: 4 / 3; object-fit: cover;">
                                    @else
                                        <img src="https://via.placeholder.com/400x300?text=Producto" class="card-img-top"
                                            alt="{{ $product->name }}" style="aspect-ratio: 4 / 3; object-fit: cover;">
                                    @endif
                                </div>
                                <div class="p-4">
                                    <p class="text-red font-weight-bold mb-2" style="font-size:.8rem; letter-spacing:.1em;">
                                        {{ $product->category->name ?? 'General' }}
                                    </p>
                                    <h3 class="h4 font-weight-bold mb-2">{{ $product->name }}</h3>
                                    <p class="text-muted-custom mb-4">
                                        {{ \Illuminate\Support\Str::limit($product->description, 100) }}</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span
                                            class="h4 font-weight-bold mb-0">{{ $product->reference ?? 'Ref: N/A' }}</span>
                                        <a href="{{ route('site.catalog') }}" class="btn btn-sm btn-red">Ver más</a>
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
                <a href="#" class="btn btn-red btn-lg">Contactar ahora</a>
            </div>
        </div>
    </section>
@endsection
