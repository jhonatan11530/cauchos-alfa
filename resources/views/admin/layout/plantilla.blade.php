<!DOCTYPE html>
<html lang="es">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Panel Administrativo</title>
    <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
    <link rel="icon" href="{{ asset('/img/icon.ico') }}" type="image/x-icon" />

    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="{{ asset('/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/atlantis.min.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/select2.min.css') }}">
</head>

<body>
    <div class="wrapper">
        <div class="main-header">
            <!-- Logo Header -->
            <div class="logo-header" data-background-color="blue">
                <button class="navbar-toggler sidenav-toggler ml-auto" type="button" data-toggle="collapse"
                    data-target="collapse" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon">
                        <i class="icon-menu"></i>
                    </span>
                </button>
                <button class="topbar-toggler more"><i class="icon-options-vertical"></i></button>
                <div class="nav-toggle">
                    <button class="btn btn-toggle toggle-sidebar">
                        <i class="icon-menu"></i>
                    </button>
                </div>
            </div>
            <!-- End Logo Header -->

            <!-- Navbar Header -->
            <nav class="navbar navbar-header navbar-expand-lg" data-background-color="blue2">
                <div class="container-fluid justify-content-end">
                    @auth
                        <form action="{{ route('logout') }}" method="POST" class="mb-0">
                            @csrf
                            <button class="btn btn-sm btn-light">Cerrar sesion</button>
                        </form>
                    @endauth
                </div>
            </nav>
            <!-- End Navbar -->
        </div>

        <!-- Sidebar -->
        <div class="sidebar sidebar-style-2">
            <div class="sidebar-wrapper scrollbar scrollbar-inner">
                <div class="sidebar-content">
                    <div class="user">
                        <div class="avatar-sm float-left mr-2">
                            <img src="{{ asset('/img/foto.png') }}" alt="..." class="avatar-img rounded-circle">
                        </div>
                        <div class="info">
                            <a href="#">
                                <span>
                                    {{ auth()->user()->name ?? 'Usuario' }}
                                    <span class="user-level">{{ auth()->user()->role->name ?? 'Administrador' }}</span>
                                </span>
                            </a>
                            <div class="clearfix"></div>
                        </div>
                    </div>
                    <ul class="nav nav-primary">
                        <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <a href="{{ route('dashboard') }}">
                                <i class="fas fa-home"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>
                        <li class="nav-section">
                            <h4 class="text-section">Supervisar Pedidos</h4>
                        </li>
                        <li class="nav-item {{ request()->routeIs('pedidos.*') ? 'active' : '' }}">
                            <a href="{{ route('pedidos.index') }}">
                                <i class="fas fa-clipboard-list"></i>
                                <p>Pedidos</p>
                            </a>
                        </li>
                        <li class="nav-section">
                            <h4 class="text-section">Opciones</h4>
                        </li>
                        @if (auth()->user() && auth()->user()->isAdmin())
                            <li class="nav-item {{ request()->routeIs('vendedores.*') ? 'active' : '' }}">
                                <a href="{{ route('vendedores.index') }}">
                                    <i class="fas fa-user-tag"></i>
                                    <p>Vendedores</p>
                                </a>
                            </li>
                            <li class="nav-item {{ request()->routeIs('usuarios.*') ? 'active' : '' }}">
                                <a href="{{ route('usuarios.index') }}">
                                    <i class="fas fa-users-cog"></i>
                                    <p>Usuarios</p>
                                </a>
                            </li>
                            <li class="nav-item {{ request()->routeIs('estados-pedido.*') ? 'active' : '' }}">
                                <a href="{{ route('estados-pedido.index') }}">
                                    <i class="fas fa-route"></i>
                                    <p>Estados</p>
                                </a>
                            </li>
                        @endif
                        <li class="nav-item {{ request()->routeIs('clientes.*') ? 'active' : '' }}">
                            <a href="{{ route('clientes.index') }}">
                                <i class="fas fa-address-book"></i>
                                <p>Clientes</p>
                            </a>
                        </li>
                        <li class="nav-item {{ request()->routeIs('categoria.*') ? 'active' : '' }}">
                            <a href="{{ route('categoria.index') }}">
                                <i class="fas fa-tags"></i>
                                <p>Categorías</p>
                            </a>
                        </li>
                        <li class="nav-item {{ request()->routeIs('productos.*') ? 'active' : '' }}">
                            <a href="{{ route('productos.index') }}">
                                <i class="fas fa-boxes"></i>
                                <p>Productos</p>
                            </a>
                        </li>
                        <li class="nav-item {{ request()->routeIs('catalogos.*') ? 'active' : '' }}">
                            <a href="{{ route('catalogos.index') }}">
                                <i class="fas fa-book"></i>
                                <p>Catálogos</p>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- End Sidebar -->

        <div class="main-panel">
            <div class="content text-capitalize">
                <div class="panel-header bg-primary-gradient">
                    <div class="page-inner py-5">
                        @yield('banner')
                    </div>
                </div>
                @yield('contenido')
            </div>
            <footer class="footer">
                <div class="container-fluid">
                    <div class="copyright ml-auto">
                        Panel administrativo de trazabilidad de pedidos
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Fonts and icons -->
    <script src="{{ asset('/js/plugin/webfont/webfont.min.js') }}"></script>
    <script>
        WebFont.load({
            google: {
                "families": ["Lato:300,400,700,900"]
            },
            custom: {
                "families": ["Flaticon", "Font Awesome 5 Solid", "Font Awesome 5 Regular", "Font Awesome 5 Brands",
                    "simple-line-icons"
                ],
                urls: ['{{ asset('/css/fonts.min.css') }}']
            },
            active: function() {
                sessionStorage.fonts = true;
            }
        });
    </script>
    <!--   Core JS Files   -->
    <script src="{{ asset('/js/core/jquery.3.2.1.min.js') }}"></script>
    <script src="{{ asset('/js/core/popper.min.js') }}"></script>
    <script src="{{ asset('/js/core/bootstrap.min.js') }}"></script>

    <!-- jQuery UI -->
    <script src="{{ asset('/js/plugin/jquery-ui-1.12.1.custom/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('/js/plugin/jquery-ui-touch-punch/jquery.ui.touch-punch.min.js') }}"></script>

    <!-- jQuery Scrollbar -->
    <script src="{{ asset('/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js') }}"></script>

    <!-- Chart JS -->
    <script src="{{ asset('/js/plugin/chart.js/chart.min.js') }}"></script>

    <!-- jQuery Sparkline -->
    <script src="{{ asset('/js/plugin/jquery.sparkline/jquery.sparkline.min.js') }}"></script>

    <!-- Chart Circle -->
    <script src="{{ asset('/js/plugin/chart-circle/circles.min.js') }}"></script>

    <!-- Datatables -->
    <script src="{{ asset('/js/plugin/datatables/datatables.min.js') }}"></script>

    <!-- Sweet Alert -->
    <script src="{{ asset('/js/plugin/sweetalert/sweetalert.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>


    <!-- Atlantis JS -->
    <script src="{{ asset('/js/atlantis.min.js') }}"></script>
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
    </script>
    @stack('scripts')
</body>

</html>
