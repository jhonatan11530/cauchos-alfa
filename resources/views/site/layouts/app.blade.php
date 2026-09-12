<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Roja Bicis | Ciclismo de alto rendimiento')</title>
    <meta name="description"
        content="@yield('description', 'Descubre bicicletas premium de carretera, montaña y ciudad. Diseño, ligereza y rendimiento con estética minimalista.')">
    <meta property="og:title" content="@yield('title', 'Roja Bicis | Ciclismo de alto rendimiento')">
    <meta property="og:description"
        content="@yield('description', 'Bicicletas premium con diseño minimalista y tecnología de punta.')">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('images/hero-bike.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ asset('images/hero-bike.png') }}">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Bootstrap 4.6 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
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
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Seleccionar una opción'
            });
        }
    </script>
    @stack('scripts')
</body>

</html>
