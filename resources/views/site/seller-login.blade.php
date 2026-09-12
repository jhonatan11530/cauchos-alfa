@extends('site.layouts.app')

@section('title', 'Acceso Vendedores | Cauchos Alfa')

@section('content')
    <div class="container-fluid bg-void"
        style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem;">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-lg border-0"
                style="background: var(--bike-charcoal); border-radius: 28px; border: 1px solid rgba(255, 255, 255, 0.05);">
                <div class="card-body p-5 text-center">
                    <div class="mb-4">
                        <div class="d-inline-block p-3 rounded-circle"
                            style="background: rgba(230, 0, 18, 0.1); color: var(--bike-red);">
                            <i class="bi bi-person-badge-fill display-4"></i>
                        </div>
                    </div>

                    <h2 class="text-white font-weight-bold mb-2">Acceso Vendedores</h2>
                    <p class="text-muted-custom mb-4">Ingresa tu código para gestionar pedidos.</p>

                    @if (session('error'))
                        <div class="alert alert-danger border-0 text-white" style="background: rgba(220, 53, 69, 0.2);">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger border-0 text-white" style="background: rgba(220, 53, 69, 0.2);">
                            <ul class="mb-0 small">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form id="seller-login-form" method="POST" action="{{ route('site.seller.auth') }}">
                        @csrf
                        @if (config('recaptchav3.sitekey'))
                            {!! RecaptchaV3::field('seller_login') !!}
                        @endif
                        <div class="form-group text-left">
                            <label for="code" class="small font-weight-bold text-white-50">CÓDIGO DE VENDEDOR</label>
                            <input type="text" name="code" id="code" class="form-control form-control-lg text-center"
                                style="background: var(--bike-void); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 12px;"
                                placeholder="Ej: VEN123" required autofocus>
                        </div>

                        <button type="submit" class="btn btn-red btn-block btn-lg font-weight-bold mt-4 shadow">
                            INGRESAR AL SISTEMA
                        </button>
                    </form>

                    <div class="mt-4">
                        <a href="{{ route('site.home') }}" class="text-muted-custom small" style="text-decoration: none;">
                            <i class="bi bi-arrow-left mr-1"></i> Volver al inicio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@if (config('recaptchav3.sitekey'))
@push('scripts')
<script>
    document.getElementById('seller-login-form')?.addEventListener('submit', function (e) {
        var form = this;
        var input = form.querySelector('input[name="g-recaptcha-response"]');
        if (typeof grecaptcha !== 'undefined' && input && !form.dataset.recaptchaRefreshed) {
            e.preventDefault();
            grecaptcha.ready(function () {
                grecaptcha.execute('{{ config('recaptchav3.sitekey') }}', { action: 'seller_login' }).then(function (token) {
                    input.value = token;
                    form.dataset.recaptchaRefreshed = 'true';
                    form.submit();
                }).catch(function () {
                    form.dataset.recaptchaRefreshed = 'true';
                    form.submit();
                });
            });
        }
    });
</script>
@endpush
@endif
