@extends('admin.layout.plantilla')
@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row">
        <div>
            <h2 class="text-white pb-2 fw-bold">Dashboard</h2>
            <h5 class="text-white op-7 mb-2">Resumen operativo del panel</h5>
        </div>
    </div>
@endsection
@section('contenido')
    <div id="dashboard-realtime" data-orders="{{ $ordersCount }}">
        <div class="page-inner mt--5">
            <div class="row mt--2">
                @foreach ([['Clientes', $clientsCount, 'fas fa-address-book'], ['Productos', $productsCount, 'fas fa-boxes'], ['Catálogos', $catalogsCount, 'fas fa-book'], ['Pedidos', $ordersCount, 'fas fa-clipboard-list']] as $card)
                    <div class="col-sm-6 col-md-3">
                        <div class="card card-stats card-round">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-icon">
                                        <div class="icon-big text-center icon-primary bubble-shadow-small">
                                            <i class="{{ $card[2] }}"></i>
                                        </div>
                                    </div>
                                    <div class="col col-stats ml-3 ml-sm-0">
                                        <div class="numbers">
                                            <p class="card-category">{{ $card[0] }}</p>
                                            <h4 class="card-title">{{ $card[1] }}</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header"><h4 class="card-title">Pedidos recientes</h4></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr><th>Código</th><th>Fecha</th><th>Cliente</th><th>Estado</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($recentOrders as $order)
                                            <tr>
                                                <td>{{ $order->code }}</td>
                                                <td>{{ $order->created_at->format('d/m/Y h:i A') }}</td>
                                                <td>{{ $order->client->name }}</td>
                                                <td><span class="badge" style="background: {{ $order->status->color }}; color: #fff">{{ $order->status->name }}</span></td>
                                                <td><a href="{{ route('pedidos.show', $order) }}" class="btn btn-sm btn-primary">Ver</a></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="text-center">No hay pedidos registrados.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        let currentOrdersCount = parseInt(document.getElementById('dashboard-realtime').getAttribute('data-orders'));

        setInterval(() => {
            fetch('{{ route('dashboard') }}')
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newDashboard = doc.getElementById('dashboard-realtime');

                    if (newDashboard) {
                        const newOrdersCount = parseInt(newDashboard.getAttribute('data-orders'));

                        // Si hay un pedido nuevo
                        if (newOrdersCount > currentOrdersCount) {
                            // Reproducir sonido (se usará el AudioContext para mayor compatibilidad)
                            try {
                                const audio = new Audio('https://www.soundjay.com/buttons/sounds/button-09.mp3');
                                audio.play().catch(e => console.log('El navegador bloqueó el autoplay del sonido.'));
                            } catch(e) {}

                            // Mostrar notificación si toastr está disponible
                            if (typeof toastr !== 'undefined') {
                                toastr.success('¡Acaba de llegar un nuevo pedido!', 'Nuevo Pedido');
                            }

                            currentOrdersCount = newOrdersCount;
                        }

                        // Actualizar el HTML
                        document.getElementById('dashboard-realtime').innerHTML = newDashboard.innerHTML;
                        document.getElementById('dashboard-realtime').setAttribute('data-orders', newOrdersCount);
                    }
                })
                .catch(error => console.error('Error actualizando dashboard:', error));
        }, 15000); // 15 segundos
    });
</script>
@endpush
