@extends('admin.layout.plantilla')

@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row justify-content-between">
        <div>
            <h2 class="text-white pb-2 fw-bold"><i class="fas fa-hand-holding-usd mr-2"></i>Cartera y Cobranzas</h2>
            <h5 class="text-white op-7 mb-2">Control de facturas a crédito, plazos de vencimiento y recaudo</h5>
        </div>
        <div class="py-2 py-md-0">
            <a href="{{ route('cartera.export', request()->all()) }}" class="btn btn-outline-light">
                <i class="fas fa-file-excel mr-1"></i> Exportar Cartera CSV
            </a>
        </div>
    </div>
@endsection

@section('contenido')
    <div class="page-inner mt--5">
        {{-- Tarjetas de Métricas --}}
        <div class="row">
            <div class="col-sm-6 col-md-3">
                <div class="card card-stats card-round">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-icon">
                                <div class="icon-big text-center icon-danger bubble-shadow-small">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                            </div>
                            <div class="col col-stats ml-3 ml-sm-0">
                                <div class="numbers">
                                    <p class="card-category">Vencidas</p>
                                    <h4 class="card-title text-danger">{{ $metrics['overdue'] }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card card-stats card-round">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-icon">
                                <div class="icon-big text-center icon-warning bubble-shadow-small">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                            <div class="col col-stats ml-3 ml-sm-0">
                                <div class="numbers">
                                    <p class="card-category">Vencen en 7 días</p>
                                    <h4 class="card-title text-warning">{{ $metrics['due_soon'] }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card card-stats card-round">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-icon">
                                <div class="icon-big text-center icon-info bubble-shadow-small">
                                    <i class="fas fa-receipt"></i>
                                </div>
                            </div>
                            <div class="col col-stats ml-3 ml-sm-0">
                                <div class="numbers">
                                    <p class="card-category">Total Pendientes</p>
                                    <h4 class="card-title text-info">{{ $metrics['pending'] }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card card-stats card-round">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-icon">
                                <div class="icon-big text-center icon-success bubble-shadow-small">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="col col-stats ml-3 ml-sm-0">
                                <div class="numbers">
                                    <p class="card-category">Cobradas</p>
                                    <h4 class="card-title text-success">{{ $metrics['collected'] }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filtros y Búsqueda --}}
        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                    <div class="btn-group btn-group-sm mb-2 mb-md-0" role="group">
                        <a href="{{ route('cartera.index', array_merge(request()->except('filter', 'page'), ['filter' => 'todas'])) }}"
                           class="btn {{ $currentFilter === 'todas' ? 'btn-primary' : 'btn-outline-primary' }}">
                            Todas ({{ $metrics['total'] }})
                        </a>
                        <a href="{{ route('cartera.index', array_merge(request()->except('filter', 'page'), ['filter' => 'vencidas'])) }}"
                           class="btn {{ $currentFilter === 'vencidas' ? 'btn-danger' : 'btn-outline-danger' }}">
                            Vencidas ({{ $metrics['overdue'] }})
                        </a>
                        <a href="{{ route('cartera.index', array_merge(request()->except('filter', 'page'), ['filter' => 'por_vencer'])) }}"
                           class="btn {{ $currentFilter === 'por_vencer' ? 'btn-warning' : 'btn-outline-warning' }}">
                            Por Vencer ({{ $metrics['due_soon'] }})
                        </a>
                        <a href="{{ route('cartera.index', array_merge(request()->except('filter', 'page'), ['filter' => 'pendientes'])) }}"
                           class="btn {{ $currentFilter === 'pendientes' ? 'btn-info' : 'btn-outline-info' }}">
                            Pendientes ({{ $metrics['pending'] }})
                        </a>
                        <a href="{{ route('cartera.index', array_merge(request()->except('filter', 'page'), ['filter' => 'cobradas'])) }}"
                           class="btn {{ $currentFilter === 'cobradas' ? 'btn-success' : 'btn-outline-success' }}">
                            Cobradas ({{ $metrics['collected'] }})
                        </a>
                    </div>
                </div>

                <form method="GET" action="{{ route('cartera.index') }}">
                    <input type="hidden" name="filter" value="{{ $currentFilter }}">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-5 mb-2 mb-md-0">
                            <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar por código de pedido o cliente...">
                        </div>
                        <div class="col-md-4 mb-2 mb-md-0">
                            <select name="client_id" class="form-control">
                                <option value="">Todos los clientes...</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(request('client_id') == $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                            <a href="{{ route('cartera.index') }}" class="btn btn-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Tabla de Cuentas por Cobrar --}}
        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Facturación</th>
                            <th>Fecha Pedido</th>
                            <th>Vencimiento</th>
                            <th>Mora / Plazo</th>
                            <th>Estado Cobro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            @php
                                $today = now()->startOfDay();
                                $dueDate = $order->payment_due_date ? \Carbon\Carbon::parse($order->payment_due_date)->startOfDay() : null;
                                $diff = $dueDate ? $today->diffInDays($dueDate, false) : null;
                                $isOverdue = $dueDate && empty($order->paid_at) && $diff < 0;
                            @endphp
                            <tr class="{{ $isOverdue ? 'table-danger' : ($dueDate && empty($order->paid_at) && $diff <= 3 ? 'table-warning' : '') }}">
                                <td class="fw-bold">
                                    <a href="{{ route('pedidos.show', $order) }}">{{ $order->code }}</a>
                                </td>
                                <td>
                                    <div><strong>{{ $order->client?->name }}</strong></div>
                                    @if ($order->client?->phone)
                                        <small class="text-muted"><i class="fas fa-phone mr-1"></i>{{ $order->client->phone }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $order->billing_type == 'factura_electronica' ? 'badge-info' : 'badge-secondary' }}">
                                        {{ $order->billing_type == 'factura_electronica' ? 'Factura Electrónica' : 'Remisión' }}
                                    </span>
                                </td>
                                <td>{{ $order->ordered_at ? \Carbon\Carbon::parse($order->ordered_at)->format('d/m/Y') : '-' }}</td>
                                <td>
                                    <strong>{{ $dueDate ? $dueDate->format('d/m/Y') : 'Sin fecha' }}</strong>
                                </td>
                                <td>
                                    @if ($order->paid_at)
                                        <span class="text-success fw-bold"><i class="fas fa-check-circle mr-1"></i>Pagado</span>
                                    @elseif ($dueDate)
                                        @if ($diff < 0)
                                            <span class="badge badge-danger">Vencida hace {{ abs($diff) }} días</span>
                                        @elseif ($diff === 0)
                                            <span class="badge badge-warning">Vence Hoy</span>
                                        @else
                                            <span class="badge badge-info">Vence en {{ $diff }} días</span>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($order->paid_at)
                                        <span class="badge badge-success">
                                            Cobrado ({{ \Carbon\Carbon::parse($order->paid_at)->format('d/m/Y') }})
                                        </span>
                                        @if ($order->payment_support_path)
                                            <div class="mt-1">
                                                <a href="{{ asset('storage/' . $order->payment_support_path) }}" target="_blank" class="small text-primary">
                                                    <i class="fas fa-paperclip mr-1"></i>Ver Soporte
                                                </a>
                                            </div>
                                        @endif
                                    @else
                                        <span class="badge badge-secondary">Pendiente</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <a href="{{ route('pedidos.show', $order) }}" class="btn btn-sm btn-outline-info mr-1" title="Ver Pedido">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if (empty($order->paid_at))
                                            <button type="button" class="btn btn-sm btn-success btn-register-payment"
                                                    data-order-id="{{ $order->id }}"
                                                    data-order-code="{{ $order->code }}"
                                                    data-action="{{ route('cartera.payment', $order) }}"
                                                    title="Registrar Cobro">
                                                <i class="fas fa-dollar-sign mr-1"></i>Cobrar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-info-circle mr-1"></i>No se encontraron cuentas por cobrar con los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-3">
                    {{ $orders->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Registrar Pago --}}
    <div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title font-weight-bold" id="paymentModalTitle">
                        <i class="fas fa-dollar-sign mr-2"></i>Registrar Cobro
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="paymentForm" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <p class="mb-3 text-muted">Registra el pago recibido para el pedido <strong id="modalOrderCode"></strong>:</p>

                        <div class="form-group px-0">
                            <label class="font-weight-bold">Fecha del Pago *</label>
                            <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="form-group px-0">
                            <label class="font-weight-bold">Responsable del Recaudo</label>
                            <select name="payment_responsible_id" class="form-control">
                                <option value="">Seleccionar responsable...</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected(auth()->id() == $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group px-0">
                            <label class="font-weight-bold">Comprobante / Soporte de Pago</label>
                            <input type="file" name="payment_support" class="form-control-file border p-2 rounded" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            <small class="form-text text-muted">Adjunta comprobante de transferencia o recibo (PDF o imagen, máx 5MB).</small>
                        </div>

                        <div class="form-group px-0">
                            <label class="font-weight-bold">Observaciones del Cobro</label>
                            <textarea name="observation" rows="2" class="form-control" placeholder="Ej: Pago realizado vía transferencia Bancolombia..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check mr-1"></i> Confirmar Pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        $('.btn-register-payment').on('click', function () {
            var orderCode = $(this).data('order-code');
            var actionUrl = $(this).data('action');

            $('#modalOrderCode').text(orderCode);
            $('#paymentForm').attr('action', actionUrl);
            $('#paymentModal').modal('show');
        });
    });
</script>
@endpush
