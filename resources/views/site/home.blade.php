@extends('site.layouts.app')

@section('title', 'Cauchos Alfa | Neumáticos de alto rendimiento')
@section('description', 'Descubre la mejor selección de cauchos y neumáticos para todo tipo de vehículos. Calidad, durabilidad y rendimiento.')

@section('schema')
    <script type="application/ld+json">
        {
          "@@context": "https://schema.org",
          "@@type": "AutoPartsStore",
          "name": "Cauchos Alfa",
          "image": {!! json_encode(asset('img/hero-bike.png')) !!},
          "@@id": {!! json_encode(url('/')) !!},
          "url": {!! json_encode(url('/')) !!},
          "telephone": "+573000000000",
          "address": {
            "@@type": "PostalAddress",
            "streetAddress": "Calle Principal",
            "addressLocality": "Cali",
            "addressRegion": "DC",
            "postalCode": "11001",
            "addressCountry": "CO"
          },
          "geo": {
            "@@type": "GeoCoordinates",
            "latitude": 4.60971,
            "longitude": -74.08175
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
                CATÁLAGO ACTUALIZADO 2026
            </p>
            <h1 class="hero-title">Neumáticos diseñados para<br>el camino extraordinario.</h1>
            <p class="hero-subtitle">
                Venta de cauchos y neumáticos de alto rendimiento en Cali. La máxima adherencia, seguridad y durabilidad para quienes entienden que la calidad es la base de cada viaje.
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

            </div>
    </section>
    <section id="contacto" class="cta-section py-5">
        <div class="container py-5 text-center">
            <h2 class="section-title mb-3">¿Necesitas asesoría?</h2>
            <p class="section-subtitle mb-4">
                Contacta con nuestros expertos para encontrar el neumático ideal para tu vehículo.
            </p>
            <div class="d-flex flex-wrap justify-content-center btn-group-hero">
                <a href="{{config("whatsapp.phone")}}" class="btn btn-red btn-lg" target="_blank">Contactar ahora</a>
            </div>
        </div>
    </section>
@endsection
