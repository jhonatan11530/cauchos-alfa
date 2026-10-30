@extends('admin.layout.plantilla')

@section('banner')
    <div>
        <h2 class="text-white pb-2 fw-bold">Configuración del Sistema</h2>
        <h5 class="text-white op-7 mb-2">Administración de parámetros y prompt de Inteligencia Artificial</h5>
    </div>
@endsection

@section('contenido')
    <div class="page-inner mt--5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
                        <div class="card-title mb-1">
                            <i class="fas fa-robot text-primary mr-2"></i> Prompt de Gemini (Estudio Fotográfico)
                        </div>
                        <div>
                            <span class="badge badge-success mr-1">
                                <i class="fas fa-database mr-1"></i> Base de Datos
                            </span>
                            <span class="badge badge-info">
                                <i class="fas fa-bolt mr-1"></i> Caché Sincronizada
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light border" style="text-transform: none;">
                            <div class="d-flex">
                                <div class="mr-3">
                                    <i class="fas fa-info-circle fa-2x text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="font-weight-bold mb-1">¿Cómo se utiliza este prompt?</h6>
                                    <p class="mb-0 text-muted" style="font-size: 0.9rem;">
                                        Este texto es enviado a Gemini Web en el módulo de productos para automatizar la mejora de fotografías. Define las instrucciones para corregir perspectiva, lograr nivelación a 0°, sombras suaves y fondo blanco puro de catálogo. Los cambios aquí guardados actualizan inmediatamente la memoria y la caché del sistema.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <form action="{{ route('settings.update') }}" method="POST" id="promptForm">
                            @csrf
                            @method('PUT')

                            <div class="form-group px-0" style="text-transform: none;">
                                <label for="prompt_gemini" class="font-weight-bold">
                                    Contenido del Prompt:
                                </label>
                                <textarea
                                    name="prompt_gemini"
                                    id="prompt_gemini"
                                    rows="9"
                                    class="form-control @error('prompt_gemini') is-invalid @enderror"
                                    style="text-transform: none !important; font-family: Consolas, 'Liberation Mono', Menlo, Courier, monospace; font-size: 0.92rem; line-height: 1.6; resize: vertical;"
                                    required
                                >{{ old('prompt_gemini', $activePrompt) }}</textarea>

                                @error('prompt_gemini')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror

                                <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap text-muted" style="font-size: 0.85rem;">
                                    <div>
                                        <i class="fas fa-keyboard mr-1"></i>
                                        <span id="charCount">0</span> caracteres &bull; <span id="wordCount">0</span> palabras
                                    </div>
                                    @if ($setting && $setting->updated_at)
                                        <div>
                                            <i class="fas fa-history mr-1"></i>
                                            Última actualización: <strong>{{ $setting->updated_at->format('d/m/Y H:i:s') }}</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center flex-wrap pt-3 border-top">
                                <div class="mb-2">
                                    <button type="submit" class="btn btn-primary mr-2">
                                        <i class="fas fa-save mr-1"></i> Guardar Cambios
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary mr-2" id="btnCopyPrompt">
                                        <i class="fas fa-copy mr-1"></i> Copiar Texto
                                    </button>
                                    <button type="button" class="btn btn-outline-warning" id="btnLoadCanonical">
                                        <i class="fas fa-undo mr-1"></i> Cargar Valor por Defecto
                                    </button>
                                </div>

                                <div class="mb-2">
                                    <a href="{{ route('dashboard') }}" class="btn btn-light border">
                                        Volver al Dashboard
                                    </a>
                                </div>
                            </div>
                        </form>

                        <form action="{{ route('settings.reset') }}" method="POST" id="resetForm" class="d-none">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const textarea = document.getElementById('prompt_gemini');
        const charCount = document.getElementById('charCount');
        const wordCount = document.getElementById('wordCount');
        const btnCopy = document.getElementById('btnCopyPrompt');
        const btnLoadCanonical = document.getElementById('btnLoadCanonical');
        const canonicalFallback = @json($canonicalFallback);

        function updateCounts() {
            if (!textarea) return;
            const text = textarea.value || '';
            charCount.textContent = text.length;
            const words = text.trim().split(/\s+/).filter(Boolean);
            wordCount.textContent = words.length;
        }

        if (textarea) {
            textarea.addEventListener('input', updateCounts);
            updateCounts();
        }

        if (btnCopy) {
            btnCopy.addEventListener('click', function () {
                if (!textarea) return;
                navigator.clipboard.writeText(textarea.value).then(function () {
                    if (window.toastr) {
                        toastr.info('Prompt copiado al portapapeles.');
                    } else {
                        alert('Prompt copiado al portapapeles.');
                    }
                }).catch(function () {
                    textarea.select();
                    document.execCommand('copy');
                    if (window.toastr) {
                        toastr.info('Prompt copiado al portapapeles.');
                    }
                });
            });
        }

        if (btnLoadCanonical) {
            btnLoadCanonical.addEventListener('click', function () {
                if (confirm('¿Deseas reemplazar el contenido actual con el prompt canónico predeterminado de fábrica? (Recuerda presionar "Guardar Cambios" para aplicarlo).')) {
                    textarea.value = canonicalFallback;
                    updateCounts();
                    if (window.toastr) {
                        toastr.warning('Se ha cargado el prompt canónico en el editor. Haz clic en "Guardar Cambios" para confirmar.');
                    }
                }
            });
        }
    });
</script>
@endpush

