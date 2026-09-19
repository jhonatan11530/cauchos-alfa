<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Cauchos Alfa | Neumáticos y Llantas de alto rendimiento')</title>
    <meta name="google-site-verification" content="29v5bm7axdRaGKn_W81TiVpHGptO6pPlljdlvhmM-rc" />
    <meta name="description"
        content="@yield('description', 'Descubre la mejor selección de cauchos y neumáticos para todo tipo de vehículos. Diseño, seguridad y rendimiento.')">
    <meta property="og:title" content="@yield('title', 'Cauchos Alfa | Neumáticos de alto rendimiento')">
    <meta property="og:description"
        content="@yield('description', 'Cauchos premium y tecnología de punta para la máxima adherencia.')">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('img/hero-bike.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ asset('img/hero-bike.png') }}">
    <link rel="canonical" href="{{ url()->current() }}">

    @yield('schema')

    <link href="{{ asset('/css/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('/css/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/select2-bootstrap4.min.css') }}">

    <link rel="stylesheet" href="{{ asset('/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/roja.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/select2.min.css') }}">
    @if (config('recaptchav3.sitekey'))
        {!! RecaptchaV3::initJs() !!}
    @endif
</head>

<body>
    <div class="bike-landing">
        @include('site.partials.navbar')

        @yield('content')

        @include('site.partials.footer')
    </div>

    <script src="{{ asset('/js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('/js/toastr.min.js') }}"></script>
    <script src="{{ asset('/js/select2.min.js') }}"></script>
    <script>
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "5000"
        };
        @if (session('success'))
            toastr.success(@json(session('success')));
        @endif
        @if (session('error'))
            toastr.error(@json(session('error')));
        @endif
        @if (session('warning'))
            toastr.warning(@json(session('warning')));
        @endif
        @if (session('info'))
            toastr.info(@json(session('info')));
        @endif
        @if ($errors->any())
            @foreach ($errors->all() as $error)
                toastr.error(@json($error));
            @endforeach
        @endif

        if ($.fn.select2) {
            $('.select2').select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: 'Seleccionar una opción'
            });
        }
    </script>
    @stack('scripts')
</body>

</html>
