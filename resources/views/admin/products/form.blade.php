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

                        @if ($product->exists && ($product->images->isNotEmpty() || $product->image_path))
                        <div class="col-md-12 mb-3">
                            <button type="button" id="btnEnhanceAll" class="btn btn-outline-primary font-weight-bold" onclick="enhanceAllImages()">
                                <i class="fas fa-magic mr-1"></i> Estandarizar y Mejorar imágenes ya cargadas
                            </button>
                            <div id="enhanceAllStatus" class="mt-2 text-primary small d-none font-weight-bold">
                                <span class="spinner-border spinner-border-sm mr-1"></span>
                                <span id="enhanceAllStatusText">Iniciando proceso...</span>
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
    let removeBgModule = null;
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

    // Pre-carga inmediata del módulo de IA en WebAssembly por URL (CDN) y precalentamiento del modelo
    let removeBgPromise = (async () => {
        try {
            const module = await import('https://cdn.jsdelivr.net/npm/@imgly/background-removal@1.7.0/+esm');
            removeBgModule = module.removeBackground;
            console.log('✅ @imgly/background-removal cargado exitosamente desde jsdelivr.');

            // Precalentar modelo ligero en segundo plano mientras el usuario llena el formulario
            if (typeof module.preload === 'function') {
                module.preload({ model: 'medium' }).then(() => {
                    console.log('🚀 Modelo IA alta calidad (medium) precalentado en memoria.');
                }).catch(() => {});
            }
            return removeBgModule;
        } catch (e) {
            console.error('❌ Error crítico al cargar @imgly/background-removal por URL:', e);
            throw e;
        }
    })();

    async function getRemoveBgFunction() {
        if (removeBgModule) return removeBgModule;
        return await removeBgPromise;
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

    // Procesar una imagen para aislar objeto y montarlo sobre fondo blanco puro (#ffffff)
    async function processImageToStudioWhite(file, onProgress, mode = 'creation') {
        const isAutoEnabled = autoSwitch ? autoSwitch.checked : true;
        if (!isAutoEnabled) {
            return {
                file: file,
                previewUrl: URL.createObjectURL(file),
                processed: false
            };
        }

        const isEnhance = mode === 'enhance';

        // 1. Reducir dimensiones ANTES de inferencia SOLO si es modo creacion rapido.
        if (onProgress) onProgress(isEnhance ? 'Preparando imagen original...' : 'Optimizando resolucion...');
        const optimizedFile = isEnhance ? file : await downscaleImageIfNeeded(file, 1400);

        if (onProgress) onProgress('Cargando motor de IA...');
        const removeBg = await getRemoveBgFunction();

        const aiModel = isEnhance ? 'isnet' : 'isnet_quint8';

        if (onProgress) onProgress('Aislando producto con IA (' + (isEnhance ? 'Alta Calidad' : 'Rapido') + ')...');
        const transparentBlob = await removeBg(optimizedFile, {
            debug: false,
            model: aiModel,
            progress: (key, current, total) => {
                if (onProgress && total > 0) {
                    const pct = Math.min(100, Math.round((current / total) * 100));
                    onProgress('Segmentando objeto (' + pct + '%)...');
                }
            }
        });

        if (onProgress) onProgress('Aplicando fondo blanco de alta fidelidad...');

        const img = new Image();
        const objectUrl = URL.createObjectURL(transparentBlob);
        await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = reject;
            img.src = objectUrl;
        });

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';

        if (isEnhance) {
            // 1. Dibujar en canvas temporal para recortar el espacio transparente
            const tempCanvas = document.createElement('canvas');
            const tempCtx = tempCanvas.getContext('2d');
            tempCanvas.width = img.naturalWidth || img.width;
            tempCanvas.height = img.naturalHeight || img.height;
            tempCtx.drawImage(img, 0, 0);

            // 2. Encontrar los limites (Bounding Box) y limpiar blancos falsos
            const imgData = tempCtx.getImageData(0, 0, tempCanvas.width, tempCanvas.height);
            const data = imgData.data;
            let minX = tempCanvas.width, minY = tempCanvas.height, maxX = 0, maxY = 0;
            let found = false;

            // Escaneo completo para borrar bolsas plasticas y encontrar limites
            for (let y = 0; y < tempCanvas.height; y++) {
                for (let x = 0; x < tempCanvas.width; x++) {
                    const idx = (y * tempCanvas.width + x) * 4;
                    const r = data[idx];
                    const g = data[idx + 1];
                    const b = data[idx + 2];
                    let alpha = data[idx + 3];

                    // FILTRO DE BLANCO FALSO (Bolsas plásticas, sombras claras)
                    // Si el pixel es muy claro (brillo promedio > 215) lo hacemos transparente
                    const brightness = (r + g + b) / 3;
                    if (brightness > 215 && alpha > 0) {
                        data[idx + 3] = 0; // Transparente total
                        alpha = 0;
                    }

                    if (alpha > 10) { // Umbral de transparencia para los bordes
                        if (x < minX) minX = x;
                        if (x > maxX) maxX = x;
                        if (y < minY) minY = y;
                        if (y > maxY) maxY = y;
                        found = true;
                    }
                }
            }

            // Aplicar los pixeles corregidos (bolsas eliminadas) al canvas temporal
            tempCtx.putImageData(imgData, 0, 0);

            let cropX = 0, cropY = 0, cropW = tempCanvas.width, cropH = tempCanvas.height;
            if (found) {
                cropX = minX;
                cropY = minY;
                cropW = maxX - minX + 1;
                cropH = maxY - minY + 1;
            }

            // 3. Dibujar en canvas final estandarizado de 1000x1000
            const TARGET_SIZE = 1000;
            const PADDING = 70; // Reducido a 70px para que el objeto se vea aun mas grande y detallado

            canvas.width = TARGET_SIZE;
            canvas.height = TARGET_SIZE;

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // 4. Escalar SOLO el objeto recortado (no todo el espacio vacio)
            const availableSize = TARGET_SIZE - (PADDING * 2);
            const scale = Math.min(availableSize / cropW, availableSize / cropH);

            const drawWidth = cropW * scale;
            const drawHeight = cropH * scale;
            const dx = (TARGET_SIZE - drawWidth) / 2;
            const dy = (TARGET_SIZE - drawHeight) / 2;

            ctx.drawImage(tempCanvas, cropX, cropY, cropW, cropH, dx, dy, drawWidth, drawHeight);
        } else {
            canvas.width = img.naturalWidth || img.width;
            canvas.height = img.naturalHeight || img.height;

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);
        }

        URL.revokeObjectURL(objectUrl);

        return new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (!blob) {
                    return reject(new Error('Fallo al exportar canvas a blob'));
                }
                const cleanName = file.name.replace(/\.[^/.]+$/, "") + ".jpg";
                const whiteBgFile = new File([blob], cleanName, { type: 'image/jpeg' });
                resolve({
                    file: whiteBgFile,
                    previewUrl: canvas.toDataURL('image/jpeg', 1.0),
                    processed: true
                });
            }, 'image/jpeg', 1.0);
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
                const file = new File([blob], `image_${id}.jpg`, { type: blob.type || 'image/jpeg' });

                const result = await processImageToStudioWhite(file, (msg) => {
                    if(statusEl) statusEl.textContent = msg;
                }, 'enhance');

                const formData = new FormData();
                formData.append('image', result.file);
                formData.append('_token', csrf);

                let endpoint = type === 'main'
                    ? `/productos/${id}/replace-main-image`
                    : `/productos/imagenes/${id}/replace`;

                const uploadRes = await fetch(endpoint, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });

                const data = await uploadRes.json();
                if (data.success) {
                    if(statusEl) {
                        statusEl.classList.remove('text-primary');
                        statusEl.classList.add('text-success');
                        statusEl.textContent = '¡Mejorada!';
                    }
                    return data.path;
                }
                throw new Error('Error al guardar');
            }

            if (mainImg) {
                const url = mainImg.getAttribute('data-url');
                const newPath = await processOne(url, 'main', productId, document.getElementById('status_existing_main'));
                mainImg.src = newPath + '?t=' + new Date().getTime();
            }

            for (let i = 0; i < galleryImgs.length; i++) {
                const img = galleryImgs[i];
                const url = img.getAttribute('data-url');
                const id = img.getAttribute('data-id');
                const statusEl = document.getElementById('status_existing_gallery_' + id);
                const newPath = await processOne(url, 'gallery', id, statusEl);
                img.src = newPath + '?t=' + new Date().getTime();
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


