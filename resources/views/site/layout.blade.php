<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
        content="Cauchos Alfa - Venta de llantas y servicios automotrices. Conoce nuestro catálogo de productos.">
    <title>@yield('title', 'Cauchos Alfa')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="{{ asset('/css/select2.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <style>
        :root {
            --brand: #d81e05;
            --dark: #1a1a1a;
        }

        .navbar-brand span {
            color: var(--brand);
            font-weight: 700;
        }

        .hero {
            background: linear-gradient(rgba(0, 0, 0, .65), rgba(0, 0, 0, .65)), url('https://images.unsplash.com/photo-1600661653561-629509216228?w=1600') center/cover no-repeat;
            color: #fff;
            padding: 7rem 0;
            text-align: center;
        }

        .btn-brand {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
        }

        .btn-brand:hover {
            background: #b31805;
            color: #fff;
        }

        .card-product img {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }

        section {
            scroll-margin-top: 80px;
        }

        footer {
            background: var(--dark);
            color: #ccc;
        }

        .mission-vision .card {
            border-top: 4px solid var(--brand);
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow">
        <div class="container">
            <a class="navbar-brand fw-bold" href="{{ route('site.home') }}">CAUCHOS <span>ALFA</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="{{ route('site.home') }}">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('site.home') }}#descripcion">Descripción</a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('site.home') }}#mision">Misión</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('site.home') }}#vision">Visión</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('site.catalog') }}">Catálogo</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('site.contact') }}">Contacto</a></li>
                    @auth
                        @if (auth()->user()->isSeller())
                            <li class="nav-item"><a class="nav-link" href="{{ route('site.seller.order') }}"><i class="bi bi-cart"></i> Mi pedido ({{ count(session('seller_cart', [])) }})</a></li>
                            <li class="nav-item ms-lg-2">
                                <form method="POST" action="{{ route('site.seller.logout') }}">@csrf
                                    <button class="btn btn-outline-light btn-sm px-3 mt-1">Salir ({{ auth()->user()->name }})</button>
                                </form>
                            </li>
                        @else
                            <li class="nav-item ms-lg-2"><a class="btn btn-brand btn-sm px-3 mt-1" href="{{ route('dashboard') }}">Panel</a></li>
                        @endif
                    @else
                        <li class="nav-item"><a class="nav-link" href="{{ route('site.seller.login') }}">Vendedores</a></li>
                        <li class="nav-item ms-lg-2"><a class="btn btn-brand btn-sm px-3 mt-1" href="{{ route('login') }}">Ingresar</a></li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <main class="flex-grow-1">
        @yield('contenido')
    </main>

    <footer class="py-4 mt-5">
        <div class="container">
            <div class="row gy-3">
                <div class="col-md-4">
                    <h5 class="text-white">CAUCHOS ALFA</h5>
                    <p class="mb-0 small">Llantas y servicios automotrices de calidad.</p>
                </div>
                <div class="col-md-4">
                    <h6 class="text-white">Contacto</h6>
                    <p class="mb-0 small">Dirección: Calle Principal #00-00<br>Teléfono: (000) 000 0000<br>Correo:
                        contacto@cauchosalfa.com</p>
                </div>
                <div class="col-md-4">
                    <h6 class="text-white">Síguenos</h6>
                    <a href="#" class="text-decoration-none text-light me-2"><i
                            class="bi bi-facebook fs-4"></i></a>
                    <a href="#" class="text-decoration-none text-light me-2"><i
                            class="bi bi-instagram fs-4"></i></a>
                    <a href="#" class="text-decoration-none text-light"><i class="bi bi-whatsapp fs-4"></i></a>
                </div>
            </div>
            <hr class="border-secondary">
            <p class="text-center small mb-0">&copy; {{ date('Y') }} Cauchos Alfa. Todos los derechos reservados.
            </p>
        </div>
    </footer>

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
