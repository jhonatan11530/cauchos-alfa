@extends('admin.layout.plantilla')

@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row justify-content-between">
        <div>
            <h2 class="text-white pb-2 fw-bold"><i class="fas fa-truck-loading mr-2"></i>Despachos y Entregas</h2>
            <h5 class="text-white op-7 mb-2">Programación y hoja de ruta para pedidos despachados</h5>
        </div>
        <div class="py-2 py-md-0">
            <a href="{{ route('dispatch.print', ['date' => $selectedDate]) }}" target="_blank" class="btn btn-primary">
                <i class="fas fa-print mr-1"></i> Imprimir Manifiesto de Ruta
            </a>
        </div>
    </div>
@endsection

@section('contenido')
    <div class="page-inner mt--5">
        {{-- Filtro de Fecha --}}
        <div class="card mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('dispatch.index') }}" class="form-inline">
                    <label class="mr-2 font-weight-bold">Fecha del Pedido:</label>
                    <input type="date" name="date" value="{{ $selectedDate }}" class="form-control mr-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-2">
                        <i class="fas fa-calendar-alt mr-1"></i> Ver Despachos
                    </button>
                    <a href="{{ route('dispatch.index') }}" class="btn btn-outline-secondary btn-sm">Hoy</a>
                </form>
            </div>
        </div>

        {{-- Listado de Despachos --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title text-primary">
                    <i class="fas fa-clipboard-list mr-2"></i>Pedidos Despachados ({{ $totalOrders }})
                </h4>
                <span class="badge badge-info">{{ \Carbon\Carbon::parse($selectedDate)->format('d/m/Y') }}</span>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Dirección & Ciudad</th>
                            <th>Productos a Entregar</th>
                            <th>Ubicación & Contacto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            @php
                                $mapQuery = null;
                                if ($order->delivery_latitude && $order->delivery_longitude) {
                                    $mapQuery = "{$order->delivery_latitude},{$order->delivery_longitude}";
                                } elseif ($order->client_location) {
                                    $mapQuery = $order->client_location;
                                } elseif ($order->client?->address) {
                                    $mapQuery = $order->client->address . ($order->client->city ? ', ' . $order->client->city : '');
                                }
                                $phoneClean = $order->client?->phone ? preg_replace('/[^0-9]/', '', $order->client->phone) : null;
                                if ($phoneClean && !str_starts_with($phoneClean, '57') && strlen($phoneClean) == 10) {
                                    $phoneClean = '57' . $phoneClean;
                                }
                            @endphp
                            <tr>
                                <td class="fw-bold align-middle">
                                    <a href="{{ route('pedidos.show', $order) }}">{{ $order->code }}</a>
                                </td>
                                <td class="align-middle">
                                    <strong>{{ $order->client?->name }}</strong>
                                    @if ($order->client?->document)
                                        <div class="small text-muted">{{ $order->client->document }}</div>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    <div><i class="fas fa-map-marker-alt text-danger mr-1"></i>{{ $order->client?->address ?: 'Sin dirección' }}</div>
                                    <small class="text-muted">{{ $order->client?->city }}</small>
                                </td>
                                <td class="align-middle">
                                    <ul class="list-unstyled mb-0 small">
                                        @foreach ($order->items as $item)
                                            <li><strong>{{ $item->quantity }}x</strong> {{ $item->product?->name }}</li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="align-middle">
                                    @if ($mapQuery)
                                        <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($mapQuery) }}" target="_blank" class="btn btn-xs btn-outline-danger mr-1" title="Abrir en Google Maps">
                                            <i class="fas fa-map-marked-alt mr-1"></i>Mapa
                                        </a>
                                    @endif
                                    @if ($phoneClean)
                                        <a href="https://wa.me/{{ $phoneClean }}?text=Hola%20{{ urlencode($order->client?->name) }},%20le%20escribimos%20de%20Cauchos%20Alfa%20respecto%20a%20su%20pedido%20{{ $order->code }}.%20Estamos%20en%20ruta%20de%20entrega."
                                           target="_blank" class="btn btn-xs btn-outline-success" title="Enviar WhatsApp">
                                            <i class="fab fa-whatsapp mr-1"></i>WhatsApp
                                        </a>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    <a href="{{ route('pedidos.show', $order) }}" class="btn btn-sm btn-info" title="Ver Detalle">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fas fa-box-open mr-1"></i> No hay pedidos despachados para la fecha seleccionada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
