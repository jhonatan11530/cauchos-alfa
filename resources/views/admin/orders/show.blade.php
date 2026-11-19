@extends('admin.layout.plantilla')
@section('banner')
    @php
        $phoneClean = $order->client?->phone ? preg_replace('/[^0-9]/', '', $order->client->phone) : null;
        if ($phoneClean && !str_starts_with($phoneClean, '57') && strlen($phoneClean) == 10) {
            $phoneClean = '57' . $phoneClean;
        }
        $whatsappMsg = 'Hola ' . ($order->client?->name ?? 'Cliente') . ', le escribimos de Cauchos Alfa respecto a su pedido ' . $order->code . '. Estado actual: ' . ($order->status?->name ?? 'Sin estado') . '.';
    @endphp
    <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
        <div><h2 class="text-white pb-2 fw-bold">Pedido {{ $order->code }}</h2><h5 class="text-white op-7 mb-2">Detalle y trazabilidad</h5></div>
        <div class="d-flex align-items-center flex-wrap">
            @if ($phoneClean)
                <a href="https://wa.me/{{ $phoneClean }}?text={{ urlencode($whatsappMsg) }}" target="_blank" class="btn btn-success mr-2 mb-1">
                    <i class="fab fa-whatsapp mr-1"></i> WhatsApp
                </a>
            @endif
            <a href="{{ route('pedidos.create', ['repeat' => $order->id]) }}" class="btn btn-info mr-2 mb-1">
                <i class="fas fa-redo-alt mr-1"></i> Repetir Pedido
            </a>
            <a href="{{ route('pedidos.pdf', $order) }}" target="_blank" class="btn btn-primary mb-1">
                <i class="fas fa-print mr-1"></i> Imprimir
            </a>
        </div>
    </div>
@endsection
@section('contenido')
    <div class="page-inner mt--5">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><h4 class="card-title">Detalle</h4></div>
                    <div class="card-body">
                        <p><strong>Cliente:</strong> {{ $order->client->name }}</p>
                        <p><strong>Fecha de pedido:</strong> {{ optional($order->ordered_at ?? $order->created_at)->format('d/m/Y h:i A') }}</p>
                        <p><strong>Facturación:</strong> <span class="badge badge-info">{{ $order->billing_type == 'factura_electronica' ? 'Factura Electrónica' : 'Remisión' }}</span></p>
                        <p><strong>Estado actual:</strong> <span class="badge" style="background: {{ $order->status?->color ?? '#6c757d' }}; color: #fff">{{ $order->status?->name ?? 'Sin estado' }}</span></p>
                        <p><strong>Observaciones:</strong> {{ $order->notes }}</p>
                        <table class="table">
                            <thead><tr><th>Producto</th><th>Código / Ref.</th><th>Cantidad</th></tr></thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td>{{ $item->product?->name }}</td>
                                        <td>{{ $item->product?->reference ?: '-' }}</td>
                                        <td>{{ $item->quantity }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Bloque de Evidencias y Estado Financiero --}}
                <div class="card mt-3">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>Información de Cobranza y Evidencias</h4>
                        @if ($order->payment_term)
                            <span class="badge {{ $order->payment_term == 'credito' ? 'badge-warning' : 'badge-success' }}">
                                Modalidad: {{ ucfirst($order->payment_term) }}
                            </span>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="row">
                            {{-- Columna Financiera / Cobro --}}
                            <div class="col-md-6 border-right">
                                <h5 class="fw-bold text-primary mb-3"><i class="fas fa-coins mr-1"></i> Estado de Cobro</h5>
                                <p><strong>Modalidad:</strong> 
                                    @if ($order->payment_term == 'credito')
                                        <span class="badge badge-warning">A Crédito</span>
                                    @elseif ($order->payment_term == 'contado')
                                        <span class="badge badge-success">Contado</span>
                                    @else
                                        <span class="text-muted">No especificada</span>
                                    @endif
                                </p>
                                @if ($order->payment_term == 'credito')
                                    <p><strong>Fecha de vencimiento:</strong> 
                                        {{ $order->payment_due_date ? \Carbon\Carbon::parse($order->payment_due_date)->format('d/m/Y') : 'Sin fecha' }}
                                        @if ($order->payment_due_date && empty($order->paid_at))
                                            @php
                                                $diff = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($order->payment_due_date)->startOfDay(), false);
                                            @endphp
                                            @if ($diff < 0)
                                                <span class="badge badge-danger">Vencida hace {{ abs($diff) }} días</span>
                                            @elseif ($diff == 0)
                                                <span class="badge badge-warning">Vence hoy</span>
                                            @else
                                                <span class="badge badge-info">Vence en {{ $diff }} días</span>
                                            @endif
                                        @endif
                                    </p>
                                @endif
                                <p><strong>Estado de pago:</strong> 
                                    @if ($order->paid_at)
                                        <span class="badge badge-success"><i class="fas fa-check"></i> Cobrado</span>
                                    @else
                                        <span class="badge badge-secondary">Pendiente de cobro</span>
                                    @endif
                                </p>
                                @if ($order->paid_at)
                                    <p><strong>Responsable del cobro:</strong> {{ $order->paymentResponsible?->name ?? 'Administración' }}</p>
                                    <p><strong>Fecha de cobro:</strong> {{ optional($order->paid_at)->format('d/m/Y h:i A') }}</p>
                                    @if ($order->payment_support_path)
                                        <p><strong>Soporte de cobro:</strong> 
                                            <a href="{{ asset('storage/' . $order->payment_support_path) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-download mr-1"></i> Ver soporte adjunto
                                            </a>
                                        </p>
                                    @endif
                                @endif
                            </div>

                            {{-- Columna Evidencias Operativas --}}
                            <div class="col-md-6">
                                <h5 class="fw-bold text-primary mb-3"><i class="fas fa-clipboard-check mr-1"></i> Evidencias Registradas</h5>
                                
                                {{-- Despacho --}}
                                <div class="mb-3">
                                    <p class="mb-1"><strong>Autorización de Despacho:</strong></p>
                                    @if ($order->dispatch_signature_path || $order->dispatch_photo_path)
                                        <div class="d-flex align-items-center flex-wrap">
                                            @if ($order->dispatch_signature_path)
                                                <a href="{{ asset('storage/' . $order->dispatch_signature_path) }}" target="_blank" class="mr-2 mb-1">
                                                    <img src="{{ asset('storage/' . $order->dispatch_signature_path) }}" alt="Firma" class="img-thumbnail" style="max-height: 50px;">
                                                </a>
                                            @endif
                                            @if ($order->dispatch_photo_path)
                                                <a href="{{ asset('storage/' . $order->dispatch_photo_path) }}" target="_blank" class="btn btn-sm btn-outline-info mr-2 mb-1">
                                                    <i class="fas fa-camera mr-1"></i> Foto Despacho
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted small">Sin evidencias de despacho aún.</span>
                                    @endif
                                </div>

                                {{-- Entrega --}}
                                <div class="mb-3">
                                    <p class="mb-1"><strong>Evidencia de Entrega:</strong></p>
                                    @if ($order->delivery_photo_path || $order->client_location || $order->delivery_latitude)
                                        <div>
                                            @if ($order->delivery_photo_path)
                                                <a href="{{ asset('storage/' . $order->delivery_photo_path) }}" target="_blank" class="btn btn-sm btn-outline-success mr-2 mb-1">
                                                    <i class="fas fa-image mr-1"></i> Foto Entrega
                                                </a>
                                            @endif
                                            @if ($order->delivery_latitude && $order->delivery_longitude)
                                                <p class="small text-muted mb-1">
                                                    <i class="fas fa-crosshairs text-primary"></i> <strong>Coordenadas GPS:</strong> {{ number_format($order->delivery_latitude, 6) }}, {{ number_format($order->delivery_longitude, 6) }}
                                                    <a href="https://www.google.com/maps?q={{ $order->delivery_latitude }},{{ $order->delivery_longitude }}" target="_blank" class="ml-1 small text-primary">
                                                        <i class="fas fa-external-link-alt"></i> Ver Mapa
                                                    </a>
                                                </p>
                                            @endif
                                            @if ($order->client_location)
                                                <p class="small text-muted mb-0">
                                                    <i class="fas fa-map-marker-alt text-danger"></i> <strong>Ubicación:</strong> {{ $order->client_location }}
                                                    @if (!$order->delivery_latitude)
                                                        <a href="https://www.google.com/maps?q={{ urlencode($order->client_location) }}" target="_blank" class="ml-1 small text-primary">
                                                            <i class="fas fa-external-link-alt"></i> Ver Mapa
                                                        </a>
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted small">Sin evidencias de entrega aún.</span>
                                    @endif
                                </div>

                                {{-- Devolución o Rechazo --}}
                                @if ($order->return_justification_path)
                                    <div class="alert alert-warning p-2 mt-2">
                                        <strong class="d-block"><i class="fas fa-undo mr-1"></i> Pedido Devuelto</strong>
                                        <a href="{{ asset('storage/' . $order->return_justification_path) }}" target="_blank" class="btn btn-xs btn-outline-dark mt-1">
                                            <i class="fas fa-file-pdf mr-1"></i> Ver justificación
                                        </a>
                                    </div>
                                @endif

                                @if ($order->rejection_reason)
                                    <div class="alert alert-danger p-2 mt-2">
                                        <strong class="d-block"><i class="fas fa-times-circle mr-1"></i> Pedido Rechazado</strong>
                                        <small class="d-block"><strong>Motivo:</strong> {{ $order->rejection_reason }}</small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><h4 class="card-title">Cambiar estado</h4></div>
                    <div class="card-body">
                        <form action="{{ route('pedidos.estado', $order) }}" method="POST" enctype="multipart/form-data" id="order-status-form">
                            @csrf
                            @method('PATCH')
                            
                            {{-- Selector de Estado --}}
                            <div class="form-group">
                                <label class="font-weight-bold">Nuevo estado</label>
                                <select name="order_status_id" id="order_status_id" class="form-control" required>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->id }}" 
                                                data-slug="{{ $status->slug }}" 
                                                @selected($order->order_status_id == $status->id)>
                                            {{ $status->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- SECCIÓN CONDICIONAL: DESPACHADO --}}
                            <div id="section-despachado" class="status-section border-top pt-3 mt-2" style="display: none;">
                                <h5 class="fw-bold text-success mb-2"><i class="fas fa-truck mr-1"></i> Autorización de Salida</h5>
                                
                                {{-- Firma --}}
                                <div class="form-group p-0 mb-3">
                                    <label class="font-weight-bold">Firma de autorización <span class="text-danger">*</span></label>
                                    <div class="border rounded bg-white p-1 mb-1" style="touch-action: none;">
                                        <canvas id="signature-pad" width="300" height="130" style="width: 100%; height: 130px; border: 1px dashed #ccc; background: #fff; cursor: crosshair;"></canvas>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <button type="button" class="btn btn-xs btn-outline-secondary" id="btn-clear-sig">
                                            <i class="fas fa-eraser mr-1"></i> Borrar
                                        </button>
                                        <small class="text-muted">Dibuje en la pantalla</small>
                                    </div>
                                    <input type="hidden" name="dispatch_signature" id="dispatch_signature">
                                    <div class="mt-2">
                                        <label class="small text-muted mb-1">O subir imagen de firma física:</label>
                                        <input type="file" name="signature_file" id="signature_file" class="form-control-file" accept="image/*">
                                    </div>
                                </div>

                                {{-- Foto Despacho --}}
                                <div class="form-group p-0 mb-3">
                                    <label class="font-weight-bold">Fotografía del despacho <span class="text-danger">*</span></label>
                                    <input type="file" name="dispatch_photo" id="dispatch_photo" class="form-control-file" accept="image/*" capture="environment">
                                    <small class="form-text text-muted">Fotografía de la mercancía lista o cargada.</small>
                                </div>

                                {{-- Modalidad de Pago --}}
                                <div class="form-group p-0 mb-3">
                                    <label class="font-weight-bold">Modalidad de pago <span class="text-danger">*</span></label>
                                    <div class="d-flex mt-1">
                                        <div class="custom-control custom-radio mr-3">
                                            <input type="radio" id="pm_contado_disp" name="payment_term" value="contado" class="custom-control-input payment-radio" @checked(old('payment_term', $order->payment_term ?? 'contado') == 'contado')>
                                            <label class="custom-control-label" for="pm_contado_disp">Contado</label>
                                        </div>
                                        <div class="custom-control custom-radio">
                                            <input type="radio" id="pm_credito_disp" name="payment_term" value="credito" class="custom-control-input payment-radio" @checked(old('payment_term', $order->payment_term) == 'credito')>
                                            <label class="custom-control-label" for="pm_credito_disp">Crédito</label>
                                        </div>
                                    </div>
                                </div>

                                {{-- Fecha Vencimiento (Crédito) --}}
                                <div class="form-group p-0 mb-3 credit-due-container" style="display: none;">
                                    <label class="font-weight-bold text-danger">Fecha de vencimiento (cobro) <span class="text-danger">*</span></label>
                                    <input type="date" name="payment_due_date" id="due_date_dispatch" class="form-control" value="{{ old('payment_due_date', optional($order->payment_due_date)->format('Y-m-d')) }}">
                                </div>
                            </div>

                            {{-- SECCIÓN CONDICIONAL: ENTREGADO --}}
                            <div id="section-entregado" class="status-section border-top pt-3 mt-2" style="display: none;">
                                <h5 class="fw-bold text-success mb-2"><i class="fas fa-box-check mr-1"></i> Constancia de Entrega</h5>
                                
                                {{-- Foto Entrega --}}
                                <div class="form-group p-0 mb-3">
                                    <label class="font-weight-bold">Fotografía de entrega <span class="text-danger">*</span></label>
                                    <input type="file" name="delivery_photo" id="delivery_photo" class="form-control-file" accept="image/*" capture="environment">
                                    <small class="form-text text-muted">Fotografía en el sitio del cliente.</small>
                                </div>

                                {{-- Ubicación del Cliente --}}
                                <div class="form-group p-0 mb-3">
                                    <label class="font-weight-bold">Ubicación del cliente <span class="text-danger">*</span></label>
                                    <input type="text" name="client_location" id="delivery_location" class="form-control mb-1" placeholder="Coordenadas GPS o dirección" value="{{ old('client_location', $order->client_location) }}">
                                    <button type="button" class="btn btn-xs btn-outline-primary" id="btn-get-gps">
                                        <i class="fas fa-crosshairs mr-1"></i> Detectar GPS actual
                                    </button>
                                    <div id="gps-feedback" class="small mt-1"></div>
                                </div>

                                {{-- Coordenadas GPS individuales --}}
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="small font-weight-bold text-muted">Latitud GPS</label>
                                        <input type="text" name="delivery_latitude" id="delivery_latitude" class="form-control form-control-sm" placeholder="Ej: 4.609710" value="{{ old('delivery_latitude', $order->delivery_latitude) }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="small font-weight-bold text-muted">Longitud GPS</label>
                                        <input type="text" name="delivery_longitude" id="delivery_longitude" class="form-control form-control-sm" placeholder="Ej: -74.081750" value="{{ old('delivery_longitude', $order->delivery_longitude) }}">
                                    </div>
                                </div>

                                {{-- Modalidad de pago si aún no se definió --}}
                                @if (empty($order->payment_term))
                                    <div class="form-group p-0 mb-3">
                                        <label class="font-weight-bold">Modalidad de pago <span class="text-danger">*</span></label>
                                        <div class="d-flex mt-1">
                                            <div class="custom-control custom-radio mr-3">
                                                <input type="radio" id="pm_contado_del" name="payment_term" value="contado" class="custom-control-input payment-radio" checked>
                                                <label class="custom-control-label" for="pm_contado_del">Contado</label>
                                            </div>
                                            <div class="custom-control custom-radio">
                                                <input type="radio" id="pm_credito_del" name="payment_term" value="credito" class="custom-control-input payment-radio">
                                                <label class="custom-control-label" for="pm_credito_del">Crédito</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group p-0 mb-3 credit-due-container" style="display: none;">
                                        <label class="font-weight-bold text-danger">Fecha de vencimiento <span class="text-danger">*</span></label>
                                        <input type="date" name="payment_due_date" id="due_date_delivery" class="form-control">
                                    </div>
                                @endif
                            </div>

                            {{-- SECCIÓN CONDICIONAL: COBRADO --}}
                            <div id="section-cobrado" class="status-section border-top pt-3 mt-2" style="display: none;">
                                <h5 class="fw-bold text-info mb-2"><i class="fas fa-cash-register mr-1"></i> Registro de Cobro</h5>
                                
                                {{-- Responsable del Cobro --}}
                                <div class="form-group p-0 mb-3">
                                    <label class="font-weight-bold">Responsable del cobro <span class="text-danger">*</span></label>
                                    <select name="payment_responsible_id" id="payment_responsible_id" class="form-control">
                                        <option value="">Seleccione el responsable...</option>
                                        @foreach ($users ?? [] as $user)
                                            <option value="{{ $user->id }}" @selected(old('payment_responsible_id', auth()->id()) == $user->id)>
                                                {{ $user->name }} ({{ $user->role->name ?? 'Usuario' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Soporte de Cobro --}}
                                <div class="form-group p-0 mb-3">
                                    <label class="font-weight-bold">Soporte de cobro <span class="text-danger">*</span></label>
                                    <input type="file" name="payment_support" id="payment_support" class="form-control-file" accept="image/*,application/pdf">
                                    <small class="form-text text-muted">Comprobante de consignación o recibo (PDF o imagen).</small>
                                </div>
                            </div>

                            {{-- SECCIÓN CONDICIONAL: DEVUELTO --}}
                            <div id="section-devuelto" class="status-section border-top pt-3 mt-2" style="display: none;">
                                <h5 class="fw-bold text-warning mb-2"><i class="fas fa-undo mr-1"></i> Registro de Devolución</h5>
                                <div class="form-group p-0 mb-3">
                                    <label class="font-weight-bold">Archivo de justificación <span class="text-danger">*</span></label>
                                    <input type="file" name="return_justification_file" id="return_justification_file" class="form-control-file" accept="image/*,application/pdf,.doc,.docx">
                                    <small class="form-text text-muted">Constancia o soporte de la devolución.</small>
                                </div>
                                <div class="form-group p-0 mb-3">
                                    <label class="font-weight-bold">Motivo detallado</label>
                                    <textarea name="return_reason" class="form-control" rows="2" placeholder="Describa el motivo de la devolución..."></textarea>
                                </div>
                            </div>

                            {{-- SECCIÓN CONDICIONAL: RECHAZADO --}}
                            <div id="section-rechazado" class="status-section border-top pt-3 mt-2" style="display: none;">
                                <h5 class="fw-bold text-danger mb-2"><i class="fas fa-times-circle mr-1"></i> Justificación de Rechazo</h5>
                                <div class="form-group p-0 mb-3">
                                    <label class="font-weight-bold">Motivo de rechazo <span class="text-danger">*</span></label>
                                    <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="3" placeholder="Indique la justificación obligatoria..."></textarea>
                                </div>
                            </div>

                            {{-- Observación general --}}
                            <div class="form-group mt-2">
                                <label>Observación general (opcional)</label>
                                <textarea name="observation" class="form-control" rows="2"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block mt-3" id="btn-submit-status">
                                <i class="fas fa-save mr-1"></i> Actualizar estado
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card"><div class="card-header"><h4 class="card-title">Historial de trazabilidad</h4></div><div class="card-body table-responsive">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Anterior</th><th>Nuevo</th><th>Usuario</th><th>Observación</th></tr></thead>
                <tbody>
                    @forelse ($order->histories as $history)
                        <tr><td>{{ $history->changed_at->format('Y-m-d H:i') }}</td><td>{{ $history->previousStatus?->name ?? 'Inicio' }}</td><td>{{ $history->newStatus?->name ?? 'Desconocido' }}</td><td>{{ $history->user?->name ?? 'Sistema' }}</td><td>{{ $history->observation }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No hay trazabilidad registrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusSelect = document.getElementById('order_status_id');
    const sections = {
        'pedido-despachado': document.getElementById('section-despachado'),
        'pedido-entregado': document.getElementById('section-entregado'),
        'pedido-cobrado': document.getElementById('section-cobrado'),
        'pedido-devuelto': document.getElementById('section-devuelto'),
        'pedido-rechazado': document.getElementById('section-rechazado')
    };

    function togglePaymentRadio() {
        const selectedRadio = document.querySelector('.payment-radio:checked:not(:disabled)');
        const isCredit = selectedRadio && selectedRadio.value === 'credito';
        document.querySelectorAll('.credit-due-container').forEach(container => {
            const isVisibleSection = $(container).closest('.status-section').is(':visible');
            if (isVisibleSection && isCredit) {
                container.style.display = 'block';
                $(container).find(':input').prop('disabled', false);
            } else {
                container.style.display = 'none';
                $(container).find(':input').prop('disabled', true).removeClass('is-invalid');
                $(container).find('.invalid-feedback').remove();
            }
        });
    }

    document.querySelectorAll('.payment-radio').forEach(r => r.addEventListener('change', togglePaymentRadio));

    function updateActiveSection() {
        if (!statusSelect) return;
        const selectedOpt = statusSelect.options[statusSelect.selectedIndex];
        const slug = selectedOpt ? selectedOpt.getAttribute('data-slug') : '';

        // Reset previous validation errors across sections
        if (window.jQuery && $('#order-status-form').data('validator')) {
            const validator = $('#order-status-form').validate();
            validator.resetForm();
            $('#order-status-form').find('.is-invalid').removeClass('is-invalid');
            $('#order-status-form').find('.border-danger').removeClass('border-danger');
        }

        // Hide all sections and disable inputs to prevent corrupting HTTP payloads
        Object.values(sections).forEach(sec => {
            if (sec) {
                sec.style.display = 'none';
                $(sec).find(':input').prop('disabled', true);
            }
        });

        // Activate matching section and enable its inputs
        const activeSec = sections[slug];
        if (activeSec) {
            activeSec.style.display = 'block';
            $(activeSec).find(':input').prop('disabled', false);
            togglePaymentRadio();
        }
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', updateActiveSection);
        updateActiveSection();
    }

    // HTML5 Canvas Signature Pad
    const canvas = document.getElementById('signature-pad');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let drawing = false;

        function resize() {
            const rect = canvas.getBoundingClientRect();
            if (rect.width > 0) {
                canvas.width = rect.width;
                canvas.height = 130;
                ctx.strokeStyle = '#222';
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
            }
        }
        resize();
        window.addEventListener('resize', resize);

        function pos(e) {
            const rect = canvas.getBoundingClientRect();
            const cx = e.touches ? e.touches[0].clientX : e.clientX;
            const cy = e.touches ? e.touches[0].clientY : e.clientY;
            return { x: cx - rect.left, y: cy - rect.top };
        }

        function start(e) { 
            drawing = true; 
            const p = pos(e); 
            ctx.beginPath(); 
            ctx.moveTo(p.x, p.y); 
            if (e.touches) e.preventDefault(); 
        }

        function move(e) {
            if (!drawing) return;
            const p = pos(e);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            const sigInput = document.getElementById('dispatch_signature');
            if (sigInput) {
                sigInput.value = canvas.toDataURL('image/png');
                $('#signature_file').removeClass('is-invalid');
                $('#signature_file-error').remove();
            }
            if (e.touches) e.preventDefault();
        }

        function end() { 
            drawing = false; 
            const sigInput = document.getElementById('dispatch_signature');
            if (sigInput && sigInput.value && $('#order-status-form').data('validator')) {
                $('#signature_file').valid();
            }
        }

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        window.addEventListener('touchend', end);

        const btnClear = document.getElementById('btn-clear-sig');
        if (btnClear) {
            btnClear.addEventListener('click', function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                const sigInput = document.getElementById('dispatch_signature');
                if (sigInput) sigInput.value = '';
                if ($('#order-status-form').data('validator') && $('#order-status-form').data('validator').submitted['signature_file']) {
                    $('#signature_file').valid();
                }
            });
        }
    }

    // Geolocation API
    const btnGps = document.getElementById('btn-get-gps');
    const locInput = document.getElementById('delivery_location');
    const latInput = document.getElementById('delivery_latitude');
    const lngInput = document.getElementById('delivery_longitude');
    const gpsFeedback = document.getElementById('gps-feedback');

    if (btnGps && locInput) {
        btnGps.addEventListener('click', function () {
            if (!navigator.geolocation) {
                alert('Geolocalización no soportada en este navegador.');
                return;
            }
            btnGps.disabled = true;
            btnGps.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Obteniendo GPS...';
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    const coords = pos.coords.latitude.toFixed(6) + ', ' + pos.coords.longitude.toFixed(6);
                    locInput.value = coords;
                    if (latInput) latInput.value = pos.coords.latitude.toFixed(8);
                    if (lngInput) lngInput.value = pos.coords.longitude.toFixed(8);
                    if (gpsFeedback) {
                        gpsFeedback.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> GPS: ' + coords + ' (±' + Math.round(pos.coords.accuracy) + 'm)</span>';
                    }
                    btnGps.disabled = false;
                    btnGps.innerHTML = '<i class="fas fa-crosshairs mr-1"></i> Actualizar GPS';

                    if (window.jQuery && $('#order-status-form').data('validator')) {
                        $(locInput).valid();
                        if (latInput) $(latInput).valid();
                        if (lngInput) $(lngInput).valid();
                    }
                },
                function (err) {
                    btnGps.disabled = false;
                    btnGps.innerHTML = '<i class="fas fa-crosshairs mr-1"></i> Detectar GPS actual';
                    alert('No se pudo obtener el GPS automáticamente (' + err.message + '). Ingrese la dirección o coordenadas manualmente.');
                    locInput.focus();
                },
                { enableHighAccuracy: true, timeout: 8000 }
            );
        });
    }

    // Order Status Form jQuery Validate
    if (window.jQuery && jQuery.fn.validate) {
        $('#order-status-form').validate({
            rules: {
                order_status_id: {
                    required: true
                },
                signature_file: {
                    required: {
                        depends: function () {
                            if (!$('#section-despachado').is(':visible')) return false;
                            const sigVal = $('#dispatch_signature').val();
                            return !sigVal || sigVal.trim() === '';
                        }
                    }
                },
                dispatch_photo: {
                    required: {
                        depends: function () {
                            return $('#section-despachado').is(':visible');
                        }
                    }
                },
                payment_term: {
                    required: {
                        depends: function () {
                            return $('#section-despachado').is(':visible') || $('#section-entregado').is(':visible');
                        }
                    }
                },
                payment_due_date: {
                    required: {
                        depends: function (element) {
                            return $(element).is(':visible');
                        }
                    },
                    date: true
                },
                delivery_photo: {
                    required: {
                        depends: function () {
                            return $('#section-entregado').is(':visible');
                        }
                    }
                },
                client_location: {
                    required: {
                        depends: function () {
                            return $('#section-entregado').is(':visible');
                        }
                    }
                },
                delivery_latitude: {
                    coordLatitude: true
                },
                delivery_longitude: {
                    coordLongitude: true
                },
                payment_responsible_id: {
                    required: {
                        depends: function () {
                            return $('#section-cobrado').is(':visible');
                        }
                    }
                },
                payment_support: {
                    required: {
                        depends: function () {
                            return $('#section-cobrado').is(':visible');
                        }
                    }
                },
                return_justification_file: {
                    required: {
                        depends: function () {
                            return $('#section-devuelto').is(':visible');
                        }
                    }
                },
                rejection_reason: {
                    required: {
                        depends: function () {
                            return $('#section-rechazado').is(':visible');
                        }
                    },
                    minlength: 3
                }
            },
            messages: {
                order_status_id: {
                    required: 'Por favor, seleccione el nuevo estado del pedido.'
                },
                signature_file: {
                    required: 'La firma de autorización es obligatoria (dibuje en el recuadro o adjunte una imagen).'
                },
                dispatch_photo: {
                    required: 'La fotografía del despacho es obligatoria.'
                },
                payment_term: {
                    required: 'La modalidad de pago es obligatoria.'
                },
                payment_due_date: {
                    required: 'La fecha de vencimiento es obligatoria para crédito.',
                    date: 'Por favor, ingrese una fecha válida.'
                },
                delivery_photo: {
                    required: 'La fotografía de entrega es obligatoria.'
                },
                client_location: {
                    required: 'La ubicación del cliente es obligatoria.'
                },
                delivery_latitude: {
                    coordLatitude: 'Por favor, ingrese una latitud válida (-90 a 90).'
                },
                delivery_longitude: {
                    coordLongitude: 'Por favor, ingrese una longitud válida (-180 a 180).'
                },
                payment_responsible_id: {
                    required: 'El responsable del cobro es obligatorio.'
                },
                payment_support: {
                    required: 'El archivo de soporte de cobro es obligatorio.'
                },
                return_justification_file: {
                    required: 'El archivo de justificación de devolución es obligatorio.'
                },
                rejection_reason: {
                    required: 'La justificación de rechazo es obligatoria.',
                    minlength: 'La justificación debe tener al menos 3 caracteres.'
                }
            },
            submitHandler: function (form) {
                form.submit();
            }
        });
    }
});
</script>
@endpush
