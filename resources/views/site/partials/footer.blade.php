<footer class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0">
                <h3 class="h4 font-weight-bold">{{ config('app.name') }}<span class="text-red">.</span></h3>
                <p class="text-muted-custom mt-2 mb-0">
                    Fabricantes de cauchos para motocicletas, comprometidos con la calidad, la resistencia y la
                    confianza. Más de 40 años de experiencia nos respaldan, ofreciendo productos diseñados para brindar
                    seguridad y rendimiento en cada camino.
                </p>
            </div>
            <div class="col-lg-2 col-6 mb-3 mb-lg-0">
                <h4 class="h6 font-weight-bold mb-3">Productos</h4>
                <ul class="list-unstyled">
                    <li><a href="{{ route('site.catalog') }}" class="footer-link d-block py-1">Carretera</a></li>
                    <li><a href="{{ route('site.catalog') }}" class="footer-link d-block py-1">Montaña</a></li>
                    <li><a href="{{ route('site.catalog') }}" class="footer-link d-block py-1">Urbana</a></li>
                    <li><a href="{{ route('site.catalog') }}" class="footer-link d-block py-1">Accesorios</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-6 mb-3 mb-lg-0">
                <h4 class="h6 font-weight-bold mb-3">Empresa</h4>
                <ul class="list-unstyled">
                    <li><a href="{{ route('site.home') }}#MisiónVisión" class="footer-link d-block py-1">Nosotros</a>
                    </li>
                    <li><a href="{{ route('site.home') }}#contacto" class="footer-link d-block py-1">Contacto</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <h4 class="h6 font-weight-bold mb-3">Visítanos</h4>
                <p class="text-muted-custom mb-1">Carrera 39 # 26 A 52 la independencia, Cali, Valle del Cauca.</p>
                <p class="text-muted-custom mb-0">Lun–Sáb: 7:00 a.m. – 6:00 p.m.</p>
            </div>
        </div>
        <div class="border-top mt-5 pt-4" style="border-color: rgba(255,255,255,0.06);">
            <p class="text-muted-custom small mb-0 text-center">
                © {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
            </p>
        </div>
    </div>
</footer>
