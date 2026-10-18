@extends('admin.layout.plantilla')
@section('banner')
    <div>
        <h2 class="text-white pb-2 fw-bold">Productos</h2>
        <h5 class="text-white op-7 mb-2">{{ $product->exists ? 'Editar producto' : 'Crear producto' }}</h5>
    </div>
@endsection
@section('contenido')
    <div class="page-inner mt--5">
        <div class="card">
            <div class="card-body">
                <form action="{{ $product->exists ? route('productos.update', $product) : route('productos.store') }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                    @if ($product->exists)
                        @method('PUT')
                    @endif
                    <div class="row">
                        <div class="form-group col-md-12"><label>Nombre</label><input name="name" class="form-control"
                                value="{{ old('name', $product->name) }}" required></div>
                        <div class="form-group col-md-6"><label>Referencia</label><input name="reference"
                                class="form-control" value="{{ old('reference', $product->reference) }}"></div>
                        <div class="form-group col-md-3"><label>Categoría</label><select name="category_id"
                                class="form-control">
                                <option value="">Sin categoría</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3"><label>Disponibilidad</label><select name="availability"
                                class="form-control">
                                @foreach ($availabilityOptions as $option)
                                    <option value="{{ $option }}" @selected(old('availability', $product->availability ?: 'disponible') === $option)>{{ ucfirst($option) }}
                                    </option>
                                @endforeach
                            </select></div>
                        <!-- Switch para Fondo Blanco de Estudio con IA -->
                        <div class="col-md-12 mb-3">
                            <div class="card border border-primary" style="background-color: #f4f8ff;">
                                <div class="card-body py-3 px-3">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                                        <div class="d-flex align-items-center mb-2 mb-md-0">
                                            <div class="mr-3 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; min-width: 40px;">
                                                <i class="fas fa-magic"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold text-primary">Fondo blanco de estudio con IA</h6>
                                                <small class="text-muted">Aísla el producto y aplica fondo blanco puro (#ffffff) de catálogo a todas las fotos (principal y galería) directamente desde tu navegador.</small>
                                            </div>
                                        </div>
                                        <div class="custom-control custom-switch ml-md-3">
                                            <input type="checkbox" class="custom-control-input" id="autoWhiteBgSwitch" checked>
                                            <label class="custom-control-label fw-bold" for="autoWhiteBgSwitch" id="autoWhiteBgLabel" style="cursor: pointer;">Activo</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($product->exists)
                        <div class="col-md-12 mb-3">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <button type="button" id="btnEnhanceAll" class="btn btn-outline-secondary font-weight-bold" onclick="enhanceAllImages()">
                                    <i class="fas fa-laptop-code mr-1"></i> Mejorar imagen con IA LOCAL
                                </button>
                                <button type="button" id="btnGeminiExtension" class="btn btn-primary font-weight-bold ml-md-2" onclick="generateWithGeminiExtension()">
                                    <i class="fab fa-google mr-1"></i> Generar todas con Gemini Pro (Automático)
                                </button>
                            </div>
                            <div id="enhanceAllStatus" class="mt-2 text-primary small d-none font-weight-bold">
                                <span class="spinner-border spinner-border-sm mr-1"></span>
                                <span id="enhanceAllStatusText">Iniciando proceso...</span>
                            </div>
                            <div id="geminiExtensionStatus" class="mt-2 text-primary small d-none font-weight-bold">
                                <span class="spinner-border spinner-border-sm mr-1"></span>
                                <span id="geminiExtensionStatusText">Conectando con Gemini Web Pro...</span>
                            </div>
                        </div>
                        @endif

                        <div class="form-group col-md-12">
                            <label class="fw-bold">Imagen principal</label>
                            <input type="file" name="image" id="mainImageInput" class="form-control" accept="image/*">
                            <div id="mainImageStatus" class="mt-2 text-primary small d-none font-weight-bold">
                                <span class="spinner-border spinner-border-sm mr-1" role="status"></span>
                                <span id="mainImageStatusText">Procesando fondo blanco con IA...</span>
                            </div>
                            <div id="mainImagePreviewArea" class="mt-2 d-none">
                                <div class="d-flex align-items-center border rounded p-2 bg-white" style="max-width: 360px;">
                                    <img id="mainImagePreviewImg" src="" style="height: 75px; width: 75px; object-fit: contain; background: #fff;" class="border rounded mr-3" alt="Vista previa">
                                    <div>
                                        <span class="badge badge-success mb-1" id="mainImageBadge">
                                            <i class="fas fa-check-circle mr-1"></i> Fondo blanco aplicado
                                        </span>
                                        <div class="small text-muted text-truncate" id="mainImageFileName" style="max-width: 200px;"></div>
                                        <button type="button" class="btn btn-xs btn-link text-secondary p-0 mt-1" id="btnRestoreMainImage">
                                            <i class="fas fa-undo mr-1"></i> Restaurar original
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group col-md-12">
                            <label class="fw-bold">Galería de imágenes secundarias (puedes seleccionar varias)</label>
                            <input type="file" name="images[]" id="galleryImagesInput" class="form-control" multiple accept="image/*">
                            @error('images.*')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                            <div id="galleryImagesStatus" class="mt-2 text-primary small d-none font-weight-bold">
                                <span class="spinner-border spinner-border-sm mr-1" role="status"></span>
                                <span id="galleryImagesStatusText">Procesando galería con IA...</span>
                            </div>
                            <div id="galleryImagesPreviewArea" class="mt-2 d-none">
                                <div class="small fw-bold text-muted mb-2">Imágenes de galería preparadas con fondo blanco:</div>
                                <div id="galleryPreviewContainer" class="d-flex flex-wrap gap-2"></div>
                            </div>
                        </div>
                        <div class="form-group col-md-6"><label>Descripción</label>
                            <textarea name="description" class="form-control">{{ old('description', $product->description) }}</textarea>
                        </div>
                        <div class="form-group col-md-6"><label>Características</label>
                            <textarea name="features" class="form-control">{{ old('features', $product->features) }}</textarea>
                        </div>
                    </div>
                    <div class="form-group"><label><input type="checkbox" name="is_active" value="1"
                                {{ old('is_active', $product->is_active ?? true) ? 'checked' : '' }}> Activo</label></div>
                    <button type="submit" id="btnSubmitProduct" class="btn btn-primary">Guardar</button>
                    <a href="{{ route('productos.index') }}" class="btn btn-secondary">Cancelar</a>
                </form>

                @if ($product->exists && ($product->images->isNotEmpty() || $product->image_path))
                    <div class="mt-3">
                        <label class="fw-bold">Imágenes actuales</label>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($product->image_path)
                                <div class="border rounded p-1 text-center">
                                    <img src="{{ \App\Models\Product::getStorageUrl($product->image_path) }}"
                                        id="existing_main_img"
                                        data-url="{{ \App\Models\Product::getStorageUrl($product->image_path) }}"
                                        style="height:80px;width:80px;object-fit:cover;" alt="Imagen principal">
                                    <div class="small text-muted">Principal</div>
                                    <div id="status_existing_main" class="small fw-bold mt-1 text-primary"></div>
                                </div>
                            @endif
                            @foreach ($product->images as $image)
                                <div class="border rounded p-1 text-center">
                                    <img src="{{ \App\Models\Product::getStorageUrl($image->path) }}"
                                        class="existing_gallery_img"
                                        data-id="{{ $image->id }}"
                                        data-url="{{ \App\Models\Product::getStorageUrl($image->path) }}"
                                        style="height:80px;width:80px;object-fit:cover;" alt="Imagen">
                                    <div id="status_existing_gallery_{{ $image->id }}" class="small fw-bold mt-1 text-primary"></div>
                                    <form action="{{ route('productos.images.destroy', [$product, $image]) }}"
                                        method="POST" onsubmit="return confirm('¿Eliminar esta imagen?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-link text-danger p-0">Eliminar</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script type="module">
    let AutoModel = null;
    let AutoProcessor = null;
    let RawImage = null;
    let transformersEnv = null;
    let transformersModulePromise = null;

    async function ensureTransformersLoaded() {
        if (AutoModel && AutoProcessor && RawImage) {
            return { AutoModel, AutoProcessor, RawImage, env: transformersEnv };
        }
        if (transformersModulePromise) return await transformersModulePromise;

        transformersModulePromise = (async () => {
            const cdnUrls = [
                'https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.3.3',
                'https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.3.3/+esm'
            ];
            let lastErr = null;
            for (const url of cdnUrls) {
                try {
                    const mod = await import(url);
                    AutoModel = mod.AutoModel;
                    AutoProcessor = mod.AutoProcessor;
                    RawImage = mod.RawImage;
                    transformersEnv = mod.env;
                    if (transformersEnv) {
                        transformersEnv.allowLocalModels = false;
                        transformersEnv.useBrowserCache = true;
                    }
                    return { AutoModel, AutoProcessor, RawImage, env: transformersEnv };
                } catch (err) {
                    console.warn('⚠️ Fallo cargando Transformers.js desde ' + url + ':', err);
                    lastErr = err;
                }
            }
            transformersModulePromise = null;
            throw lastErr || new Error('No se pudo cargar @huggingface/transformers desde CDN');
        })();

        return await transformersModulePromise;
    }

    let activeProcessCount = 0;
    let originalMainFile = null;

    const btnSubmit = document.getElementById('btnSubmitProduct');
    const autoSwitch = document.getElementById('autoWhiteBgSwitch');
    const switchLabel = document.getElementById('autoWhiteBgLabel');
    const mainInput = document.getElementById('mainImageInput');
    const mainStatus = document.getElementById('mainImageStatus');
    const mainStatusText = document.getElementById('mainImageStatusText');
    const mainPreviewArea = document.getElementById('mainImagePreviewArea');
    const mainPreviewImg = document.getElementById('mainImagePreviewImg');
    const mainBadge = document.getElementById('mainImageBadge');
    const mainFileName = document.getElementById('mainImageFileName');
    const btnRestoreMain = document.getElementById('btnRestoreMainImage');

    const galleryInput = document.getElementById('galleryImagesInput');
    const galleryStatus = document.getElementById('galleryImagesStatus');
    const galleryStatusText = document.getElementById('galleryImagesStatusText');
    const galleryPreviewArea = document.getElementById('galleryImagesPreviewArea');
    const galleryContainer = document.getElementById('galleryPreviewContainer');

    // Manejar cambio de estado del interruptor
    if (autoSwitch && switchLabel) {
        autoSwitch.addEventListener('change', function() {
            switchLabel.textContent = this.checked ? 'Activo' : 'Desactivado';
            if (!this.checked && window.toastr) {
                toastr.info('Fondo blanco automático desactivado. Las imágenes se subirán originales.');
            }
        });
    }

    function setProcessingState(isBusy, message) {
        if (isBusy) {
            activeProcessCount++;
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = `<span class="spinner-border spinner-border-sm mr-1"></span> ${message || 'Procesando imágenes con IA...'}`;
            }
        } else {
            activeProcessCount = Math.max(0, activeProcessCount - 1);
            if (activeProcessCount === 0 && btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = 'Guardar';
            }
        }
    }

    // ============================================================================
    // MOTOR DE SEGMENTACIÓN IA: TRANSFORMERS.JS v3 + BRIA RMBG-1.4
    // WebGPU primario (fp32) con fallback automático a WebAssembly (q8)
    // ============================================================================
    let rmbgModelInstance = null;
    let rmbgProcessorInstance = null;
    let rmbgLoadingPromise = null;
    let activeBackendName = 'Detectando...';

    async function getRMBGModelAndProcessor(onProgress) {
        if (rmbgModelInstance && rmbgProcessorInstance) {
            return { model: rmbgModelInstance, processor: rmbgProcessorInstance };
        }
        if (rmbgLoadingPromise) return await rmbgLoadingPromise;

        rmbgLoadingPromise = (async () => {
            await ensureTransformersLoaded();
            const modelId = 'briaai/RMBG-1.4';
            const isWebGPUSupported = typeof navigator !== 'undefined' && 'gpu' in navigator && Boolean(navigator.gpu);

            const progressCallback = (info) => {
                if (!onProgress) return;
                if (info.status === 'progress' || info.status === 'download') {
                    const fName = info.file ? info.file.split('/').pop() : 'pesos';
                    const pct = typeof info.progress === 'number'
                        ? Math.round(info.progress)
                        : (info.total ? Math.round((info.loaded / info.total) * 100) : '');
                    onProgress(`Cargando IA RMBG-1.4 (${fName} ${pct ? pct + '%' : ''})...`);
                } else if (info.status === 'done') {
                    onProgress('IA RMBG-1.4 compilada en memoria.');
                }
            };

            let model = null;
            let processor = null;

            // 1. Aceleración por hardware primaria: WebGPU (fp32)
            if (isWebGPUSupported) {
                try {
                    if (onProgress) onProgress('Iniciando aceleración WebGPU para RMBG-1.4...');
                    model = await AutoModel.from_pretrained(modelId, {
                        device: 'webgpu',
                        dtype: 'fp32',
                        progress_callback: progressCallback
                    });
                    processor = await AutoProcessor.from_pretrained(modelId);
                    activeBackendName = 'WebGPU (Hardware GPU)';
                    console.log('✅ Transformers.js ejecutando RMBG-1.4 en WebGPU.');
                } catch (gpuErr) {
                    console.warn('⚠️ WebGPU no disponible o rechazado. Activando fallback a WebAssembly (WASM):', gpuErr);
                }
            }

            // 2. Fallback secundario: WebAssembly CPU con pesos cuantizados (q8, ~43MB)
            if (!model) {
                if (onProgress) onProgress('Iniciando motor WebAssembly CPU (q8 ligero)...');
                try {
                    model = await AutoModel.from_pretrained(modelId, {
                        device: 'wasm',
                        dtype: 'q8',
                        progress_callback: progressCallback
                    });
                    processor = await AutoProcessor.from_pretrained(modelId);
                    activeBackendName = 'WebAssembly CPU (q8)';
                    console.log('✅ Transformers.js ejecutando RMBG-1.4 en WebAssembly.');
                } catch (wasmErr) {
                    console.error('❌ Error crítico al cargar RMBG-1.4 en WebAssembly:', wasmErr);
                    throw wasmErr;
                }
            }

            rmbgModelInstance = model;
            rmbgProcessorInstance = processor;
            return { model, processor };
        })();

        return await rmbgLoadingPromise;
    }

    // Pre-calentar modelo ligero en segundo plano tras carga inicial de la página
    setTimeout(() => {
        getRMBGModelAndProcessor().then(() => {
            console.log('🚀 Pipeline RMBG-1.4 precalentado en memoria (' + activeBackendName + ').');
        }).catch(() => {});
    }, 1200);

    // ============================================================================
    // COMPOSICIÓN PURA SOBRE FONDO BLANCO (#FFFFFF) SIN FILTROS DE COLOR NI BRILLO
    // Conserva el 100% de los colores, brillo y textura originales del producto
    // ============================================================================
    function processSatinRubberStudio(sourceCanvas, alphaMaskData) {
        const W = sourceCanvas.width;
        const H = sourceCanvas.height;
        const ctx = sourceCanvas.getContext('2d');

        const imgData = ctx.getImageData(0, 0, W, H);
        const data = imgData.data;
        const totalPixels = W * H;

        for (let i = 0; i < data.length; i += 4) {
            const pixelIdx = i / 4;
            let rawAlpha;
            if (alphaMaskData.length === totalPixels) {
                rawAlpha = alphaMaskData[pixelIdx];
            } else if (alphaMaskData.length === totalPixels * 4) {
                rawAlpha = alphaMaskData[i + 3];
            } else if (alphaMaskData.length === totalPixels * 3) {
                rawAlpha = alphaMaskData[pixelIdx * 3];
            } else {
                rawAlpha = alphaMaskData[pixelIdx];
            }

            // Puerta de ruido alfa (Alpha Noise Gate) para limpiar fondo residual
            let alpha = 0.0;
            if (rawAlpha >= 251) {
                alpha = 1.0;
            } else if (rawAlpha > 4) {
                alpha = (rawAlpha - 4) / 247.0;
            }

            // Si es fondo (alpha <= 0), fijar blanco puro (#FFFFFF)
            if (alpha <= 0.0) {
                data[i] = 255;
                data[i + 1] = 255;
                data[i + 2] = 255;
                data[i + 3] = 255;
                continue;
            }

            // Si es el interior del objeto (alpha === 1), mantener píxeles 100% originales intactos
            if (alpha >= 1.0) {
                data[i + 3] = 255;
                continue;
            }

            // En el borde antialiasing (0 < alpha < 1), mezclar píxel original directamente con blanco puro #FFFFFF
            data[i] = Math.round(data[i] * alpha + 255 * (1.0 - alpha));
            data[i + 1] = Math.round(data[i + 1] * alpha + 255 * (1.0 - alpha));
            data[i + 2] = Math.round(data[i + 2] * alpha + 255 * (1.0 - alpha));
            data[i + 3] = 255;
        }

        ctx.putImageData(imgData, 0, 0);
        return sourceCanvas;
    }


    // Optimización: reduce fotos gigantes de celular (e.g. 4000x3000px, 8MB) a máx 1400px
    // antes de enviarlas a la red neuronal, acelerando el recorte hasta 5 veces
    async function downscaleImageIfNeeded(file, maxDimension = 1400) {
        return new Promise((resolve) => {
            if (!file.type || !file.type.startsWith('image/')) {
                return resolve(file);
            }
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(url);
                let { naturalWidth: width, naturalHeight: height } = img;
                if (!width || !height || (width <= maxDimension && height <= maxDimension)) {
                    return resolve(file);
                }
                if (width > height) {
                    height = Math.round((height * maxDimension) / width);
                    width = maxDimension;
                } else {
                    width = Math.round((width * maxDimension) / height);
                    height = maxDimension;
                }
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                canvas.toBlob((blob) => {
                    if (!blob) return resolve(file);
                    const resizedFile = new File([blob], file.name, { type: 'image/jpeg' });
                    resolve(resizedFile);
                }, 'image/jpeg', 0.94);
            };
            img.onerror = () => {
                URL.revokeObjectURL(url);
                resolve(file);
            };
            img.src = url;
        });
    }

    // Extraer sombra de contacto y fusionar pieza original de Gemini sobre fondo blanco puro #FFFFFF
    function extractAndBlendWithGroundShadow(sourceCanvas, finalMask, origW, origH) {
        const outputCanvas = document.createElement('canvas');
        outputCanvas.width = origW;
        outputCanvas.height = origH;
        const outCtx = outputCanvas.getContext('2d');
        outCtx.fillStyle = '#ffffff';
        outCtx.fillRect(0, 0, origW, origH);

        const srcCtx = sourceCanvas.getContext('2d');
        const srcImgData = srcCtx.getImageData(0, 0, origW, origH);
        const sData = srcImgData.data;

        const maskData = finalMask.data;
        const maskChannels = finalMask.channels;

        // 1. Muestrear fondo del estudio en las esquinas superiores (sin objeto ni sombra)
        let bgR = 0, bgG = 0, bgB = 0, bgCount = 0;
        const sampleHeight = Math.max(10, Math.round(origH * 0.08));
        const sampleWidth = Math.max(20, Math.round(origW * 0.20));

        for (let y = 0; y < sampleHeight; y++) {
            for (let x = 0; x < sampleWidth; x++) {
                // Esquina superior izquierda
                let idx = (y * origW + x) * 4;
                bgR += sData[idx];
                bgG += sData[idx + 1];
                bgB += sData[idx + 2];
                bgCount++;

                // Esquina superior derecha
                idx = (y * origW + (origW - 1 - x)) * 4;
                bgR += sData[idx];
                bgG += sData[idx + 1];
                bgB += sData[idx + 2];
                bgCount++;
            }
        }

        bgR = Math.max(210, Math.min(255, bgR / Math.max(1, bgCount)));
        bgG = Math.max(210, Math.min(255, bgG / Math.max(1, bgCount)));
        bgB = Math.max(210, Math.min(255, bgB / Math.max(1, bgCount)));
        const bgLum = 0.299 * bgR + 0.587 * bgG + 0.114 * bgB;

        // 2. Encontrar caja del producto para acotar la sombra de contacto
        let minX = origW, minY = origH, maxX = 0, maxY = 0;
        let found = false;
        for (let y = 0; y < origH; y++) {
            for (let x = 0; x < origW; x++) {
                const idx = y * origW + x;
                const alphaVal = maskChannels === 1 ? maskData[idx] : maskData[idx * maskChannels];
                if (alphaVal > 25) {
                    if (x < minX) minX = x;
                    if (x > maxX) maxX = x;
                    if (y < minY) minY = y;
                    if (y > maxY) maxY = y;
                    found = true;
                }
            }
        }

        if (!found) {
            minX = 0; maxX = origW - 1; minY = 0; maxY = origH - 1;
        }

        const objH = Math.max(1, maxY - minY);
        const objW = Math.max(1, maxX - minX);

        // La sombra de contacto se encuentra en el tercio inferior del objeto y el plano de apoyo
        const shadowStartY = minY + objH * 0.25;
        const shadowMaxY = Math.min(origH - 1, maxY + objH * 0.40);
        const shadowMinX = Math.max(0, minX - objW * 0.25);
        const shadowMaxX = Math.min(origW - 1, maxX + objW * 0.25);

        const outImgData = outCtx.getImageData(0, 0, origW, origH);
        const oData = outImgData.data;

        for (let y = 0; y < origH; y++) {
            for (let x = 0; x < origW; x++) {
                const pIdx = (y * origW + x) * 4;
                const mIdx = y * origW + x;
                const alphaVal = maskChannels === 1 ? maskData[mIdx] : maskData[mIdx * maskChannels];
                const objAlpha = alphaVal / 255.0;

                const rOrig = sData[pIdx];
                const gOrig = sData[pIdx + 1];
                const bOrig = sData[pIdx + 2];

                // Si el píxel pertenece al objeto (Gemini), se mantiene 100% original sin filtros
                if (objAlpha >= 0.98) {
                    oData[pIdx] = rOrig;
                    oData[pIdx + 1] = gOrig;
                    oData[pIdx + 2] = bOrig;
                    oData[pIdx + 3] = 255;
                    continue;
                }

                // Fuera o en el borde del objeto: calcular sombra de contacto
                let shadowWeight = 0;
                if (y >= shadowStartY && y <= shadowMaxY && x >= shadowMinX && x <= shadowMaxX) {
                    let vertFactor = 1.0;
                    if (y < maxY) {
                        vertFactor = (y - shadowStartY) / (maxY - shadowStartY + 0.001);
                    } else {
                        vertFactor = Math.max(0, 1.0 - (y - maxY) / (shadowMaxY - maxY + 0.001));
                    }

                    let horizFactor = 1.0;
                    if (x < minX) {
                        horizFactor = Math.max(0, 1.0 - (minX - x) / (minX - shadowMinX + 0.001));
                    } else if (x > maxX) {
                        horizFactor = Math.max(0, 1.0 - (x - maxX) / (shadowMaxX - maxX + 0.001));
                    }

                    shadowWeight = Math.min(1.0, Math.max(0, vertFactor * horizFactor));
                }

                const pLum = 0.299 * rOrig + 0.587 * gOrig + 0.114 * bOrig;
                let rShadow = 255;
                let gShadow = 255;
                let bShadow = 255;

                if (shadowWeight > 0.01 && pLum < bgLum - 3.0) {
                    // Ratio de absorción de la sombra original de Gemini sobre fondo blanco
                    const rRatio = Math.min(1.0, rOrig / bgR);
                    const gRatio = Math.min(1.0, gOrig / bgG);
                    const bRatio = Math.min(1.0, bOrig / bgB);

                    rShadow = Math.round(255 * (1.0 - (1.0 - rRatio) * shadowWeight));
                    gShadow = Math.round(255 * (1.0 - (1.0 - gRatio) * shadowWeight));
                    bShadow = Math.round(255 * (1.0 - (1.0 - bRatio) * shadowWeight));
                }

                // Fusión Porter-Duff Over: píxeles puros de Gemini sobre la sombra integrada
                oData[pIdx] = Math.round(rOrig * objAlpha + rShadow * (1.0 - objAlpha));
                oData[pIdx + 1] = Math.round(gOrig * objAlpha + gShadow * (1.0 - objAlpha));
                oData[pIdx + 2] = Math.round(bOrig * objAlpha + bShadow * (1.0 - objAlpha));
                oData[pIdx + 3] = 255;
            }
        }

        outCtx.putImageData(outImgData, 0, 0);
        return {
            canvas: outputCanvas,
            bbox: { minX, minY, maxX, maxY, objW, objH }
        };
    }

    // Procesar una imagen para aislar objeto y montarlo sobre fondo blanco puro (#ffffff)
    async function processImageToStudioWhite(file, onProgress, mode = 'creation') {
        const isAutoEnabled = autoSwitch ? autoSwitch.checked : true;
        if (!isAutoEnabled && mode !== 'enhance' && mode !== 'gemini') {
            return {
                file: file,
                previewUrl: URL.createObjectURL(file),
                processed: false
            };
        }

        const isEnhance = mode === 'enhance';
        const isGemini = mode === 'gemini';

        // 0. Detectar si la imagen ya es un flyer (corregir error heredado donde flyer sobreescribió image_path)
        let finalFileToProcess = file;
        try {
            const tempImg = new Image();
            await new Promise((res, rej) => {
                tempImg.onload = res;
                tempImg.onerror = rej;
                tempImg.src = URL.createObjectURL(file);
            });
            URL.revokeObjectURL(tempImg.src);

            const ratio = tempImg.width / tempImg.height;
            const isSquareRatio = Math.abs(ratio - 1.0) < 0.06;
            const isLegacyRatio = ratio > 0.70 && ratio < 0.88;

            if (isSquareRatio || isLegacyRatio) {
                const flyerCheckCanvas = document.createElement('canvas');
                const flyerCheckCtx = flyerCheckCanvas.getContext('2d');
                flyerCheckCanvas.width = tempImg.width;
                flyerCheckCanvas.height = tempImg.height;
                flyerCheckCtx.drawImage(tempImg, 0, 0);

                // Comprobar si la barra superior y contenedor inferior son oscuros
                const sampleTopY = isSquareRatio ? Math.round(tempImg.height * 0.09) : Math.round(tempImg.height * 0.087);
                const sampleBottomY = isSquareRatio ? Math.round(tempImg.height * 0.93) : Math.round(tempImg.height * 0.89);
                const sampleX = Math.round(tempImg.width / 2);

                const topPixel = flyerCheckCtx.getImageData(sampleX, sampleTopY, 1, 1).data;
                const bottomPixel = flyerCheckCtx.getImageData(sampleX, sampleBottomY, 1, 1).data;

                const isTopBlack = topPixel[0] < 60 && topPixel[1] < 60 && topPixel[2] < 60;
                const isBottomBlack = bottomPixel[0] < 60 && bottomPixel[1] < 60 && bottomPixel[2] < 60;

                if (isTopBlack && isBottomBlack) {
                    if (onProgress) onProgress('Detectado flyer previo. Extrayendo imagen original...');
                    // Es un flyer. Extraer la parte central blanca libre de insignias ni elementos graficos
                    let cropX, cropY, cropW, cropH;
                    if (isSquareRatio) {
                        cropX = Math.round(tempImg.width * 0.15);
                        cropY = Math.round(tempImg.height * 0.26);
                        cropW = Math.round(tempImg.width * 0.70);
                        cropH = Math.round(tempImg.height * 0.48);
                    } else {
                        cropX = Math.round(tempImg.width * 0.14);
                        cropY = Math.round(tempImg.height * 0.33);
                        cropW = Math.round(tempImg.width * 0.72);
                        cropH = Math.round(tempImg.height * 0.47);
                    }

                    const extractCanvas = document.createElement('canvas');
                    extractCanvas.width = cropW;
                    extractCanvas.height = cropH;
                    const extractCtx = extractCanvas.getContext('2d');
                    extractCtx.fillStyle = '#ffffff';
                    extractCtx.fillRect(0, 0, cropW, cropH);
                    extractCtx.drawImage(tempImg, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);

                    const extractedBlob = await new Promise(res => extractCanvas.toBlob(res, 'image/jpeg', 1.0));
                    finalFileToProcess = new File([extractedBlob], file.name, { type: 'image/jpeg' });
                }
            }
        } catch (e) {
            console.warn("No se pudo analizar la imagen pre-procesamiento", e);
        }

        // 1. Optimizar resolución previa si excede 1600px para evitar saturación de memoria
        if (onProgress) onProgress('Optimizando resolución para inferencia...');
        const optimizedFile = await downscaleImageIfNeeded(finalFileToProcess, 1600);

        // 2. Obtener modelo y procesador Transformers.js RMBG-1.4
        if (onProgress) onProgress('Cargando motor de IA RMBG-1.4...');
        const { model, processor } = await getRMBGModelAndProcessor(onProgress);

        if (onProgress) onProgress(`Aislando producto con RMBG-1.4 (${activeBackendName})...`);

        // 3. Cargar RawImage
        const rawInput = await RawImage.fromBlob(optimizedFile);
        const origW = rawInput.width;
        const origH = rawInput.height;

        // 4. Inferencia de segmentación directa (AutoProcessor + AutoModel)
        const { pixel_values } = await processor(rawInput);
        const { output } = await model({ input: pixel_values });

        if (onProgress) onProgress('Restaurando antialiasing a resolución nativa...');

        // 5. Extraer máscara alfa de alta resolución y reescalar a resolución original
        const finalMask = await RawImage.fromTensor(output[0].mul(255).to('uint8')).resize(origW, origH);

        // 6. Transferir a canvas de trabajo a resolución nativa
        const sourceCanvas = document.createElement('canvas');
        sourceCanvas.width = origW;
        sourceCanvas.height = origH;
        const sourceCtx = sourceCanvas.getContext('2d');

        // Dibujar imagen original completa
        try {
            const imgBitmap = await createImageBitmap(optimizedFile);
            sourceCtx.drawImage(imgBitmap, 0, 0);
        } catch (e) {
            await new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    sourceCtx.drawImage(img, 0, 0);
                    resolve();
                };
                img.onerror = reject;
                img.src = URL.createObjectURL(optimizedFile);
            });
        }

        let orientedCanvas = document.createElement('canvas');
        let cropX = 0, cropY = 0, cropW = origW, cropH = origH;

        if (isGemini) {
            // EXCLUSIVO GEMINI: Omitir filtros locales destructivos, retener sombras de suelo y mantener orientación natural
            if (onProgress) onProgress('Preservando sombras originales y fusionando con fondo blanco...');
            const shadowResult = extractAndBlendWithGroundShadow(sourceCanvas, finalMask, origW, origH);
            const shadowCanvas = shadowResult.canvas;
            const bbox = shadowResult.bbox;

            // Bounding box ajustado al objeto y su sombra de apoyo inmediata sin margen vacío excesivo
            const padX = Math.max(8, Math.round(bbox.objW * 0.04));
            const padYTop = Math.max(8, Math.round(bbox.objH * 0.03));
            const padYBottom = Math.max(12, Math.round(bbox.objH * 0.10));

            cropX = Math.max(0, bbox.minX - padX);
            cropY = Math.max(0, bbox.minY - padYTop);
            cropW = Math.min(origW - cropX, (bbox.maxX - cropX) + padX);
            cropH = Math.min(origH - cropY, (bbox.maxY - cropY) + padYBottom);

            // Orientación natural: NO rotar 90°, mantener la gravedad y la posición física de la sombra
            orientedCanvas.width = cropW;
            orientedCanvas.height = cropH;
            const oCtx = orientedCanvas.getContext('2d');
            oCtx.fillStyle = '#ffffff';
            oCtx.fillRect(0, 0, cropW, cropH);
            oCtx.drawImage(shadowCanvas, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);

        } else {
            // 7. RUTA ESTÁNDAR (IA LOCAL / CREACIÓN): Aislar sobre fondo blanco puro sin alterar colores ni brillo
            if (onProgress) onProgress('Aplicando fondo blanco puro al producto...');
            const maskData = finalMask.data;
            processSatinRubberStudio(sourceCanvas, maskData);

            // 8. Calcular Bounding Box basado en la máscara alfa
            let minX = origW, minY = origH, maxX = 0, maxY = 0;
            let found = false;
            const maskChannels = finalMask.channels;
            for (let y = 0; y < origH; y++) {
                for (let x = 0; x < origW; x++) {
                    const idx = y * origW + x;
                    const alphaVal = maskChannels === 1 ? maskData[idx] : maskData[idx * maskChannels];
                    if (alphaVal > 15) {
                        if (x < minX) minX = x;
                        if (x > maxX) maxX = x;
                        if (y < minY) minY = y;
                        if (y > maxY) maxY = y;
                        found = true;
                    }
                }
            }

            if (found) {
                cropX = Math.max(0, minX - 2);
                cropY = Math.max(0, minY - 2);
                cropW = Math.min(origW - cropX, (maxX - minX + 1) + 4);
                cropH = Math.min(origH - cropY, (maxY - minY + 1) + 4);
            }

            // 9. Orientación natural fiel (rotación = 0°): conserva la orientación, inclinación y postura original del producto
            orientedCanvas.width = cropW;
            orientedCanvas.height = cropH;
            const oCtx = orientedCanvas.getContext('2d');
            oCtx.fillStyle = '#ffffff';
            oCtx.fillRect(0, 0, cropW, cropH);
            oCtx.drawImage(sourceCanvas, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);
        }

        // 10. Montar sobre canvas cuadrado de 1500x1500px con fondo blanco puro #FFFFFF
        const TARGET_SIZE = 1500;
        const finalCanvas = document.createElement('canvas');
        finalCanvas.width = TARGET_SIZE;
        finalCanvas.height = TARGET_SIZE;
        const finalCtx = finalCanvas.getContext('2d');

        finalCtx.fillStyle = '#ffffff';
        finalCtx.fillRect(0, 0, TARGET_SIZE, TARGET_SIZE);
        finalCtx.imageSmoothingEnabled = true;
        finalCtx.imageSmoothingQuality = 'high';

        const maxProductDim = 1300;
        const scaleFactor = Math.min(maxProductDim / orientedCanvas.width, maxProductDim / orientedCanvas.height);
        const finalW = Math.round(orientedCanvas.width * scaleFactor);
        const finalH = Math.round(orientedCanvas.height * scaleFactor);
        const destX = Math.round((TARGET_SIZE - finalW) / 2);
        const destY = Math.round((TARGET_SIZE - finalH) / 2);

        finalCtx.drawImage(orientedCanvas, 0, 0, orientedCanvas.width, orientedCanvas.height, destX, destY, finalW, finalH);

        // Studio White Snapping puro: garantiza que el fondo sea estrictamente #FFFFFF (255, 255, 255)
        const finalImgData = finalCtx.getImageData(0, 0, TARGET_SIZE, TARGET_SIZE);
        const fData = finalImgData.data;
        const snapThreshold = isGemini ? 253 : 252;
        for (let i = 0; i < fData.length; i += 4) {
            if (fData[i] >= snapThreshold && fData[i + 1] >= snapThreshold && fData[i + 2] >= snapThreshold) {
                fData[i] = 255;
                fData[i + 1] = 255;
                fData[i + 2] = 255;
            }
        }
        finalCtx.putImageData(finalImgData, 0, 0);

        return new Promise((resolve, reject) => {
            finalCanvas.toBlob((blob) => {
                if (!blob) {
                    return reject(new Error('Fallo al exportar canvas a blob'));
                }
                const cleanName = file.name.replace(/\.[^/.]+$/, "") + ".jpg";
                const whiteBgFile = new File([blob], cleanName, { type: 'image/jpeg' });
                resolve({
                    file: whiteBgFile,
                    previewUrl: finalCanvas.toDataURL('image/jpeg', 0.94),
                    processed: true
                });
            }, 'image/jpeg', 0.94);
        });
    }

    // 1. Manejo de Imagen Principal
    if (mainInput) {
        mainInput.addEventListener('change', async function() {
            if (!this.files || this.files.length === 0) return;
            originalMainFile = this.files[0];
            if (mainFileName) mainFileName.textContent = originalMainFile.name;

            const isAutoEnabled = autoSwitch ? autoSwitch.checked : true;
            if (!isAutoEnabled) {
                if (mainPreviewImg) mainPreviewImg.src = URL.createObjectURL(originalMainFile);
                if (mainBadge) {
                    mainBadge.className = 'badge badge-secondary mb-1';
                    mainBadge.innerHTML = '<i class="fas fa-image mr-1"></i> Original';
                }
                if (mainPreviewArea) mainPreviewArea.classList.remove('d-none');
                return;
            }

            try {
                setProcessingState(true, 'Procesando imagen principal con IA...');
                if (mainStatus) mainStatus.classList.remove('d-none');
                if (mainPreviewArea) mainPreviewArea.classList.add('d-none');

                const result = await processImageToStudioWhite(originalMainFile, (msg) => {
                    if (mainStatusText) mainStatusText.textContent = msg;
                });

                // Reemplazar archivo en el input mediante DataTransfer
                const dt = new DataTransfer();
                dt.items.add(result.file);
                mainInput.files = dt.files;

                if (mainPreviewImg) mainPreviewImg.src = result.previewUrl;
                if (mainBadge) {
                    mainBadge.className = 'badge badge-success mb-1';
                    mainBadge.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Fondo blanco aplicado';
                }
                if (mainPreviewArea) mainPreviewArea.classList.remove('d-none');
                if (window.toastr) {
                    toastr.success('Fondo blanco de estudio aplicado a la imagen principal.');
                }
            } catch (err) {
                console.error('Error procesando imagen principal:', err);
                if (window.toastr) {
                    toastr.warning('No se pudo procesar el fondo con IA en el navegador. Se mantendrá la foto original.');
                }
                if (mainPreviewImg) mainPreviewImg.src = URL.createObjectURL(originalMainFile);
                if (mainBadge) {
                    mainBadge.className = 'badge badge-secondary mb-1';
                    mainBadge.innerHTML = '<i class="fas fa-image mr-1"></i> Original';
                }
                if (mainPreviewArea) mainPreviewArea.classList.remove('d-none');
            } finally {
                if (mainStatus) mainStatus.classList.add('d-none');
                setProcessingState(false);
            }
        });
    }

    if (btnRestoreMain) {
        btnRestoreMain.addEventListener('click', function() {
            if (!originalMainFile || !mainInput) return;
            const dt = new DataTransfer();
            dt.items.add(originalMainFile);
            mainInput.files = dt.files;

            if (mainPreviewImg) mainPreviewImg.src = URL.createObjectURL(originalMainFile);
            if (mainBadge) {
                mainBadge.className = 'badge badge-secondary mb-1';
                mainBadge.innerHTML = '<i class="fas fa-image mr-1"></i> Original';
            }
            if (window.toastr) {
                toastr.info('Imagen principal restaurada a la versión original.');
            }
        });
    }

    // 2. Manejo de Galería de Imágenes Secundarias
    if (galleryInput) {
        galleryInput.addEventListener('change', async function() {
            if (!this.files || this.files.length === 0) return;
            const originalFiles = Array.from(this.files);
            if (galleryContainer) galleryContainer.innerHTML = '';

            const isAutoEnabled = autoSwitch ? autoSwitch.checked : true;
            if (!isAutoEnabled) {
                originalFiles.forEach(file => {
                    renderGalleryItem(URL.createObjectURL(file), false);
                });
                if (galleryPreviewArea) galleryPreviewArea.classList.remove('d-none');
                return;
            }

            try {
                setProcessingState(true, `Procesando ${originalFiles.length} imágenes de galería...`);
                if (galleryStatus) galleryStatus.classList.remove('d-none');
                if (galleryPreviewArea) galleryPreviewArea.classList.remove('d-none');

                const dt = new DataTransfer();

                for (let i = 0; i < originalFiles.length; i++) {
                    const file = originalFiles[i];
                    if (galleryStatusText) {
                        galleryStatusText.textContent = `Procesando imagen ${i + 1} de ${originalFiles.length} con IA...`;
                    }

                    try {
                        const result = await processImageToStudioWhite(file, (msg) => {
                            if (galleryStatusText) {
                                galleryStatusText.textContent = `[${i + 1}/${originalFiles.length}] ${msg}`;
                            }
                        });

                        dt.items.add(result.file);
                        renderGalleryItem(result.previewUrl, true);
                    } catch (itemErr) {
                        console.warn(`Fallo al procesar imagen de galería ${file.name}:`, itemErr);
                        dt.items.add(file);
                        renderGalleryItem(URL.createObjectURL(file), false);
                    }
                }

                galleryInput.files = dt.files;
                if (window.toastr) {
                    toastr.success('Todas las imágenes secundarias fueron procesadas con fondo blanco.');
                }
            } catch (err) {
                console.error('Error procesando galería:', err);
                if (window.toastr) {
                    toastr.warning('Hubo un problema al procesar la galería con IA. Se conservaron los archivos originales.');
                }
            } finally {
                if (galleryStatus) galleryStatus.classList.add('d-none');
                setProcessingState(false);
            }
        });
    }

    function renderGalleryItem(url, isWhiteBg) {
        if (!galleryContainer) return;
        const div = document.createElement('div');
        div.className = 'border rounded p-1 text-center bg-white shadow-sm';
        div.style.width = '85px';
        div.innerHTML = `
            <img src="${url}" style="height: 60px; width: 75px; object-fit: contain; background: #fff;" class="rounded" alt="Miniatura">
            <div class="badge ${isWhiteBg ? 'badge-success' : 'badge-secondary'} mt-1 d-block" style="font-size: 9px; padding: 2px 4px;">
                ${isWhiteBg ? 'Fondo blanco' : 'Original'}
            </div>
        `;
        galleryContainer.appendChild(div);
    }

    // ==========================================
    // DICCIONARIO Y RENDERIZADO DE TITULO EN CANVAS
    // ==========================================
    const MOTORCYCLE_BRANDS = new Set([
        // Marcas colombianas y latinoamericanas principales
        'YAMAHA', 'HONDA', 'SUZUKI', 'BAJAJ', 'AKT', 'AUTECO', 'KAWASAKI',
        'HERO', 'KTM', 'TVS', 'KYMCO', 'VICTORY', 'SIGMA', 'SYM',
        // Marcas internacionales y regionales adicionales
        'BENELLI', 'BMW', 'DUCATI', 'ROYAL ENFIELD', 'ROYAL', 'ENFIELD',
        'HARLEY-DAVIDSON', 'HARLEY', 'DAVIDSON', 'HUSQVARNA', 'APRILIA',
        'PIAGGIO', 'VESPA', 'KEEWAY', 'UM', 'AYCO', 'CERONTE', 'ZONTES',
        'CFMOTO', 'VOGE', 'LIFAN', 'LONCIN', 'JIALING', 'SHINERAY',
        'STARK', 'FRATELLI', 'ITALIKA',
        // Marcas automotrices comunes (bujes / soportes caucho)
        'CHEVROLET', 'RENAULT', 'MAZDA', 'TOYOTA', 'NISSAN', 'KIA',
        'HYUNDAI', 'FORD', 'VOLKSWAGEN'
    ]);

    function isBrandToken(str) {
        if (!str) return false;
        const clean = str.replace(/^[^A-Z0-9]+|[^A-Z0-9]+$/gi, '').toUpperCase();
        return clean.length > 0 && MOTORCYCLE_BRANDS.has(clean);
    }

    function tokenizeLineForCanvas(lineStr) {
        const rawWords = lineStr.trim().split(/\s+/);
        const tokens = [];
        const wCount = rawWords.length;

        for (let w = 0; w < wCount; w++) {
            const rawWord = rawWords[w];
            const isLastWord = (w === wCount - 1);

            // Separar subpartes si contiene delimitadores como / , ( ) [ ]
            const parts = rawWord.split(/([\/,()\[\]])/).filter(Boolean);
            const pCount = parts.length;

            for (let p = 0; p < pCount; p++) {
                const part = parts[p];

                // Si contiene guión, verificar si alguna subparte es marca (ej. AUTECO-BAJAJ)
                if (part.includes('-') && !MOTORCYCLE_BRANDS.has(part.toUpperCase())) {
                    const subHyphen = part.split(/(-)/).filter(Boolean);
                    const hasBrand = subHyphen.some(sh => isBrandToken(sh));
                    if (hasBrand) {
                        const shCount = subHyphen.length;
                        for (let h = 0; h < shCount; h++) {
                            const hPart = subHyphen[h];
                            const isLastSub = (p === pCount - 1 && h === shCount - 1);
                            tokens.push({
                                text: hPart + (isLastSub && !isLastWord ? ' ' : ''),
                                color: isBrandToken(hPart) ? '#e61b23' : '#111111'
                            });
                        }
                        continue;
                    }
                }

                const isLastPart = (p === pCount - 1);
                tokens.push({
                    text: part + (isLastPart && !isLastWord ? ' ' : ''),
                    color: isBrandToken(part) ? '#e61b23' : '#111111'
                });
            }
        }
        return tokens;
    }

    function splitTitleIntoBalancedLines(fullTitle, maxPxWidth, ctx) {
        const words = fullTitle.trim().split(/\s+/);
        if (words.length <= 1) return [fullTitle, ''];

        let bestSplit = [fullTitle, ''];
        let bestScore = Infinity;

        for (let k = 1; k < words.length; k++) {
            const p1 = words.slice(0, k);
            const p2 = words.slice(k);
            const line1 = p1.join(' ');
            const line2 = p2.join(' ');

            const w1 = ctx.measureText(line1).width;
            const w2 = ctx.measureText(line2).width;

            let score = Math.abs(w1 - w2);
            if (w1 > maxPxWidth) score += 2000;
            if (w2 > maxPxWidth) score += 2000;

            const lastWord1 = p1[p1.length - 1];
            const firstWord2 = p2[0];

            // Penalizaciones para evitar que la segunda linea empiece con puntuacion
            if (/^[\/,\-\–]/.test(firstWord2)) score += 600;
            if (/^[\)\]]/.test(firstWord2)) score += 1000;
            if (/[\(\[]$/.test(lastWord1)) score += 1000;

            // Penalizar separar numero de sufijo de modelo (ej. "160" y "NS")
            if (/^\d+$/.test(lastWord1) && /^[A-Z]{1,4}$/.test(firstWord2)) {
                score += 350;
            }

            // Bonificacion por corte despues de '/' como separador de lista de modelos
            if (lastWord1 === '/') {
                score -= 60;
            }

            // Bonificacion semantica por corte inmediatamente despues de la marca
            if (isBrandToken(lastWord1)) {
                score -= 60;
            }

            if (score < bestScore) {
                bestScore = score;
                bestSplit = [line1, line2];
            }
        }
        return bestSplit;
    }

    async function aplicarPlantilla(imagenProcesadaFile, nombreProducto, refCodeCustom = null) {
        return new Promise(async (resolve) => {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            const imgPlantilla = new Image();
            imgPlantilla.crossOrigin = "Anonymous";
            imgPlantilla.src = '{{ asset("img/plantilla-estado.jpg") }}';

            const imgProd = new Image();
            imgProd.src = URL.createObjectURL(imagenProcesadaFile);

            await Promise.all([
                new Promise(r => { imgPlantilla.onload = r; imgPlantilla.onerror = r; }),
                new Promise(r => { imgProd.onload = r; imgProd.onerror = r; })
            ]);

            if (!imgPlantilla.width) {
                resolve(imagenProcesadaFile);
                return;
            }

            canvas.width = imgPlantilla.width;
            canvas.height = imgPlantilla.height;

            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.drawImage(imgPlantilla, 0, 0);

            // TITULO EN LA ZONA SUPERIOR BLANCA
            const text = (nombreProducto || 'PRODUCTO').toUpperCase();
            const topMaxWidth = 1650; // Ancho maximo en zona blanca superior (2048x2048)
            const topCenterX = 1024;
            const topCenterY = 410;

            // Auto-fit 1 linea: probar si cabe a tamano grande (75px hasta 50px)
            let singleLineFs = 75;
            ctx.font = `italic 900 ${singleLineFs}px "Arial Black", Arial, sans-serif`;
            while (ctx.measureText(text).width > topMaxWidth && singleLineFs > 20) {
                singleLineFs -= 1;
                ctx.font = `italic 900 ${singleLineFs}px "Arial Black", Arial, sans-serif`;
            }

            let linesToDraw = [];
            let finalFontSize = singleLineFs;

            if (singleLineFs >= 50) {
                // Titulo corto: 1 sola linea centrada
                linesToDraw = [{ text: text, y: topCenterY }];
                finalFontSize = singleLineFs;
            } else {
                // Titulo largo: dividir inteligentemente en 2 lineas balanceadas
                ctx.font = `italic 900 60px "Arial Black", Arial, sans-serif`;
                const [line1, line2] = splitTitleIntoBalancedLines(text, topMaxWidth, ctx);

                let twoLineFs = 64;
                ctx.font = `italic 900 ${twoLineFs}px "Arial Black", Arial, sans-serif`;
                while ((ctx.measureText(line1).width > topMaxWidth || (line2 && ctx.measureText(line2).width > topMaxWidth)) && twoLineFs > 36) {
                    twoLineFs -= 1;
                    ctx.font = `italic 900 ${twoLineFs}px "Arial Black", Arial, sans-serif`;
                }

                finalFontSize = twoLineFs;
                if (!line2) {
                    linesToDraw = [{ text: line1, y: topCenterY }];
                } else {
                    const lineGap = Math.round(twoLineFs * 1.22);
                    linesToDraw = [
                        { text: line1, y: topCenterY - Math.round(lineGap / 2) },
                        { text: line2, y: topCenterY + Math.round(lineGap / 2) }
                    ];
                }
            }

            // Dibujo multi-color token por token
            ctx.font = `italic 900 ${finalFontSize}px "Arial Black", Arial, sans-serif`;
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';

            for (const lineObj of linesToDraw) {
                const tokens = tokenizeLineForCanvas(lineObj.text);
                let totalLineWidth = 0;
                for (const t of tokens) {
                    totalLineWidth += ctx.measureText(t.text).width;
                }

                let currentX = topCenterX - (totalLineWidth / 2);
                for (const t of tokens) {
                    ctx.fillStyle = t.color;
                    ctx.fillText(t.text, currentX, lineObj.y);
                    currentX += ctx.measureText(t.text).width;
                }
            }

            // CODIGO DE REFERENCIA EN EL CONTENEDOR TRAPEZOIDAL INFERIOR
            // Contenedor inferior negro: Center x = 1024, y = 1905
            const refInput = document.querySelector('input[name="reference"]');
            const refVal = refCodeCustom || (refInput ? refInput.value.trim() : null) || '{{ $product->reference ?? $product->id ?? "0000" }}';
            let refCode = 'REF: ' + refVal;

            const refMaxWidth = 780;
            let refFontSize = 58;
            ctx.font = `italic 900 ${refFontSize}px "Arial Black", Arial, sans-serif`;
            while (ctx.measureText(refCode).width > refMaxWidth && refFontSize > 32) {
                refFontSize -= 1;
                ctx.font = `italic 900 ${refFontSize}px "Arial Black", Arial, sans-serif`;
            }

            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#FFFFFF';
            ctx.fillText(refCode, 1024, 1905);

            // Ajuste proporcional y uniforme de la imagen del producto en el area central blanca:
            // 1. Detectar el bounding box real del objeto dentro de imgProd (eliminando las bandas blancas vacías del lienzo cuadrado 1500x1500)
            const rawW = imgProd.naturalWidth || imgProd.width || 1500;
            const rawH = imgProd.naturalHeight || imgProd.height || 1500;
            const trimCanvas = document.createElement('canvas');
            trimCanvas.width = rawW;
            trimCanvas.height = rawH;
            const trimCtx = trimCanvas.getContext('2d');
            trimCtx.fillStyle = '#FFFFFF';
            trimCtx.fillRect(0, 0, rawW, rawH);
            trimCtx.drawImage(imgProd, 0, 0, rawW, rawH);

            let srcX = 0, srcY = 0, prodW = rawW, prodH = rawH;
            try {
                const pData = trimCtx.getImageData(0, 0, rawW, rawH).data;
                let minX = rawW, minY = rawH, maxX = 0, maxY = 0;
                let foundCore = false;

                // Fase A: medir el cuerpo sólido del producto (luminancia < 215, ignorando sombras suaves y fondo blanco)
                for (let y = 0; y < rawH; y++) {
                    for (let x = 0; x < rawW; x++) {
                        const i = (y * rawW + x) * 4;
                        const lum = 0.299 * pData[i] + 0.587 * pData[i + 1] + 0.114 * pData[i + 2];
                        if (lum < 215) {
                            if (x < minX) minX = x;
                            if (x > maxX) maxX = x;
                            if (y < minY) minY = y;
                            if (y > maxY) maxY = y;
                            foundCore = true;
                        }
                    }
                }

                // Fase B: fallback para objetos muy claros (luminancia < 246)
                if (!foundCore) {
                    for (let y = 0; y < rawH; y++) {
                        for (let x = 0; x < rawW; x++) {
                            const i = (y * rawW + x) * 4;
                            if (pData[i] < 246 || pData[i + 1] < 246 || pData[i + 2] < 246) {
                                if (x < minX) minX = x;
                                if (x > maxX) maxX = x;
                                if (y < minY) minY = y;
                                if (y > maxY) maxY = y;
                                foundCore = true;
                            }
                        }
                    }
                }

                if (foundCore && maxX > minX && maxY > minY) {
                    const coreW = maxX - minX + 1;
                    const coreH = maxY - minY + 1;
                    // Conservar un margen mínimo para no cortar la sombra de contacto inmediata
                    const padSide = Math.max(6, Math.round(coreW * 0.03));
                    const padTop = Math.max(6, Math.round(coreH * 0.02));
                    const padBottom = Math.max(10, Math.round(coreH * 0.08));

                    srcX = Math.max(0, minX - padSide);
                    srcY = Math.max(0, minY - padTop);
                    prodW = Math.min(rawW - srcX, coreW + padSide * 2);
                    prodH = Math.min(rawH - srcY, coreH + padTop + padBottom);
                }
            } catch (e) {
                console.warn('No se pudo recortar el bounding box en aplicarPlantilla:', e);
            }

            const maxProdWidth = 1350;
            const maxProdHeight = 950;
            const TARGET_VISUAL_AREA = 820000;
            const ratio = Math.min(
                maxProdWidth / prodW,
                maxProdHeight / prodH,
                Math.sqrt(TARGET_VISUAL_AREA / Math.max(1, prodW * prodH))
            );
            const finalW = Math.round(prodW * ratio);
            const finalH = Math.round(prodH * ratio);

            const targetCenterX = 1024;
            const targetCenterY = 1010;

            ctx.globalCompositeOperation = 'multiply';

            // Dibujamos el recorte real del producto centrado y con escala visual uniforme en el contenedor
            ctx.drawImage(trimCanvas,
                srcX,
                srcY,
                prodW,
                prodH,
                targetCenterX - (finalW / 2),
                targetCenterY - (finalH / 2),
                finalW,
                finalH
            );

            ctx.globalCompositeOperation = 'source-over';

            canvas.toBlob((blob) => {
                resolve(new File([blob], 'flyer.jpg', { type: 'image/jpeg' }));
            }, 'image/jpeg', 0.95);
        });
    }

    window.aplicarPlantilla = aplicarPlantilla;
    window.MOTORCYCLE_BRANDS = MOTORCYCLE_BRANDS;
    window.isBrandToken = isBrandToken;
    window.tokenizeLineForCanvas = tokenizeLineForCanvas;
    window.splitTitleIntoBalancedLines = splitTitleIntoBalancedLines;

    async function extractCleanProductIfFlyer(imgSource) {
        return new Promise((resolve) => {
            const img = new Image();
            img.crossOrigin = 'Anonymous';
            img.onload = () => {
                const isSquareFlyer = (img.naturalWidth === 2048 && img.naturalHeight === 2048) ||
                                (Math.abs((img.naturalWidth / img.naturalHeight) - 1.0) < 0.05 && img.naturalHeight >= 800);
                const isLegacyFlyer = (img.naturalWidth === 928 && img.naturalHeight === 1150) ||
                                (Math.abs((img.naturalWidth / img.naturalHeight) - (928 / 1150)) < 0.05 && img.naturalHeight >= 800);

                if (!isSquareFlyer && !isLegacyFlyer) {
                    resolve(imgSource);
                    return;
                }

                let cropX, cropY, cropW, cropH;
                if (isSquareFlyer) {
                    cropX = Math.round(img.naturalWidth * 0.15);
                    cropY = Math.round(img.naturalHeight * 0.26);
                    cropW = Math.round(img.naturalWidth * 0.70);
                    cropH = Math.round(img.naturalHeight * 0.48);
                } else {
                    cropX = Math.round(img.naturalWidth * 0.14);
                    cropY = Math.round(img.naturalHeight * 0.33);
                    cropW = Math.round(img.naturalWidth * 0.72);
                    cropH = Math.round(img.naturalHeight * 0.47);
                }

                const canvas = document.createElement('canvas');
                canvas.width = cropW;
                canvas.height = cropH;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);

                canvas.toBlob((blob) => {
                    if (blob) {
                        const reader = new FileReader();
                        reader.onload = () => resolve(reader.result);
                        reader.onerror = () => resolve(imgSource);
                        reader.readAsDataURL(blob);
                    } else {
                        resolve(imgSource);
                    }
                }, 'image/jpeg', 0.95);
            };
            img.onerror = () => resolve(imgSource);
            img.src = (typeof imgSource === 'string') ? imgSource : URL.createObjectURL(imgSource);
        });
    }

    window.extractCleanProductIfFlyer = extractCleanProductIfFlyer;

    window.enhanceAllImages = async function() {
        const btn = document.getElementById('btnEnhanceAll');
        const statusDiv = document.getElementById('enhanceAllStatus');
        const statusText = document.getElementById('enhanceAllStatusText');
        const originalText = btn.innerHTML;

        try {
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';
            btn.disabled = true;
            statusDiv.classList.remove('d-none');

            const productId = "{{ $product->id ?? '' }}";
            const csrf = document.querySelector('input[name="_token"]').value;

            // 1. Recopilar imagenes principales
            const mainImg = document.getElementById('existing_main_img');
            const galleryImgs = document.querySelectorAll('.existing_gallery_img');

            let total = (mainImg ? 1 : 0) + galleryImgs.length;
            let current = 0;

            async function processOne(url, type, id, statusEl) {
                current++;
                statusText.textContent = `Procesando imagen ${current} de ${total}...`;
                if(statusEl) statusEl.textContent = 'Procesando...';

                const response = await fetch(url);
                const blob = await response.blob();

                // Si la imagen ya tiene marco de plantilla/flyer, recortamos la pieza de caucho del centro
                const cleanSource = await extractCleanProductIfFlyer(blob);
                const cleanBlob = (cleanSource === blob) ? blob : await (await fetch(cleanSource)).blob();
                const file = new File([cleanBlob], `image_${id}.jpg`, { type: cleanBlob.type || 'image/jpeg' });

                const result = await processImageToStudioWhite(file, (msg) => {
                    if(statusEl) statusEl.textContent = msg;
                }, 'enhance');

                const productNameInput = document.querySelector('input[name="name"]');
                const productName = (productNameInput ? productNameInput.value : '') || 'PRODUCTO';

                // Para imágenes de galería, generamos el flyer con la plantilla y lo subimos directamente
                let fileToUpload = result.file;
                if (type === 'gallery') {
                    if(statusEl) statusEl.textContent = 'Aplicando plantilla...';
                    fileToUpload = await aplicarPlantilla(result.file, productName);
                }

                const formData = new FormData();
                formData.append('image', fileToUpload);
                formData.append('_token', csrf);

                let endpoint = type === 'main'
                    ? `{{ url('/productos') }}/${id}/replace-main-image`
                    : `{{ url('/productos/imagenes') }}/${id}/replace`;

                const uploadRes = await fetch(endpoint, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });

                const data = await uploadRes.json();

                if (data.success) {
                    let finalPath = data.path;

                    if (type === 'main') {
                        if(statusEl) statusEl.textContent = 'Aplicando plantilla...';
                        const flyerFile = await aplicarPlantilla(result.file, productName);

                        const flyerData = new FormData();
                        flyerData.append('flyer', flyerFile);
                        flyerData.append('_token', csrf);

                        const flyerRes = await fetch(`{{ url('/productos') }}/${id}/upload-flyer`, {
                            method: 'POST',
                            body: flyerData,
                            headers: { 'Accept': 'application/json' }
                        });

                        const flyerDataJson = await flyerRes.json();
                        if (flyerDataJson.success) {
                            // Actualizamos finalPath para que el UI muestre el flyer generado
                            finalPath = flyerDataJson.path;
                        }
                    }

                    if(statusEl) {
                        statusEl.classList.remove('text-primary');
                        statusEl.classList.add('text-success');
                        statusEl.textContent = '¡Mejorada con plantilla!';
                    }
                    return { display: finalPath, source: data.path };
                }
                throw new Error('Error al guardar');
            }

            if (mainImg) {
                const url = mainImg.getAttribute('data-url');
                const result = await processOne(url, 'main', productId, document.getElementById('status_existing_main'));
                mainImg.src = result.display + '?t=' + new Date().getTime();
                mainImg.setAttribute('data-url', result.source);
            }

            for (let i = 0; i < galleryImgs.length; i++) {
                const img = galleryImgs[i];
                const url = img.getAttribute('data-url');
                const id = img.getAttribute('data-id');
                const statusEl = document.getElementById('status_existing_gallery_' + id);
                const result = await processOne(url, 'gallery', id, statusEl);
                img.src = result.display + '?t=' + new Date().getTime();
                img.setAttribute('data-url', result.source);
            }

            statusText.textContent = '¡Todas las imágenes mejoradas!';
            statusDiv.classList.remove('text-primary');
            statusDiv.classList.add('text-success');
            if (window.toastr) toastr.success('Todas las imágenes fueron estandarizadas con IA.');

        } catch (error) {
            console.error('Error al mejorar imagenes:', error);
            if (window.toastr) toastr.error('Error: ' + error.message);
            statusText.textContent = 'Ocurrió un error.';
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    };

    function isGeminiExtensionInstalled() {
        return !!document.getElementById('__cauchos_gemini_bridge_installed__');
    }

    function collectAllProductImages() {
        const items = [];

        // 1. Imagen principal
        const mainFileInput = document.getElementById('mainImageInput');
        const existingMain = document.getElementById('existing_main_img');

        if (mainFileInput && mainFileInput.files && mainFileInput.files[0]) {
            items.push({
                type: 'main',
                id: '{{ $product->id ?? "" }}',
                jobId: 'main',
                label: 'Imagen Principal (Nueva)',
                file: mainFileInput.files[0],
                url: null,
                statusEl: document.getElementById('status_existing_main') || document.getElementById('mainImageStatusText')
            });
        } else if (existingMain) {
            const url = existingMain.getAttribute('data-url') || existingMain.src;
            if (url) {
                items.push({
                    type: 'main',
                    id: '{{ $product->id ?? "" }}',
                    jobId: 'main',
                    label: 'Imagen Principal',
                    file: null,
                    url: url,
                    imgEl: existingMain,
                    statusEl: document.getElementById('status_existing_main')
                });
            }
        }

        // 2. Imágenes de galería existentes
        const galleryImgs = document.querySelectorAll('.existing_gallery_img');
        galleryImgs.forEach((img, idx) => {
            const id = img.getAttribute('data-id');
            const url = img.getAttribute('data-url') || img.src;
            if (url && id) {
                items.push({
                    type: 'gallery',
                    id: id,
                    jobId: 'gallery_' + id,
                    label: `Galería #${idx + 1}`,
                    file: null,
                    url: url,
                    imgEl: img,
                    statusEl: document.getElementById('status_existing_gallery_' + id)
                });
            }
        });

        return items;
    }

    async function getImageBase64ForItem(item) {
        let base64 = null;
        if (item.file) {
            base64 = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(item.file);
            });
        } else if (item.url) {
            try {
                const res = await fetch(item.url);
                const blob = await res.blob();
                base64 = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(blob);
                });
            } catch (e) {
                console.warn("Fetch de item url falló, intentando canvas...", e);
                if (item.imgEl) {
                    const canvas = document.createElement('canvas');
                    canvas.width = item.imgEl.naturalWidth || 600;
                    canvas.height = item.imgEl.naturalHeight || 600;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(item.imgEl, 0, 0);
                    base64 = canvas.toDataURL('image/jpeg', 0.95);
                }
            }
        }

        if (!base64) {
            throw new Error('No se pudo obtener datos de la imagen: ' + item.label);
        }

        // Enviar la imagen 100% completa a Gemini sin ningún recorte
        return base64;
    }

    function buildGeminiStudioPrompt() {
        return @json(env('PROMPT_GEMINI') ?: config('services.gemini.prompt'), JSON_UNESCAPED_UNICODE);
    }

    window.buildGeminiStudioPrompt = buildGeminiStudioPrompt;

    function runGeminiGenerationBridge(promptText, productId, imageBase64, jobId, isLast, onStepStatus) {
        return new Promise((resolve, reject) => {
            let settled = false;

            const onMsg = (event) => {
                if (event.source !== window || !event.data || typeof event.data !== 'object') return;

                if (event.data.productId == productId && event.data.jobId == jobId) {
                    if (event.data.type === 'CAUCHOS_GEMINI_STATUS') {
                        if (onStepStatus) onStepStatus(event.data.status);
                    } else if (event.data.type === 'CAUCHOS_GEMINI_SUCCESS') {
                        if (settled) return;
                        settled = true;
                        window.removeEventListener('message', onMsg);
                        resolve(event.data.imageBase64);
                    } else if (event.data.type === 'CAUCHOS_GEMINI_ERROR') {
                        if (settled) return;
                        settled = true;
                        window.removeEventListener('message', onMsg);
                        reject(new Error(event.data.error || 'Fallo en Gemini Web'));
                    }
                }
            };

            window.addEventListener('message', onMsg);

            window.postMessage({
                type: 'CAUCHOS_START_GEMINI_AUTOMATION',
                prompt: promptText,
                productId: productId,
                jobId: jobId,
                imageBase64: imageBase64,
                isLast: isLast
            }, '*');

            // Timeout por imagen individual: 140s
            setTimeout(() => {
                if (!settled) {
                    settled = true;
                    window.removeEventListener('message', onMsg);
                    reject(new Error('Tiempo de espera agotado (140s) en Gemini Web'));
                }
            }, 140000);
        });
    }

    window.generateWithGeminiExtension = async function() {
        const btn = document.getElementById('btnGeminiExtension');
        const statusDiv = document.getElementById('geminiExtensionStatus');
        const statusText = document.getElementById('geminiExtensionStatusText');
        const productId = '{{ $product->id ?? "" }}';
        const csrf = '{{ csrf_token() }}';

        if (!productId) {
            if (window.toastr) toastr.warning('Debes guardar el producto primero antes de generar con Gemini.');
            return;
        }

        // 1. Verificar si la extensión de Chrome está instalada
        if (!isGeminiExtensionInstalled()) {
            const extensionPath = "e:\\xampp\\htdocs\\cauchos-alfa\\extension-gemini-bridge";
            if (window.Swal) {
                Swal.fire({
                    icon: 'info',
                    title: 'Extensión de Chrome Requerida',
                    html: `Para conectar automáticamente con tu cuenta <b>Google Gemini Pro</b> sin pagar APIs, carga la extensión:<br><br>
                    <ol style="text-align: left; font-size: 13px;">
                        <li>Abre <b>chrome://extensions/</b> en una pestaña.</li>
                        <li>Activa <b>Modo de desarrollador</b> (arriba a la derecha).</li>
                        <li>Haz clic en <b>"Cargar descomprimida"</b>.</li>
                        <li>Selecciona la carpeta: <br><code style="font-size: 11px; background:#eee; padding:2px 4px; border-radius:3px;">${extensionPath}</code></li>
                    </ol>
                    <small class="text-muted">¡Solo se hace una vez y queda 100% automático para siempre!</small>`,
                    confirmButtonText: 'Entendido'
                });
            } else if (window.toastr) {
                toastr.info('Carga la extensión desde ' + extensionPath, 'Extensión Requerida', { timeOut: 15000 });
            }
            return;
        }

        const items = collectAllProductImages();
        if (items.length === 0) {
            if (window.toastr) toastr.warning('No se encontraron imágenes en el producto para procesar.');
            return;
        }

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm mr-1"></span> Procesando con Gemini Pro...`;

        if (statusDiv) {
            statusDiv.classList.remove('d-none', 'text-danger', 'text-success');
            statusDiv.classList.add('text-primary');
            statusText.textContent = `Preparando ${items.length} ${items.length === 1 ? 'imagen' : 'imágenes'} para Gemini Pro...`;
        }

        const promptText = buildGeminiStudioPrompt();
        let successCount = 0;
        let failCount = 0;

        try {
            setProcessingState(true, 'Procesando con Gemini Pro e IA Local...');

            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                const isLast = (i === items.length - 1);
                const prefix = `[${i + 1}/${items.length} - ${item.label}]`;

                statusText.textContent = `${prefix} Extrayendo imagen de referencia...`;
                if (item.statusEl) {
                    item.statusEl.classList.remove('text-success', 'text-danger');
                    item.statusEl.classList.add('text-primary');
                    item.statusEl.textContent = 'Iniciando Gemini...';
                }

                let imageBase64 = null;
                try {
                    imageBase64 = await getImageBase64ForItem(item);
                } catch (e) {
                    console.error(`${prefix} Error obteniendo base64:`, e);
                    if (item.statusEl) {
                        item.statusEl.classList.remove('text-primary');
                        item.statusEl.classList.add('text-danger');
                        item.statusEl.textContent = 'Error al leer imagen';
                    }
                    failCount++;
                    continue;
                }

                statusText.textContent = `${prefix} Enviando a Gemini Pro en Chrome...`;

                try {
                    // Generar en Gemini Web
                    const geminiBase64 = await runGeminiGenerationBridge(
                        promptText,
                        productId,
                        imageBase64,
                        item.jobId,
                        isLast,
                        (stepStatus) => {
                            statusText.textContent = `${prefix} ${stepStatus}`;
                            if (item.statusEl) item.statusEl.textContent = stepStatus;
                        }
                    );

                    // Aislar objeto con IA local (Transformers.js RMBG-1.4)
                    statusText.textContent = `${prefix} Foto recibida de Gemini. Recortando con IA local...`;
                    if (item.statusEl) item.statusEl.textContent = 'IA Local recortando...';

                    const resBlob = await (await fetch(geminiBase64)).blob();
                    const rawGeminiFile = new File([resBlob], `gemini_${item.type}_${item.id}.jpg`, { type: 'image/jpeg' });

                    let localResult = null;
                    try {
                        localResult = await processImageToStudioWhite(rawGeminiFile, (msg) => {
                            statusText.textContent = `${prefix} [IA Local] ${msg}`;
                        }, 'gemini');
                    } catch (rmbgErr) {
                        console.warn(`${prefix} RMBG-1.4 local no disponible, usando imagen directa de Gemini:`, rmbgErr);
                        localResult = {
                            file: rawGeminiFile,
                            previewUrl: geminiBase64,
                            processed: false
                        };
                    }

                    const isolatedFile = localResult.file;

                    if (item.type === 'main') {
                        statusText.textContent = `${prefix} Guardando imagen principal limpia en el catálogo...`;
                        const mainFormData = new FormData();
                        mainFormData.append('image', isolatedFile);
                        mainFormData.append('_token', csrf);

                        const uploadRes = await fetch(`{{ url('/productos') }}/${productId}/replace-main-image`, {
                            method: 'POST',
                            body: mainFormData,
                            headers: { 'Accept': 'application/json' }
                        });
                        const uploadJson = await uploadRes.json().catch(() => ({}));
                        if (!uploadRes.ok || !uploadJson.success) {
                            throw new Error(uploadJson.message || `Error HTTP ${uploadRes.status} al guardar imagen principal`);
                        }

                        // Generar flyer publicitario institucional oficial
                        statusText.textContent = `${prefix} Generando y aplicando plantilla de flyer oficial...`;
                        const productNameInput = document.querySelector('input[name="name"]');
                        const productName = (productNameInput ? productNameInput.value : '') || 'PRODUCTO';
                        const flyerFile = await aplicarPlantilla(isolatedFile, productName);

                        const flyerData = new FormData();
                        flyerData.append('flyer', flyerFile);
                        flyerData.append('_token', csrf);

                        const flyerRes = await fetch(`{{ url('/productos') }}/${productId}/upload-flyer`, {
                            method: 'POST',
                            body: flyerData,
                            headers: { 'Accept': 'application/json' }
                        });
                        const flyerJson = await flyerRes.json().catch(() => ({}));
                        if (!flyerRes.ok || !flyerJson.success) {
                            throw new Error(flyerJson.message || `Error HTTP ${flyerRes.status} al guardar flyer`);
                        }

                        // Actualizar visiblemente la sección Imagen Principal
                        if (mainPreviewImg) mainPreviewImg.src = localResult.previewUrl;
                        if (mainFileName) mainFileName.textContent = 'gemini_enhanced.jpg (Guardada en catálogo)';
                        if (mainBadge) {
                            mainBadge.className = 'badge badge-success mb-1';
                            mainBadge.innerHTML = '<i class="fab fa-google mr-1"></i> Mejorada con Gemini Pro (Fondo Blanco)';
                        }
                        if (mainPreviewArea) {
                            mainPreviewArea.classList.remove('d-none');
                            mainPreviewArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                        if (mainInput) mainInput.value = '';

                        // Actualizar miniatura del catálogo
                        const mainImg = document.getElementById('existing_main_img');
                        if (mainImg) {
                            if (flyerJson.success && flyerJson.path) {
                                mainImg.src = flyerJson.path + '?t=' + new Date().getTime();
                            } else if (uploadJson.path) {
                                mainImg.src = uploadJson.path + '?t=' + new Date().getTime();
                            }
                            if (uploadJson.path) mainImg.setAttribute('data-url', uploadJson.path);
                        }

                        if (item.statusEl) {
                            item.statusEl.classList.remove('text-primary');
                            item.statusEl.classList.add('text-success');
                            item.statusEl.textContent = '¡Mejorada con Gemini + Flyer!';
                        }
                    } else {
                        // Galería: Aplicar plantilla institucional al igual que en la imagen principal
                        statusText.textContent = `${prefix} Generando y aplicando plantilla de flyer oficial...`;
                        const productNameInput = document.querySelector('input[name="name"]');
                        const productName = (productNameInput ? productNameInput.value : '') || 'PRODUCTO';
                        const galleryFlyerFile = await aplicarPlantilla(isolatedFile, productName);

                        statusText.textContent = `${prefix} Guardando imagen de galería con plantilla en el catálogo...`;
                        const galleryFormData = new FormData();
                        galleryFormData.append('image', galleryFlyerFile);
                        galleryFormData.append('_token', csrf);

                        const galleryRes = await fetch(`{{ url('/productos/imagenes') }}/${item.id}/replace`, {
                            method: 'POST',
                            body: galleryFormData,
                            headers: { 'Accept': 'application/json' }
                        });
                        const galleryJson = await galleryRes.json().catch(() => ({}));
                        if (!galleryRes.ok || !galleryJson.success) {
                            throw new Error(galleryJson.message || `Error HTTP ${galleryRes.status} al guardar imagen de galería`);
                        }

                        if (item.imgEl && galleryJson.path) {
                            item.imgEl.src = galleryJson.path + '?t=' + new Date().getTime();
                            item.imgEl.setAttribute('data-url', galleryJson.path);
                        }

                        if (item.statusEl) {
                            item.statusEl.classList.remove('text-primary');
                            item.statusEl.classList.add('text-success');
                            item.statusEl.textContent = '¡Mejorada con Gemini + Plantilla!';
                        }
                    }

                    successCount++;

                    // Pequeña pausa antes de procesar la siguiente imagen para que Gemini Web esté completamente listo
                    if (!isLast) {
                        statusText.textContent = `${prefix} ¡Completada! Preparando siguiente imagen en 2.5 segundos...`;
                        await new Promise(r => setTimeout(r, 2500));
                    }

                } catch (err) {
                    console.error(`${prefix} Error:`, err);
                    if (item.statusEl) {
                        item.statusEl.classList.remove('text-primary');
                        item.statusEl.classList.add('text-danger');
                        item.statusEl.textContent = 'Error: ' + err.message;
                    }
                    failCount++;
                    if (window.toastr) {
                        toastr.warning(`Error en ${item.label}: ${err.message}`);
                    }
                    if (!isLast) {
                        await new Promise(r => setTimeout(r, 2000));
                    }
                }
            }

            if (successCount > 0) {
                statusText.textContent = `¡Listo! Se mejoraron ${successCount} de ${items.length} imágenes con Gemini Pro e IA Local.`;
                statusDiv.classList.remove('text-primary', 'text-danger');
                statusDiv.classList.add('text-success');
                if (window.toastr) {
                    toastr.success(`¡Éxito! ${successCount} imágenes procesadas con Gemini Pro y recortadas con fondo blanco puro.`);
                }
            } else {
                statusText.textContent = 'No se pudo completar el procesamiento de las imágenes.';
                statusDiv.classList.remove('text-primary', 'text-success');
                statusDiv.classList.add('text-danger');
            }

        } catch (globalErr) {
            console.error('Error general en generateWithGeminiExtension:', globalErr);
            statusDiv.classList.remove('text-primary');
            statusDiv.classList.add('text-danger');
            statusText.textContent = 'Error: ' + globalErr.message;
            if (window.toastr) toastr.error('Error: ' + globalErr.message);
        } finally {
            setProcessingState(false);
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    };

    // Prevenir envío prematuro si las imágenes aún se están procesando
    const form = document.querySelector('form[action*="productos"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (activeProcessCount > 0) {
                e.preventDefault();
                if (window.toastr) {
                    toastr.warning('Por favor espera a que finalice el procesamiento con IA antes de guardar.');
                }
            }
        });
    }
</script>
@endpush


