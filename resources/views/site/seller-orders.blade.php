@extends('site.layouts.app')

@section('title', 'Mis Pedidos | Cauchos Alfa')

@section('content')
<div class="container" style="margin-top: 120px; min-height: 70vh;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h2 class="font-weight-bold mb-1">Mis Pedidos</h2>
            <p class="text-muted mb-0">Gestión de despachos y registro de entrega con evidencia fotográfica y GPS.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="{{ route('site.catalog') }}" class="btn btn-outline-dark mr-2">
                <i class="bi bi-cart-plus"></i> Nuevo Pedido
            </a>
            <a href="{{ route('site.seller.order') }}" class="btn btn-red">
                <i class="bi bi-cart"></i> Ver Carrito ({{ count(session('seller_cart', [])) }})
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill mr-1"></i>
            <strong>Hubo un problema con la entrega:</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Filtros de estado --}}
    <div class="mb-3 d-flex flex-wrap align-items-center">
        <a href="{{ route('site.seller.orders', ['status' => 'despachado']) }}"
           class="btn btn-sm mr-2 mb-2 {{ ($currentFilter === 'despachado' || !$currentFilter) ? 'btn-primary' : 'btn-outline-secondary' }}">
            <i class="bi bi-truck mr-1"></i> Despachados por Entregar
            @if ($dispatchedCount > 0)
                <span class="badge badge-light ml-1">{{ $dispatchedCount }}</span>
            @endif
        </a>
        <a href="{{ route('site.seller.orders', ['status' => 'entregado']) }}"
           class="btn btn-sm mr-2 mb-2 {{ $currentFilter === 'entregado' ? 'btn-success' : 'btn-outline-secondary' }}">
            <i class="bi bi-check-circle mr-1"></i> Entregados
        </a>
        <a href="{{ route('site.seller.orders', ['status' => 'all']) }}"
           class="btn btn-sm mb-2 {{ $currentFilter === 'all' ? 'btn-dark' : 'btn-outline-secondary' }}">
            <i class="bi bi-list-ul mr-1"></i> Todos
        </a>
    </div>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="bg-dark text-white">
                        <tr>
                            <th class="border-0 px-3 py-3">Código</th>
                            <th class="border-0 py-3">Fecha</th>
                            <th class="border-0 py-3">Cliente</th>
                            <th class="border-0 py-3 text-center">Estado</th>
                            <th class="border-0 py-3">Evidencias</th>
                            <th class="border-0 py-3 text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            @php
                                $statusSlug = $order->status?->slug;
                                $isDispatched = in_array($statusSlug, ['pedido-despachado', 'despachado']);
                                $isDelivered = in_array($statusSlug, ['pedido-entregado', 'entregado']);
                            @endphp
                            <tr>
                                <td class="px-3 py-3 font-weight-bold text-nowrap">
                                    {{ $order->code }}
                                </td>
                                <td class="py-3 text-muted text-nowrap">
                                    {{ optional($order->ordered_at)->format('d/m/Y') ?? $order->created_at->format('d/m/Y') }}
                                </td>
                                <td class="py-3">
                                    <span class="font-weight-bold">{{ $order->client->name ?? 'Sin cliente' }}</span>
                                    @if ($order->client?->phone)
                                        <div class="small text-muted"><i class="bi bi-telephone"></i> {{ $order->client->phone }}</div>
                                    @endif
                                </td>
                                <td class="py-3 text-center">
                                    @if ($isDispatched)
                                        <span class="badge badge-warning p-2 text-dark font-weight-bold">
                                            <i class="bi bi-truck"></i> Despachado
                                        </span>
                                    @elseif ($isDelivered)
                                        <span class="badge badge-success p-2 font-weight-bold">
                                            <i class="bi bi-check2-circle"></i> Entregado
                                        </span>
                                    @else
                                        <span class="badge badge-secondary p-2">
                                            {{ $order->status->name ?? 'En proceso' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    @if ($order->delivery_photo_path || $order->client_location || $order->delivery_latitude)
                                        <div class="d-flex flex-column small">
                                            @if ($order->delivery_photo_path)
                                                <a href="{{ asset('storage/' . $order->delivery_photo_path) }}" target="_blank" class="text-success mb-1">
                                                    <i class="bi bi-image"></i> Ver Foto Entrega
                                                </a>
                                            @endif
                                            @if ($order->delivery_latitude && $order->delivery_longitude)
                                                <a href="https://www.google.com/maps?q={{ $order->delivery_latitude }},{{ $order->delivery_longitude }}" target="_blank" class="text-primary">
                                                    <i class="bi bi-geo-alt-fill text-danger"></i> {{ number_format($order->delivery_latitude, 5) }}, {{ number_format($order->delivery_longitude, 5) }}
                                                </a>
                                            @elseif ($order->client_location)
                                                <span class="text-muted"><i class="bi bi-geo-alt"></i> {{ Str::limit($order->client_location, 25) }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted small">Sin evidencias registradas</span>
                                    @endif
                                </td>
                                <td class="py-3 text-center text-nowrap">
                                    @if ($isDispatched)
                                        <button type="button"
                                                class="btn btn-sm btn-success font-weight-bold"
                                                data-toggle="modal"
                                                data-target="#modal-deliver-{{ $order->id }}">
                                            <i class="bi bi-check2-circle mr-1"></i> Registrar Entrega
                                        </button>
                                    @elseif ($isDelivered)
                                        <span class="text-success small font-weight-bold">
                                            <i class="bi bi-patch-check-fill"></i> Completado
                                        </span>
                                    @else
                                        <span class="text-muted small">Pendiente de despacho</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox display-4 d-block mb-3 text-secondary"></i>
                                    <h5>No se encontraron pedidos en esta categoría</h5>
                                    <p class="mb-0">Los pedidos despachados aparecerán aquí para que registres la entrega.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <div class="p-3 border-top">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Modales para registrar entrega --}}
@foreach ($orders as $order)
    @php
        $statusSlug = $order->status?->slug;
        $isDispatched = in_array($statusSlug, ['pedido-despachado', 'despachado']);
    @endphp
    @if ($isDispatched)
        <div class="modal fade" id="modal-deliver-{{ $order->id }}" tabindex="-1" role="dialog" aria-labelledby="modalLabel-{{ $order->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content shadow">
                    <form action="{{ route('site.seller.orders.deliver', $order) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title font-weight-bold" id="modalLabel-{{ $order->id }}">
                                <i class="bi bi-box-seam text-danger mr-1"></i> Registrar Entrega: {{ $order->code }}
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="alert alert-info py-2 px-3 small mb-3">
                                <strong>Cliente:</strong> {{ $order->client->name ?? 'N/A' }}<br>
                                <strong>Fecha del Pedido:</strong> {{ optional($order->ordered_at)->format('d/m/Y') ?? $order->created_at->format('d/m/Y') }}
                            </div>

                            {{-- Fotografía de Entrega --}}
                            <div class="form-group mb-3">
                                <label for="photo-{{ $order->id }}" class="font-weight-bold">
                                    Fotografía de entrega <span class="text-danger">*</span>
                                </label>
                                <div class="custom-file">
                                    <input type="file"
                                           name="delivery_photo"
                                           id="photo-{{ $order->id }}"
                                           class="form-control-file"
                                           accept="image/*"
                                           capture="environment"
                                           required>
                                </div>
                                <small class="form-text text-muted">Toma o sube una fotografía del pedido recibido en el sitio del cliente.</small>
                            </div>

                            {{-- Botón de Detección GPS --}}
                            <div class="form-group mb-3 p-3 bg-light rounded border">
                                <label class="font-weight-bold d-block mb-1">
                                    Ubicación Geográfica <span class="text-danger">*</span>
                                </label>
                                <button type="button"
                                        class="btn btn-outline-primary btn-sm mb-2 btn-gps"
                                        data-order-id="{{ $order->id }}">
                                    <i class="bi bi-crosshair mr-1"></i> Detectar ubicación GPS actual
                                </button>
                                <div id="gps-status-{{ $order->id }}" class="small mb-2"></div>

                                <div class="row">
                                    <div class="col-6">
                                        <label for="lat-{{ $order->id }}" class="small font-weight-bold text-muted">Latitud <span class="text-danger">*</span></label>
                                        <input type="text"
                                               name="delivery_latitude"
                                               id="lat-{{ $order->id }}"
                                               class="form-control form-control-sm"
                                               placeholder="Ej: 4.60971000"
                                               value="{{ old('delivery_latitude') }}"
                                               required>
                                    </div>
                                    <div class="col-6">
                                        <label for="lng-{{ $order->id }}" class="small font-weight-bold text-muted">Longitud <span class="text-danger">*</span></label>
                                        <input type="text"
                                               name="delivery_longitude"
                                               id="lng-{{ $order->id }}"
                                               class="form-control form-control-sm"
                                               placeholder="Ej: -74.08175000"
                                               value="{{ old('delivery_longitude') }}"
                                               required>
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <label for="loc-{{ $order->id }}" class="small font-weight-bold text-muted">Dirección o Referencia</label>
                                    <input type="text"
                                           name="client_location"
                                           id="loc-{{ $order->id }}"
                                           class="form-control form-control-sm"
                                           placeholder="Ej: Calle 100 # 15-20, Bogotá"
                                           value="{{ old('client_location') }}">
                                </div>
                            </div>

                            {{-- Observaciones opcionales --}}
                            <div class="form-group mb-2">
                                <label for="obs-{{ $order->id }}" class="small font-weight-bold text-muted">Observaciones (opcional)</label>
                                <textarea name="observation"
                                          id="obs-{{ $order->id }}"
                                          rows="2"
                                          class="form-control form-control-sm"
                                          placeholder="Nombre de quien recibe, novedades, etc."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-red font-weight-bold px-4">
                                <i class="bi bi-check2-circle mr-1"></i> Confirmar Entrega
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endforeach

<script>
document.addEventListener('DOMContentLoaded', function () {
    const gpsButtons = document.querySelectorAll('.btn-gps');

    gpsButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const orderId = this.getAttribute('data-order-id');
            const latInput = document.getElementById('lat-' + orderId);
            const lngInput = document.getElementById('lng-' + orderId);
            const locInput = document.getElementById('loc-' + orderId);
            const statusDiv = document.getElementById('gps-status-' + orderId);

            if (!navigator.geolocation) {
                alert('La geolocalización no está soportada en este navegador.');
                return;
            }

            button.disabled = true;
            const originalHtml = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Obteniendo GPS...';

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    const lat = position.coords.latitude.toFixed(8);
                    const lng = position.coords.longitude.toFixed(8);

                    if (latInput) latInput.value = lat;
                    if (lngInput) lngInput.value = lng;
                    if (locInput && (!locInput.value || locInput.value.trim() === '')) {
                        locInput.value = position.coords.latitude.toFixed(6) + ', ' + position.coords.longitude.toFixed(6);
                    }

                    if (statusDiv) {
                        statusDiv.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> GPS detectado (precisión ±' + Math.round(position.coords.accuracy) + 'm)</span>';
                    }

                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-check2 mr-1"></i> GPS Actualizado';
                },
                function (error) {
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                    if (statusDiv) {
                        statusDiv.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle-fill"></i> No se pudo obtener el GPS (' + error.message + '). Ingrese las coordenadas manualmente.</span>';
                    }
                    if (latInput) latInput.focus();
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        });
    });
});
</script>
@endsection
