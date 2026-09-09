@extends('admin.layout.plantilla')

@section('banner')
    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row">
        <div>
            <h2 class="text-white pb-2 fw-bold">Mensajes de WhatsApp</h2>
            <h5 class="text-white op-7 mb-2">Envio de mensajes y enlaces de catalogos a los vendedores (OpenWA)</h5>
        </div>
    </div>
@endsection

@section('contenido')
    <div class="page-inner mt--5">
        <div class="row">
            <!-- Estado de la sesion -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Sesion de WhatsApp</h4>
                    </div>
                    <div class="card-body text-center" id="session-box">
                        <p id="session-text">Consultando estado del gateway OpenWA...</p>
                        <div class="row">
                            <div class="col-sm-12">
                                <img id="session-qr" src="" alt="QR" class="img-fluid d-none mb-3" style="max-width:240px;">
                            </div>
                            <div class="col-sm-12">
                                <button id="btn-refresh" class="btn btn-sm btn-primary">Actualizar</button>
                                <form method="POST" action="{{ route('whatsapp.restart') }}" class="mt-2"
                                    onsubmit="return confirm('¿Reiniciar la sesion y generar un nuevo QR?')">
                                    @csrf
                                    <button class="btn btn-sm btn-warning">Reiniciar sesion (nuevo QR)</button>
                                </form>
                                <form method="POST" action="{{ route('whatsapp.logout') }}" class="mt-2"
                                    onsubmit="return confirm('¿Cerrar la sesion de WhatsApp?')">
                                    @csrf
                                    <button class="btn btn-sm btn-danger">Cerrar sesion</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <!-- Mensaje libre -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Enviar mensaje</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('whatsapp.send') }}">
                            @csrf
                            <div class="form-group">
                                <label>Numeros destinatarios</label>
                                <textarea name="numbers" class="form-control @error('numbers') is-invalid @enderror"
                                    rows="2" placeholder="04141234567, +584241234567 (separados por coma o salto de linea)"
                                    required>{{ old('numbers') }}</textarea>
                                @error('numbers')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-group">
                                <label>Mensaje</label>
                                <textarea name="message" class="form-control" rows="3"
                                    placeholder="Texto del mensaje">{{ old('message') }}</textarea>
                            </div>
                            <button class="btn btn-success"><i class="fab fa-whatsapp"></i> Enviar</button>
                        </form>
                    </div>
                </div>

                <!-- Envio de catalogo a vendedores -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0">Enviar catalogo a vendedores</h4>
                            <a href="{{ route('whatsapp.templates.index') }}" class="btn btn-sm btn-outline-primary">Gestionar plantillas</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('whatsapp.catalog') }}">
                            @csrf
                            <div class="form-group">
                                <label>Catalogo (se enviara como enlace publico)</label>
                                <select name="catalog_id" class="form-control @error('catalog_id') is-invalid @enderror"
                                    required>
                                    <option value="">-- Selecciona un catalogo --</option>
                                    @foreach ($catalogs as $catalog)
                                        <option value="{{ $catalog->id }}" @selected(old('catalog_id') == $catalog->id)>
                                            {{ $catalog->name }} {{ $catalog->is_active ? '' : '(inactivo)' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('catalog_id')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-group">
                                <p class="text-muted mb-0">Se enviara a todos los vendedores activos con telefono
                                    registrado.</p>
                            </div>
                            <div class="form-group">
                                <label for="message-template">Plantilla de mensaje</label>
                                <select id="message-template" class="form-control">
                                    <option value="">-- Selecciona una plantilla --</option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" data-message="{{ $template->message }}">
                                            {{ $template->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Puedes editar el mensaje después de cargar la plantilla.</small>
                            </div>
                            <div class="form-group">
                                <label>Mensaje que acompana el catalogo</label>
                                <textarea id="catalog-message" name="message" class="form-control" rows="2"
                                    placeholder="Hola, te comparto nuestro catalogo actualizado">{{ old('message') }}</textarea>
                            </div>
                            <button class="btn btn-success">
                                <i class="fab fa-whatsapp"></i> Enviar catalogo
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            const statusUrl = '{{ url('whatsapp/status') }}';
            const text = document.getElementById('session-text');
            const qr = document.getElementById('session-qr');

            async function refresh() {
                text.textContent = 'Consultando estado del gateway OpenWA...';
                qr.classList.add('d-none');
                try {
                    const res = await fetch(statusUrl);
                    const data = await res.json();
                    if (data.ready) {
                        text.innerHTML = '<span class="text-success fw-bold">Sesion activa</span>';
                    } else if (data.qr) {
                        text.innerHTML = 'Escanea este QR desde WhatsApp (dispositivos vinculados):';
                        qr.src = data.qr;
                        qr.classList.remove('d-none');
                    } else if (data.starting) {
                        text.innerHTML = '<span class="text-info">Iniciando WhatsApp... el QR aparecera en unos segundos.</span>';
                    } else if (data.error) {
                        text.innerHTML = '<span class="text-danger">Error:</span> ' + data.error +
                            '<br><small>Se reintenta automaticamente, o pulsa "Reiniciar sesion".</small>';
                    } else {
                        text.innerHTML = '<span class="text-danger">Sin sesion.</span> ' +
                            'Verifica que el gateway OpenWA este iniciado y que la sesion configurada exista.';
                    }
                } catch (e) {
                    text.innerHTML = '<span class="text-danger">Gateway OpenWA no disponible en la URL configurada.</span>';
                }
            }

            document.getElementById('btn-refresh').addEventListener('click', refresh);

            document.getElementById('message-template').addEventListener('change', function() {
                const option = this.options[this.selectedIndex];
                const message = option.dataset.message || '';
                const textarea = document.getElementById('catalog-message');

                if (message) {
                    textarea.value = message;
                }
            });

            refresh();
            setInterval(refresh, 10000);
        </script>
    @endpush
@endsection
