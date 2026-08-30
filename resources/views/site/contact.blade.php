@extends('site.layout')

@section('title', 'Contacto | Cauchos Alfa')

@section('contenido')
    <section class="bg-dark text-white py-5 text-center">
        <div class="container">
            <h1 class="fw-bold">Contáctanos</h1>
            <p class="lead mb-0">Estamos listos para atenderte.</p>
        </div>
    </section>

    <section class="container py-5">
        <div class="row g-5">
            <div class="col-lg-6">
                <h3 class="fw-bold mb-4">Envíanos un mensaje</h3>
                <form method="POST" action="{{ route('site.contact.send') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="name" value="{{ old('name') }}"
                            class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo electrónico *</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                            class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mensaje *</label>
                        <textarea name="message" rows="5" class="form-control @error('message') is-invalid @enderror" required>{{ old('message') }}</textarea>
                        @error('message')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-brand px-4">Enviar mensaje</button>
                </form>
            </div>
            <div class="col-lg-6">
                <h3 class="fw-bold mb-4">Información de contacto</h3>
                <ul class="list-unstyled fs-5">
                    <li class="mb-3"><i class="bi bi-geo-alt text-danger me-2"></i>Calle Principal #00-00</li>
                    <li class="mb-3"><i class="bi bi-telephone text-danger me-2"></i>(000) 000 0000</li>
                    <li class="mb-3"><i class="bi bi-envelope text-danger me-2"></i>contacto@cauchosalfa.com</li>
                    <li class="mb-3"><i class="bi bi-clock text-danger me-2"></i>Lunes a sábado, 8:00 a.m. - 6:00 p.m.
                    </li>
                </ul>
                <h5 class="fw-bold mt-4">Síguenos</h5>
                <a href="#" class="text-decoration-none text-dark me-3"><i class="bi bi-facebook fs-3"></i></a>
                <a href="#" class="text-decoration-none text-dark me-3"><i class="bi bi-instagram fs-3"></i></a>
                <a href="#" class="text-decoration-none text-dark"><i class="bi bi-whatsapp fs-3"></i></a>
            </div>
        </div>
    </section>
@endsection
