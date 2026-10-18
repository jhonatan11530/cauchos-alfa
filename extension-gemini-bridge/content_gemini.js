// content_gemini.js - Inyectado en gemini.google.com

console.log('[Cauchos Alfa] Script de automatización inyectado en Gemini Web.');

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message.action === 'EXECUTE_GEMINI_PROMPT') {
        const { prompt, productId, jobId, imageBase64 } = message;
        console.log('[Cauchos Alfa] Ejecutando prompt para producto:', productId, 'jobId:', jobId, { hasImage: !!imageBase64 });
        executePromptInGemini(prompt, productId, jobId || 'main', imageBase64);
        sendResponse({ executing: true });
    }
    return true;
});

function sendStepProgress(status) {
    console.log('[Cauchos Alfa Gemini Content]', status);
    chrome.runtime.sendMessage({
        action: 'GEMINI_STEP_PROGRESS',
        status: status
    }).catch(() => {});
}

function dataUrlToFile(dataUrl, filename = 'repuesto_cauchos_alfa.jpg') {
    const arr = dataUrl.split(',');
    const mimeMatch = arr[0].match(/:(.*?);/);
    const mime = mimeMatch ? mimeMatch[1] : 'image/jpeg';
    const bstr = atob(arr[1]);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);
    while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
    }
    return new File([u8arr], filename, { type: mime });
}

function hasAttachmentPreviewInComposer() {
    const previewSelectors = [
        'uploader img',
        '.uploader img',
        'user-query-file-preview',
        '.file-preview',
        '.attachment-container img',
        'rich-textarea img',
        'input-area img',
        'img.preview-image',
        '[data-test-id*="uploaded"]',
        '[data-test-id*="file-preview"]',
        'uploader button[aria-label*="Eliminar" i]',
        'uploader button[aria-label*="Remove" i]',
        'uploader button[aria-label*="Quitar" i]',
        'rich-textarea button[aria-label*="Eliminar" i]',
        'rich-textarea button[aria-label*="Remove" i]',
        'rich-textarea button[aria-label*="Quitar" i]',
        '.attachment-close-button',
        '[data-test-id*="remove-file"]',
        'button[aria-label*="Quitar archivo" i]',
        'button[aria-label*="Remove file" i]'
    ];
    return document.querySelector(previewSelectors.join(',')) !== null;
}

function isComposerUploadingImage() {
    const spinnerSelectors = [
        'uploader mat-progress-spinner',
        'uploader [role="progressbar"]',
        '.uploader mat-progress-spinner',
        'user-query-file-preview mat-progress-spinner',
        'user-query-file-preview [role="progressbar"]',
        '.file-preview mat-progress-spinner',
        '.file-preview [role="progressbar"]',
        'rich-textarea mat-progress-spinner',
        'rich-textarea [role="progressbar"]'
    ];
    return document.querySelector(spinnerSelectors.join(',')) !== null;
}

function attachImageToGemini(editor, file) {
    let attached = false;
    let pasteSucceeded = false;

    // 1. Intentar mediante ClipboardEvent('paste') en el editor
    try {
        editor.focus();
        const dt = new DataTransfer();
        dt.items.add(file);
        const pasteEvent = new ClipboardEvent('paste', {
            bubbles: true,
            cancelable: true,
            composed: true,
            clipboardData: dt
        });
        editor.dispatchEvent(pasteEvent);
        pasteSucceeded = hasAttachmentPreviewInComposer();
        if (pasteSucceeded) {
            attached = true;
            console.log('[Cauchos Alfa] Imagen adjuntada mediante paste event al editor.');
        }
    } catch (pasteErr) {
        console.warn('[Cauchos Alfa] Error al disparar paste event:', pasteErr);
    }

    // 2. Intentar mediante Drag & Drop si paste no montó la vista previa inmediatamente
    if (!pasteSucceeded) {
        try {
            const dtDrop = new DataTransfer();
            dtDrop.items.add(file);
            const dropTarget = editor.closest('rich-textarea') || editor;
            for (const evtName of ['dragenter', 'dragover', 'drop']) {
                const dropEvent = new DragEvent(evtName, {
                    bubbles: true,
                    cancelable: true,
                    composed: true,
                    dataTransfer: dtDrop
                });
                dropTarget.dispatchEvent(dropEvent);
            }
            pasteSucceeded = hasAttachmentPreviewInComposer();
            if (pasteSucceeded) {
                attached = true;
            }
        } catch (dropErr) {}
    }

    // 3. Intentar mediante input[type="file"] en el DOM si aún no se ha adjuntado
    try {
        let fileInputs = Array.from(document.querySelectorAll('input[type="file"]'));

        // Regla R1: Si paste ya tuvo éxito o ya existen file inputs directos,
        // NUNCA disparar clics sintéticos que puedan activar miniaturas.
        if (fileInputs.length === 0 && !pasteSucceeded) {
            const strictUploadSelectors = [
                'button[aria-label*="Añadir archivos"]',
                'button[aria-label*="Add files"]',
                'button[aria-label*="Subir archivos"]',
                'button[aria-label*="Upload files"]',
                'button[aria-label*="Subir archivo"]',
                'button[aria-label*="Upload file"]',
                'button[aria-label*="Subir imagen"]',
                'button[aria-label*="Upload image"]',
                'button[aria-label="Abrir menú para subir archivos"]',
                'button[aria-label="Open upload file menu"]',
                '[data-test-id="upload-button"]'
            ];

            const candidates = Array.from(document.querySelectorAll(strictUploadSelectors.join(',')));
            // Filtrar estrictamente cualquier botón que sea o esté dentro de una tarjeta de vista previa / miniatura
            const realUploadBtn = candidates.find(btn => {
                const isInsidePreview = btn.closest('uploader, .uploader, user-query-file-preview, [data-test-id*="uploaded"], .file-preview, .attachment-container');
                const hasThumbnail = btn.querySelector('img') !== null;
                const ariaLabel = (btn.getAttribute('aria-label') || '').toLowerCase();
                const isFileChip = ariaLabel.includes('.jpg') || ariaLabel.includes('.jpeg') || ariaLabel.includes('.png') || ariaLabel.includes('.webp') || ariaLabel.includes('repuesto');
                return !isInsidePreview && !hasThumbnail && !isFileChip;
            });

            if (realUploadBtn) {
                console.log('[Cauchos Alfa] Activando botón de subida real:', realUploadBtn);
                realUploadBtn.click();
                fileInputs = Array.from(document.querySelectorAll('input[type="file"]'));
            }
        }

        if (!pasteSucceeded && fileInputs.length > 0) {
            const fileInput = fileInputs[0];
            const dt2 = new DataTransfer();
            dt2.items.add(file);
            fileInput.files = dt2.files;
            fileInput.dispatchEvent(new Event('change', { bubbles: true }));
            fileInput.dispatchEvent(new Event('input', { bubbles: true }));
            attached = true;
            console.log('[Cauchos Alfa] Archivo asignado directamente al input[type="file"].');
        }
    } catch (inputErr) {
        console.warn('[Cauchos Alfa] Error asignando archivo a input file:', inputErr);
    }

    return attached;
}

function dismissGeminiLightbox() {
    let dismissed = false;

    // 1. Verificar presencia real de contenedor de diálogo/visor de archivo a pantalla completa
    // Excluyendo estrictamente el compositor (rich-textarea, uploader, user-query-file-preview)
    const viewerContainers = Array.from(document.querySelectorAll('[role="dialog"], image-viewer, media-viewer, expanded-image-view, full-screen-image-dialog, .preview-dialog, [aria-modal="true"]')).filter(c => {
        if (!c || (c.offsetParent === null && c.getClientRects().length === 0)) return false;
        if (c.closest('rich-textarea, uploader, .uploader, input-area, user-query-file-preview, .file-preview, .attachment-container')) return false;
        if (c.querySelector('rich-textarea, uploader, div[contenteditable="true"]')) return false;
        const txt = (c.innerText || '').toLowerCase();
        const tag = c.tagName.toLowerCase();
        if (tag.includes('viewer') || tag.includes('full-screen-image')) return true;
        return txt.includes('repuesto_cauchos_alfa.jpg') || (c.querySelector('img') !== null && (c.getAttribute('role') === 'dialog' || c.getAttribute('aria-modal') === 'true' || tag.includes('viewer')));
    });

    // 2. Localizar botón Atrás / Back o Cerrar característico del visor ÚNICAMENTE cuando hay un visor activo
    const backSelectors = [
        'image-viewer button[aria-label*="Atrás" i]',
        'image-viewer button[aria-label*="Back" i]',
        'image-viewer button[aria-label*="Volver" i]',
        'image-viewer button[aria-label*="Regresar" i]',
        '[data-test-id*="image-viewer"] button',
        '[role="dialog"] button[aria-label*="Cerrar" i]',
        '[role="dialog"] button[aria-label*="Close" i]',
        '[role="dialog"] button[aria-label*="Atrás" i]',
        '[role="dialog"] button[aria-label*="Back" i]',
        'image-viewer button',
        'media-viewer button',
        'expanded-image-view button',
        'full-screen-image-dialog button'
    ];

    let backBtn = null;
    if (viewerContainers.length > 0) {
        for (const sel of backSelectors) {
            const el = document.querySelector(sel);
            if (el && (el.offsetParent !== null || el.getClientRects().length > 0)) {
                const isInsideComposer = el.closest('rich-textarea, uploader, .uploader, input-area, user-query-file-preview, .file-preview, .attachment-container');
                if (!isInsideComposer) {
                    backBtn = el;
                    break;
                }
            }
        }

        if (!backBtn) {
            for (const container of viewerContainers) {
                const icon = container.querySelector('mat-icon[data-mat-icon-name="arrow_back"], mat-icon[font-icon="arrow_back"], mat-icon[data-mat-icon-name="close"]');
                if (icon) {
                    const candidateBtn = icon.closest('button');
                    if (candidateBtn && !candidateBtn.closest('rich-textarea, uploader, .uploader, input-area, user-query-file-preview, .file-preview, .attachment-container')) {
                        backBtn = candidateBtn;
                        break;
                    }
                }
            }
        }
    }

    const isViewerActive = viewerContainers.length > 0;

    // 3. Si el visor está realmente activo, ejecutar descarte inmediato
    if (isViewerActive) {
        console.warn('[Cauchos Alfa] Guardián de Lightbox: Se detectó visor emergente a pantalla completa. Descartando...');

        // Despachar evento Escape con composed: true a document, window, document.body y activeElement
        const targets = [document, window, document.body, document.activeElement].filter(Boolean);
        for (const target of targets) {
            try {
                target.dispatchEvent(new KeyboardEvent('keydown', {
                    key: 'Escape',
                    code: 'Escape',
                    keyCode: 27,
                    which: 27,
                    bubbles: true,
                    cancelable: true,
                    composed: true
                }));
                target.dispatchEvent(new KeyboardEvent('keyup', {
                    key: 'Escape',
                    code: 'Escape',
                    keyCode: 27,
                    which: 27,
                    bubbles: true,
                    cancelable: true,
                    composed: true
                }));
            } catch (e) {}
        }

        // Clic en botón Atrás / Cerrar si existe
        if (backBtn) {
            try {
                backBtn.click();
            } catch (e) {}
        }

        // Restaurar foco al editor de texto
        const editor = document.querySelector('rich-textarea div[contenteditable="true"]')
            || document.querySelector('div[contenteditable="true"][role="textbox"]')
            || document.querySelector('div[contenteditable="true"]')
            || document.querySelector('textarea');
        if (editor) {
            try {
                editor.focus();
            } catch (e) {}
        }

        dismissed = true;
    }

    return dismissed;
}

function startLightboxGuardian() {
    dismissGeminiLightbox();

    const intervalId = setInterval(() => {
        dismissGeminiLightbox();
    }, 250);

    let observer = null;
    try {
        observer = new MutationObserver(() => {
            dismissGeminiLightbox();
        });
        if (document.body) {
            observer.observe(document.body, { childList: true, subtree: true });
        }
    } catch (e) {}

    return {
        stop: () => {
            clearInterval(intervalId);
            if (observer) {
                observer.disconnect();
            }
        }
    };
}

async function executePromptInGemini(promptText, productId, jobId, imageBase64) {
    sendStepProgress('Conectando con el editor de Gemini Pro...');

    // Descartar lightbox si quedó activo de un turno anterior en pestaña reusada
    dismissGeminiLightbox();
    const guardian = startLightboxGuardian();

    try {
        // 1. Esperar y encontrar el editor de texto de Gemini
        let editor = null;
        let attempts = 0;
        while (!editor && attempts < 25) {
            dismissGeminiLightbox();
            editor = document.querySelector('rich-textarea div[contenteditable="true"]')
                || document.querySelector('div[contenteditable="true"][role="textbox"]')
                || document.querySelector('div[contenteditable="true"]')
                || document.querySelector('textarea');
            if (!editor) {
                await new Promise(r => setTimeout(r, 500));
                attempts++;
            }
        }

        if (!editor) {
            chrome.runtime.sendMessage({
                action: 'GEMINI_FAILED',
                error: 'No se encontró el campo de texto en Gemini Web.',
                productId: productId,
                jobId: jobId
            });
            return;
        }

        editor.scrollIntoView({ behavior: 'smooth', block: 'center' });
        editor.focus();

        // Limpiar posibles borradores o adjuntos pendientes en el editor antes de comenzar (uploader y rich-textarea)
        try {
            const removeButtons = document.querySelectorAll(
                'uploader button[aria-label*="Eliminar" i], uploader button[aria-label*="Remove" i], uploader button[aria-label*="Quitar" i], uploader button[aria-label*="Descartar" i], uploader button[aria-label*="Borrar" i], uploader button[aria-label*="Clear" i], ' +
                'rich-textarea button[aria-label*="Eliminar" i], rich-textarea button[aria-label*="Remove" i], rich-textarea button[aria-label*="Quitar" i], rich-textarea button[aria-label*="Descartar" i], rich-textarea button[aria-label*="Borrar" i], rich-textarea button[aria-label*="Clear" i], ' +
                '.attachment-close-button, [data-test-id*="remove-file"], button[aria-label*="Quitar archivo" i], button[aria-label*="Remove file" i]'
            );
            removeButtons.forEach(btn => {
                try { btn.click(); } catch (e) {}
            });
            if (removeButtons.length > 0) {
                await new Promise(r => setTimeout(r, 400));
            }
        } catch (e) {}

        // Limpieza profunda del texto previo del editor (antes de adjuntar la imagen)
        try {
            editor.focus();
            document.execCommand('selectAll', false, null);
            document.execCommand('delete', false, null);
            if (editor.textContent && editor.textContent.trim().length > 0) {
                editor.textContent = '';
            }
            if (editor.innerHTML && editor.innerHTML.replace(/<[^>]+>/g, '').trim().length > 0) {
                editor.innerHTML = '<p><br></p>';
            }
            editor.dispatchEvent(new Event('input', { bubbles: true }));
            editor.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (e) {}

        // 2. Si hay imagen de referencia del producto, adjuntarla y verificar que se monte en el compositor
        if (imageBase64) {
            sendStepProgress('Adjuntando foto del repuesto a Gemini Pro...');
            const file = dataUrlToFile(imageBase64);
            attachImageToGemini(editor, file);

            await new Promise(r => setTimeout(r, 600));

            // Si el evento desde el Isolated World no montó la miniatura, inyectar en el MAIN world de Gemini
            if (!hasAttachmentPreviewInComposer()) {
                console.log('[Cauchos Alfa] Adjuntando imagen mediante puente en MAIN world de Gemini...');
                await new Promise((resolve) => {
                    chrome.runtime.sendMessage({
                        action: 'ATTACH_IMAGE_MAIN_WORLD',
                        imageBase64: imageBase64,
                        filename: file.name
                    }, () => resolve());
                });
            }

            // Esperar a que la miniatura aparezca y termine de subir a los servidores de Gemini (sin spinner activo)
            let uploadWaitTicks = 0;
            while (uploadWaitTicks < 20) {
                await new Promise(r => setTimeout(r, 400));
                uploadWaitTicks++;
                if (hasAttachmentPreviewInComposer() && !isComposerUploadingImage() && uploadWaitTicks >= 5) {
                    break;
                }
            }

            // Pausa adicional para asegurar que el token del archivo adjunto esté listo en el estado de Angular
            await new Promise(r => setTimeout(r, 1200));

            // Descartar únicamente si algún visor modal a pantalla completa se abrió
            dismissGeminiLightbox();
        }

        // 3. Escribir el prompt en el editor (sin ejecutar selectAll/delete para no borrar el adjunto)
        dismissGeminiLightbox();
        sendStepProgress(`Escribiendo prompt: "${promptText}"...`);
        editor.focus();
        try {
            const sel = window.getSelection();
            if (sel && editor.childNodes.length > 0) {
                const range = document.createRange();
                range.selectNodeContents(editor);
                range.collapse(false);
                sel.removeAllRanges();
                sel.addRange(range);
            }
            const inserted = document.execCommand('insertText', false, promptText);
            if (!inserted && (!editor.textContent || editor.textContent.trim().length === 0)) {
                editor.textContent = promptText;
            }
        } catch (e) {
            if (!editor.textContent || editor.textContent.trim().length === 0) {
                editor.textContent = promptText;
            }
        }
        editor.dispatchEvent(new Event('input', { bubbles: true }));
        editor.dispatchEvent(new Event('change', { bubbles: true }));

        await new Promise(r => setTimeout(r, 1200));

        // 4. Registrar la cantidad de respuestas de modelo antes de enviar para saber cuál es la nueva
        const initialModelResponses = Array.from(document.querySelectorAll('model-response'));
        const initialResponseCount = initialModelResponses.length;
        console.log(`[Cauchos Alfa] Respuestas existentes de Gemini antes de enviar: ${initialResponseCount}`);

        // 5. Encontrar y hacer clic en el botón de Enviar (esperando si la imagen aún está subiendo)
        dismissGeminiLightbox();
        sendStepProgress('Enviando imagen y prompt a Gemini Pro...');
        let sendBtn = null;
        let waitSendCount = 0;

        while (waitSendCount < 30) {
            if (!isComposerUploadingImage()) {
                sendBtn = document.querySelector('button[aria-label*="Enviar"], button[aria-label*="Send"], button.send-button, mat-icon[data-mat-icon-name="send"]')?.closest('button')
                    || document.querySelector('button.send-button')
                    || document.querySelector('button[aria-label*="Submit"]');

                if (sendBtn && !sendBtn.disabled && sendBtn.getAttribute('aria-disabled') !== 'true') {
                    break;
                }
            }
            await new Promise(r => setTimeout(r, 500));
            waitSendCount++;
        }

        if (sendBtn && !sendBtn.disabled && sendBtn.getAttribute('aria-disabled') !== 'true') {
            sendBtn.click();
        } else {
            editor.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true }));
        }

        sendStepProgress('Gemini Pro está procesando y generando la imagen realista...');
        console.log('[Cauchos Alfa] Prompt enviado. Esperando respuesta generada...');

        // Esperar a que se registre la nueva respuesta de modelo
        const startWaitResponse = Date.now();
        let targetResponse = null;

        while (!targetResponse && (Date.now() - startWaitResponse) < 30000) {
            const currentResponses = Array.from(document.querySelectorAll('model-response'));
            if (currentResponses.length > initialResponseCount) {
                targetResponse = currentResponses[currentResponses.length - 1];
                console.log('[Cauchos Alfa] Detectada nueva respuesta de modelo (índice ' + (currentResponses.length - 1) + ')');
                break;
            }
            await new Promise(r => setTimeout(r, 600));
        }

        // Si aún no se detectó targetResponse, solo usar el último model-response si es nuevo respecto al turno anterior
        if (!targetResponse) {
            const allResponses = Array.from(document.querySelectorAll('model-response'));
            if (allResponses.length > initialResponseCount) {
                targetResponse = allResponses[allResponses.length - 1];
            }
        }

        // 6. Monitorear la respuesta hasta que Gemini termine de generar (timeout de 120s)
        const startTime = Date.now();
        let foundImage = null;

        while (!foundImage && (Date.now() - startTime) < 120000) {
            await new Promise(r => setTimeout(r, 1500));

            // Re-evaluar targetResponse si no se fijó antes o si se añadió un nuevo bloque
            const currentResponses = Array.from(document.querySelectorAll('model-response'));
            if (currentResponses.length > initialResponseCount) {
                targetResponse = currentResponses[currentResponses.length - 1];
            }

            // 1. Evaluar si ya está la imagen generada en la respuesta
            foundImage = findGeneratedImageInResponse(targetResponse);
            if (foundImage) {
                break;
            }

            // 2. Si todavía no está la imagen, comprobar si Gemini sigue generando (botón Detener o contenedor de carga activo)
            const stopBtn = document.querySelector('button[aria-label*="Detener"], button[aria-label*="Stop"], button.stop-button, [data-test-id*="stop"]');
            const isStopVisible = stopBtn && !stopBtn.disabled && stopBtn.offsetParent !== null;
            const hasPendingImageLoader = targetResponse && targetResponse.querySelector('generated-image, single-image, mat-progress-spinner, [role="progressbar"], .loading-skeleton, lottie-animation') !== null;

            if (isStopVisible || hasPendingImageLoader || (Date.now() - startTime) < 15000) {
                sendStepProgress('Gemini Pro está generando la imagen (en progreso)...');
                continue;
            }

            // 3. Si el botón Detener ya no está y pasaron al menos 15s sin loader de imagen, verificar si terminó el turno
            const sendBtnActive = document.querySelector('button[aria-label*="Enviar"], button[aria-label*="Send"], button.send-button');
            if (sendBtnActive && !sendBtnActive.disabled && sendBtnActive.getAttribute('aria-disabled') !== 'true') {
                // Dar 3s para asegurar que la imagen termine de montarse en el DOM si estaba renderizando
                await new Promise(r => setTimeout(r, 3000));
                foundImage = findGeneratedImageInResponse(targetResponse);
                if (foundImage) break;

                // Si Gemini terminó y NO hay imagen: respondió únicamente con texto
                if (targetResponse && !targetResponse.querySelector('generated-image, single-image, mat-progress-spinner, [role="progressbar"]')) {
                    const responseText = targetResponse.innerText || '';
                    console.warn('[Cauchos Alfa] Gemini terminó el turno pero no generó imagen. Texto recibido:', responseText);
                    chrome.runtime.sendMessage({
                        action: 'GEMINI_FAILED',
                        error: 'Gemini no generó una imagen (respondió con texto): "' + responseText.substring(0, 140).trim() + '..."',
                        productId: productId,
                        jobId: jobId
                    });
                    return;
                }
            }
        }

        if (!foundImage) {
            chrome.runtime.sendMessage({
                action: 'GEMINI_FAILED',
                error: 'Tiempo de espera agotado: Gemini Pro no completó la generación en 120 segundos.',
                productId: productId,
                jobId: jobId
            });
            return;
        }

        sendStepProgress('¡Imagen generada detectada! Extrayendo datos en alta resolución...');
        console.log('[Cauchos Alfa] ¡Imagen generada detectada! Dimensiones:', foundImage.naturalWidth, 'x', foundImage.naturalHeight);

        // Pausa de 2 segundos para asegurar renderizado en resolución nativa
        await new Promise(r => setTimeout(r, 2000));

        // 7. Convertir la imagen a base64
        try {
            const base64Data = await convertImgToBase64(foundImage);
            chrome.runtime.sendMessage({
                action: 'GEMINI_FINISHED',
                imageBase64: base64Data,
                productId: productId,
                jobId: jobId
            });
        } catch (conversionErr) {
            console.error('[Cauchos Alfa] Error al convertir imagen a base64:', conversionErr);
            chrome.runtime.sendMessage({
                action: 'GEMINI_FAILED',
                error: 'No se pudo extraer la imagen generada de Gemini: ' + conversionErr.message,
                productId: productId,
                jobId: jobId
            });
        }
    } finally {
        guardian.stop();
        dismissGeminiLightbox();
    }
}

function findGeneratedImageInResponse(responseElement) {
    if (!responseElement) return null;

    // Selectores prioritarios: contenedores y clases oficiales de imágenes generadas por IA en Gemini
    const primarySelectors = [
        'generated-image img',
        'single-image img',
        'div.generated-images img',
        'img.image.loaded',
        'img.image.animate',
        'img[alt*="generada por IA"]',
        'img[alt*="AI generated"]',
        'img[alt*="generada"]'
    ];

    for (const sel of primarySelectors) {
        const imgs = responseElement.querySelectorAll(sel);
        for (const img of imgs) {
            // Excluir estrictamente cualquier imagen subida por el usuario
            if (img.classList.contains('preview-image') ||
                img.getAttribute('data-test-id') === 'uploaded-img' ||
                img.closest('rich-textarea, input-area, uploader, user-query, user-query-file-preview, [data-test-id*="user"], .user-message')) {
                continue;
            }
            const src = img.currentSrc || img.src || '';
            if (src && img.complete && (img.naturalWidth >= 256 || img.width >= 256)) {
                return img;
            }
        }
    }

    // Selector secundario: a través del botón de descarga oficial de imágenes de Gemini
    const downloadBtn = responseElement.querySelector('[data-test-id="download-generated-image-button"], button[aria-label*="Descargar"]');
    if (downloadBtn) {
        const container = downloadBtn.closest('single-image, generated-image, response-element, .attachment-container') || downloadBtn.parentElement?.parentElement;
        if (container) {
            const img = container.querySelector('img');
            if (img && !img.classList.contains('preview-image') && (img.naturalWidth >= 256 || img.width >= 256) && img.complete) {
                return img;
            }
        }
    }

    // Selector de respaldo: cualquier imagen grande (>= 256px) dentro de model-response que no sea avatar ni subida del usuario
    const allResponseImgs = responseElement.querySelectorAll('img');
    for (const img of allResponseImgs) {
        if (img.classList.contains('preview-image') ||
            img.getAttribute('data-test-id') === 'uploaded-img' ||
            img.closest('rich-textarea, input-area, uploader, user-query, user-query-file-preview, [data-test-id*="user"], .user-message')) {
            continue;
        }
        const src = img.currentSrc || img.src || '';
        if (src && img.complete && (img.naturalWidth >= 256 || img.width >= 256) && (img.naturalHeight >= 256 || img.height >= 256)) {
            return img;
        }
    }

    return null;
}

async function convertImgToBase64(imgElement) {
    const src = imgElement.currentSrc || imgElement.src || '';
    if (src.startsWith('data:image/')) {
        return src;
    }

    // Asegurar que el elemento esté cargado
    if (!imgElement.complete || (imgElement.naturalWidth === 0 && imgElement.width === 0)) {
        await new Promise((resolve) => {
            imgElement.onload = resolve;
            setTimeout(resolve, 1500);
        });
    }

    const width = imgElement.naturalWidth || imgElement.width || 1024;
    const height = imgElement.naturalHeight || imgElement.height || 1024;

    // Método 1: Canvas directo en el documento
    // Funciona al 100% para imágenes blob: nativas de Gemini sin restricciones CSP ni CORS
    try {
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(imgElement, 0, 0, width, height);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
        if (dataUrl && dataUrl.length > 5000) {
            console.log('[Cauchos Alfa] Conversión exitosa vía Canvas directo (longitud:', dataUrl.length, ')');
            return dataUrl;
        }
    } catch (canvasErr) {
        console.warn('[Cauchos Alfa] Canvas directo falló (posible contaminación CORS):', canvasErr);
    }

    // Método 2: createImageBitmap + Canvas (acceso acelerado por hardware a nivel de GPU)
    try {
        const bitmap = await createImageBitmap(imgElement);
        const canvas = document.createElement('canvas');
        canvas.width = bitmap.width;
        canvas.height = bitmap.height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(bitmap, 0, 0);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
        bitmap.close();
        if (dataUrl && dataUrl.length > 5000) {
            console.log('[Cauchos Alfa] Conversión exitosa vía createImageBitmap');
            return dataUrl;
        }
    } catch (bitmapErr) {
        console.warn('[Cauchos Alfa] createImageBitmap falló:', bitmapErr);
    }

    // Método 2.5: Fetch directo en el contexto de página (para blob: o URLs con sesión de página)
    if (src.startsWith('blob:') || src.startsWith('http://') || src.startsWith('https://')) {
        try {
            const pageRes = await fetch(src, { credentials: 'include' });
            if (pageRes.ok) {
                const blob = await pageRes.blob();
                const dataUrl = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(blob);
                });
                if (dataUrl && dataUrl.length > 5000) {
                    console.log('[Cauchos Alfa] Conversión exitosa vía fetch directo en página');
                    return dataUrl;
                }
            }
        } catch (fetchErr) {
            console.warn('[Cauchos Alfa] Fetch directo en página falló:', fetchErr);
        }
    }

    // Método 3: Si la imagen es una URL remota (http/https como googleusercontent.com),
    // delegar la descarga al Background Service Worker (que cuenta con host_permissions y no está sujeto a CSP ni CORS de la página)
    if (src.startsWith('http://') || src.startsWith('https://')) {
        console.log('[Cauchos Alfa] Delegando descarga de imagen remota a Background Service Worker...');
        try {
            const bgResponse = await new Promise((resolve, reject) => {
                chrome.runtime.sendMessage({
                    action: 'FETCH_IMAGE_URL',
                    url: src
                }, (response) => {
                    if (chrome.runtime.lastError) {
                        reject(new Error(chrome.runtime.lastError.message));
                    } else if (response && response.success) {
                        resolve(response.base64);
                    } else {
                        reject(new Error(response?.error || 'Fallo desconocido en background'));
                    }
                });
            });
            if (bgResponse && bgResponse.length > 5000) {
                console.log('[Cauchos Alfa] Conversión exitosa vía Background Service Worker');
                return bgResponse;
            }
        } catch (bgErr) {
            console.warn('[Cauchos Alfa] Descarga en background falló:', bgErr);
        }
    }

    throw new Error('No se pudo convertir la imagen a base64 con los métodos disponibles.');
}


