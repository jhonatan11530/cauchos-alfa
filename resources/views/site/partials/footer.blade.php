<footer class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0">
                <h3 class="h4 font-weight-bold">{{ config('app.name') }}<span class="text-red">.</span></h3>
                <p class="text-muted-custom mt-2 mb-0">
                    Bicicletería premium para ciclistas que buscan rendimiento, diseño y una experiencia inolvidable.
                </p>
            </div>
            <div class="col-lg-2 col-6 mb-3 mb-lg-0">
                <h4 class="h6 font-weight-bold mb-3">Productos</h4>
                <ul class="list-unstyled">
                    <li><a href="#bicicletas" class="footer-link d-block py-1">Carretera</a></li>
                    <li><a href="#bicicletas" class="footer-link d-block py-1">Montaña</a></li>
                    <li><a href="#bicicletas" class="footer-link d-block py-1">Urbana</a></li>
                    <li><a href="#" class="footer-link d-block py-1">Accesorios</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-6 mb-3 mb-lg-0">
                <h4 class="h6 font-weight-bold mb-3">Empresa</h4>
                <ul class="list-unstyled">
                    <li><a href="{{ route('site.home') }}#Descripción" class="footer-link d-block py-1">Nosotros</a></li>
                    <li><a href="{{ route('site.home') }}#Contacto" class="footer-link d-block py-1">Contacto</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <h4 class="h6 font-weight-bold mb-3">Visítanos</h4>
                <p class="text-muted-custom mb-1">Cr28 A 1-72 Y 1 El Poblado II, Cali, Valle del Cauca.</p>
                <p class="text-muted-custom mb-0">Lun–Sáb: 9:00 a.m. – 7:00 p.m.</p>
            </div>
        </div>
        <div class="border-top mt-5 pt-4" style="border-color: rgba(255,255,255,0.06);">
            <p class="text-muted-custom small mb-0 text-center">
                © {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
            </p>
        </div>
    </div>
</footer>
