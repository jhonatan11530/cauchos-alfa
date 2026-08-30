@extends('site.layout')

@section('title', 'Inicio | Cauchos Alfa')

@section('contenido')
    {{-- HERO / Inicio --}}
    <section class="hero" id="inicio">
        <div class="container">
            <h1 class="display-4 fw-bold">Bienvenidos a Cauchos Alfa</h1>
            <p class="lead mb-4">Tu mejor opciÃ³n en llantas y servicios automotrices.</p>
            <a href="{{ route('site.catalog') }}" class="btn btn-brand btn-lg px-4">Ver catÃ¡logo</a>
        </div>
    </section>

    {{-- Productos destacados --}}
    <section class="container py-5">
        <h2 class="text-center fw-bold mb-4">Productos destacados</h2>
        <div class="row g-4">
            @forelse ($featured as $product)
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card card-product h-100 shadow-sm">
                        @if ($product->image_path)
                            <img src="{{ asset('storage/' . $product->image_path) }}" class="card-img-top"
                                alt="{{ $product->name }}">
                        @else
                            <img src="https://via.placeholder.com/400x200?text=Producto" class="card-img-top"
                                alt="{{ $product->name }}">
                        @endif
                        <div class="card-body">
                            <h5 class="card-title">{{ $product->name }}</h5>
                            <p class="card-text text-muted small">
                                {{ \Illuminate\Support\Str::limit($product->description, 90) }}</p>
                        </div>
                        <div class="card-footer bg-white border-0 text-center">
                            <span class="badge bg-dark">{{ $product->category->name ?? "General" }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-center text-muted">Pronto publicaremos nuestros productos.</p>
            @endforelse
        </div>
    </section>

    {{-- DescripciÃ³n --}}
    <section id="descripcion" class="bg-light py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h2 class="fw-bold">Â¿QuiÃ©nes somos?</h2>
                    <p class="text-muted">Cauchos Alfa es una empresa dedicada a la venta de llantas, cauchos y accesorios
                        para vehÃ­culos de toda clase. Contamos con personal calificado y productos de las mejores marcas
                        para garantizar tu seguridad en la vÃ­a.</p>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Amplio catÃ¡logo de
                            productos</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>AsesorÃ­a especializada
                        </li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-danger me-2"></i>Servicio post-venta</li>
                    </ul>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="bi bi-truck text-danger" style="font-size: 9rem;"></i>
                </div>
            </div>
        </div>
    </section>

    {{-- MisiÃ³n / VisiÃ³n --}}
    <section class="container py-5 mission-vision">
        <div class="row g-4">
            <div class="col-md-6" id="mision">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-bullseye text-danger display-5"></i>
                        <h3 class="fw-bold mt-3">MisiÃ³n</h3>
                        <p class="text-muted">Proveer a nuestros clientes llantas y servicios automotrices de la mÃ¡s alta
                            calidad, con asesorÃ­a personalizada, precios justos y un servicio oportuno que garantice su
                            seguridad y satisfacciÃ³n.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6" id="vision">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-eye text-danger display-5"></i>
                        <h3 class="fw-bold mt-3">VisiÃ³n</h3>
                        <p class="text-muted">Ser la empresa lÃ­der y referente en el sector de llantas y servicios
                            automotrices de la regiÃ³n, reconocida por su confiabilidad, innovaciÃ³n y excelencia en la
                            atenciÃ³n al cliente.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
