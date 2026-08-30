@extends('site.layout')

@section('title', 'Acceso vendedor | Cauchos Alfa')

@section('contenido')
    <section class="bg-dark text-white py-5 text-center">
        <div class="container">
            <h1 class="fw-bold">Acceso para vendedores</h1>
            <p class="lead mb-0">Ingresa tu código de vendedor para crear pedidos.</p>
        </div>
    </section>

    <section class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h4 class="fw-bold text-center mb-4"><i class="bi bi-person-badge text-danger"></i> Código de
                            vendedor</h4>
                        @if ($errors->any())
                            <div class="alert alert-danger">{{ $errors->first() }}</div>
                        @endif
                        <form method="POST" action="{{ route('site.seller.auth') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Código *</label>
                                <input type="text" name="code" value="{{ old('code') }}"
                                    class="form-control text-uppercase" placeholder="Ej: VEND001" required autofocus>
                            </div>
                            <button type="submit" class="btn btn-brand w-100">Ingresar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
