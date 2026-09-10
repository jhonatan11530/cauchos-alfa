<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/') }}">{{ config('app.name') }}<span class="text-red">.</span></a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNav" aria-controls="mainNav"
            aria-expanded="false" aria-label="Abrir navegación">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item"><a class="nav-link" href="{{ route('site.home') }}#Descripción">Sobre Nosotros</a>
                </li>
                <li class="nav-item"><a class="nav-link" href="{{ route('site.home') }}#MisiónVisión">Misión y Visión</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('site.home') }}#contacto">Contacto</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('site.catalog') }}">Catálogo</a></li>
                @auth
                    @if (auth()->user()->isSeller())
                        <li class="nav-item"><a class="nav-link" href="{{ route('site.seller.order') }}"><i
                                    class="bi bi-cart"></i> Mi pedido
                                ({{ count(session('seller_cart', [])) }})
                            </a></li>
                        <li class="nav-item ms-lg-2">
                            <form method="POST" action="{{ route('site.seller.logout') }}">@csrf
                                <button class="nav-link btn btn-red">Salir ({{ auth()->user()->name }})</button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item ms-lg-2"><a class="nav-link btn btn-red btn-lg"
                                href="{{ route('dashboard') }}">Panel Administrativo</a>
                        </li>
                    @endif
                @else
                    <li class="nav-item"><a class="nav-link btn btn-red btn-lg"
                            href="{{ route('site.seller.login') }}">Vendedores</a></li>
                    <li class="nav-item"><a class="nav-link btn btn-red btn-lg" href="{{ route('login') }}">Ingresar</a>
                    @endauth
            </ul>
        </div>
    </div>
</nav>
