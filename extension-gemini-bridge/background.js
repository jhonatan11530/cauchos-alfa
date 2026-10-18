// background.js - Service Worker para coordinar Cauchos Alfa y Gemini Web

let activeSessions = {};

function persistActiveSessions() {
    try {
        const lightweightSessions = {};
        for (const [tabId, sess] of Object.entries(activeSessions)) {
            if (sess) {
                lightweightSessions[tabId] = {
                    originTabId: sess.originTabId,
                    productId: sess.productId,
                    jobId: sess.jobId,
                    isLast: sess.isLast,
                    status: sess.status
                };
            }
        }
        chrome.storage.local.set({ activeSessions: lightweightSessions }).catch(() => {});
    } catch (e) {}
}

async function resolveSessionForGeminiTab(geminiTabId, message) {
    if (geminiTabId && activeSessions[geminiTabId]) {
        return activeSessions[geminiTabId];
    }

    try {
        const stored = await chrome.storage.local.get(['activeSessions']);
        if (stored && stored.activeSessions && geminiTabId && stored.activeSessions[geminiTabId]) {
            activeSessions[geminiTabId] = stored.activeSessions[geminiTabId];
            return activeSessions[geminiTabId];
        }
    } catch (e) {}

    // Fallback si el Service Worker se reinició y no hay registro en storage: buscar la pestaña de Cauchos Alfa
    try {
        const tabs = await chrome.tabs.query({});
        const cauchosTab = tabs.find(t => t.url && (
            t.url.includes('/productos/') ||
            t.url.includes('cauchos-alfa') ||
            t.url.includes('127.0.0.1') ||
            t.url.includes('localhost')
        ) && !t.url.includes('gemini.google.com'));

        if (cauchosTab) {
            return {
                originTabId: cauchosTab.id,
                productId: message.productId,
                jobId: message.jobId || 'main',
                isLast: true
            };
        }
    } catch (e) {}

    return null;
}

function sendPromptToGeminiTab(geminiTabId, payload, originTabId) {
    chrome.tabs.sendMessage(geminiTabId, payload, (res) => {
        if (chrome.runtime.lastError) {
            console.warn('[Background] content_gemini.js no responde, inyectando script dinámicamente...', chrome.runtime.lastError);
            chrome.scripting.executeScript({
                target: { tabId: geminiTabId },
                files: ['content_gemini.js']
            }).then(() => {
                setTimeout(() => {
                    chrome.tabs.sendMessage(geminiTabId, payload, (res2) => {
                        if (chrome.runtime.lastError) {
                            console.error('[Background] Error crítico al contactar Gemini:', chrome.runtime.lastError);
                        }
                    });
                }, 800);
            }).catch(err => {
                console.error('[Background] Error inyectando script:', err);
            });
        }
    });
}

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message.action === 'START_GEMINI') {
        const originTabId = sender.tab ? sender.tab.id : null;
        const { prompt, productId, jobId, imageBase64, isLast } = message;

        console.log('[Background] Solicitud para producto:', productId, 'jobId:', jobId, { isLast, hasImage: !!imageBase64 });

        // 1. Buscar si ya existe alguna pestaña de Gemini abierta (para reusar el mismo chat)
        chrome.tabs.query({ url: '*://gemini.google.com/*' }, (existingTabs) => {
            if (existingTabs && existingTabs.length > 0) {
                // Reutilizar la pestaña existente en lugar de abrir una nueva
                const geminiTab = existingTabs.find(t => t.active) || existingTabs[0];
                const geminiTabId = geminiTab.id;

                console.log('[Background] Reutilizando chat existente en pestaña ID:', geminiTabId, geminiTab.url);

                activeSessions[geminiTabId] = {
                    originTabId: originTabId,
                    prompt: prompt,
                    productId: productId,
                    jobId: jobId || 'main',
                    isLast: !!isLast,
                    imageBase64: imageBase64,
                    status: 'reusing'
                };
                persistActiveSessions();

                // Enfocar la pestaña de Gemini existente
                chrome.tabs.update(geminiTabId, { active: true });
                if (geminiTab.windowId) {
                    chrome.windows.update(geminiTab.windowId, { focused: true }).catch(() => {});
                }

                if (originTabId) {
                    chrome.tabs.sendMessage(originTabId, {
                        action: 'GEMINI_STATUS_UPDATE',
                        status: 'Reutilizando chat existente de Gemini Pro...',
                        productId: productId,
                        jobId: jobId || 'main'
                    }).catch(() => {});
                }

                // Enviar el prompt directamente en el mismo chat (espera de 1s para asegurar foco del editor)
                setTimeout(() => {
                    sendPromptToGeminiTab(geminiTabId, {
                        action: 'EXECUTE_GEMINI_PROMPT',
                        prompt: prompt,
                        productId: productId,
                        jobId: jobId || 'main',
                        imageBase64: imageBase64
                    }, originTabId);
                }, 1000);

            } else {
                // No hay ninguna pestaña abierta con Gemini: abrir una nueva
                console.log('[Background] No hay pestañas de Gemini abiertas. Abriendo una...');

                chrome.tabs.create({
                    url: 'https://gemini.google.com/app',
                    active: true
                }, (geminiTab) => {
                    const geminiTabId = geminiTab.id;
                    activeSessions[geminiTabId] = {
                        originTabId: originTabId,
                        prompt: prompt,
                        productId: productId,
                        jobId: jobId || 'main',
                        isLast: !!isLast,
                        imageBase64: imageBase64,
                        status: 'loading'
                    };
                    persistActiveSessions();

                    if (originTabId) {
                        chrome.tabs.sendMessage(originTabId, {
                            action: 'GEMINI_STATUS_UPDATE',
                            status: 'Pestaña de Gemini Pro abierta. Esperando carga...',
                            productId: productId,
                            jobId: jobId || 'main'
                        }).catch(() => {});
                    }

                    const onUpdatedListener = (tabId, changeInfo, tab) => {
                        if (tabId === geminiTabId && changeInfo.status === 'complete') {
                            chrome.tabs.onUpdated.removeListener(onUpdatedListener);
                            console.log('[Background] Pestaña de Gemini cargada. Preparando inyección...');

                            if (originTabId) {
                                chrome.tabs.sendMessage(originTabId, {
                                    action: 'GEMINI_STATUS_UPDATE',
                                    status: 'Conectando con el editor de Gemini Pro...',
                                    productId: productId,
                                    jobId: jobId || 'main'
                                }).catch(() => {});
                            }

                            setTimeout(() => {
                                sendPromptToGeminiTab(geminiTabId, {
                                    action: 'EXECUTE_GEMINI_PROMPT',
                                    prompt: prompt,
                                    productId: productId,
                                    jobId: jobId || 'main',
                                    imageBase64: imageBase64
                                }, originTabId);
                            }, 3500);
                        }
                    };

                    chrome.tabs.onUpdated.addListener(onUpdatedListener);
                });
            }
        });

        sendResponse({ started: true });
        return true;
    }

    if (message.action === 'GEMINI_STEP_PROGRESS') {
        const geminiTabId = sender.tab ? sender.tab.id : null;
        resolveSessionForGeminiTab(geminiTabId, message).then((session) => {
            if (session && session.originTabId) {
                chrome.tabs.sendMessage(session.originTabId, {
                    action: 'GEMINI_STATUS_UPDATE',
                    status: message.status,
                    productId: session.productId || message.productId,
                    jobId: session.jobId || message.jobId
                }).catch(() => {});
            }
        });
        sendResponse({ ack: true });
        return true;
    }

    if (message.action === 'GEMINI_FINISHED') {
        const geminiTabId = sender.tab ? sender.tab.id : null;
        resolveSessionForGeminiTab(geminiTabId, message).then((session) => {
            if (session && session.originTabId) {
                console.log('[Background] Reenviando imagen generada a Cauchos Alfa... (jobId:', session.jobId, ')');
                chrome.tabs.sendMessage(session.originTabId, {
                    action: 'GEMINI_AUTOMATION_COMPLETED',
                    imageBase64: message.imageBase64,
                    productId: session.productId || message.productId,
                    jobId: session.jobId || message.jobId
                });

                // Reenfocar la pestaña de Cauchos Alfa si es la última imagen del lote
                if (session.isLast) {
                    chrome.tabs.update(session.originTabId, { active: true });
                    chrome.tabs.get(session.originTabId, (origTab) => {
                        if (origTab && origTab.windowId) {
                            chrome.windows.update(origTab.windowId, { focused: true }).catch(() => {});
                        }
                    });
                }

                // Mantener la pestaña abierta en el mismo chat para futuras generaciones
                delete activeSessions[geminiTabId];
                persistActiveSessions();
            }
        });

        sendResponse({ success: true });
        return true;
    }

    if (message.action === 'ATTACH_IMAGE_MAIN_WORLD') {
        const geminiTabId = sender.tab ? sender.tab.id : null;
        if (!geminiTabId || !message.imageBase64) {
            sendResponse({ success: false, error: 'Tab ID o imageBase64 faltante' });
            return true;
        }

        chrome.scripting.executeScript({
            target: { tabId: geminiTabId },
            world: 'MAIN',
            func: async (dataUrl, filename) => {
                const hasPreview = () => {
                    const sel = [
                        'uploader img', '.uploader img', 'user-query-file-preview', '.file-preview',
                        '.attachment-container img', 'rich-textarea img', 'input-area img', 'img.preview-image',
                        '[data-test-id*="uploaded"]', '[data-test-id*="file-preview"]',
                        'uploader button[aria-label*="Eliminar" i]', 'uploader button[aria-label*="Remove" i]',
                        'uploader button[aria-label*="Quitar" i]', 'rich-textarea button[aria-label*="Eliminar" i]',
                        'rich-textarea button[aria-label*="Remove" i]', '.attachment-close-button',
                        '[data-test-id*="remove-file"]', 'button[aria-label*="Quitar archivo" i]', 'button[aria-label*="Remove file" i]'
                    ].join(',');
                    return document.querySelector(sel) !== null;
                };

                if (hasPreview()) {
                    return { attached: true, method: 'already_present' };
                }

                const arr = dataUrl.split(',');
                const mimeMatch = arr[0].match(/:(.*?);/);
                const mime = mimeMatch ? mimeMatch[1] : 'image/jpeg';
                const bstr = atob(arr[1]);
                let n = bstr.length;
                const u8arr = new Uint8Array(n);
                while (n--) {
                    u8arr[n] = bstr.charCodeAt(n);
                }
                const file = new File([u8arr], filename || 'repuesto_cauchos_alfa.jpg', { type: mime, lastModified: Date.now() });

                const editor = document.querySelector('rich-textarea div[contenteditable="true"]')
                    || document.querySelector('div[contenteditable="true"][role="textbox"]')
                    || document.querySelector('div[contenteditable="true"]')
                    || document.querySelector('textarea');

                // 1. Paste en MAIN world
                if (editor) {
                    try {
                        editor.focus();
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        const pasteEvt = new ClipboardEvent('paste', {
                            bubbles: true,
                            cancelable: true,
                            composed: true,
                            clipboardData: dt
                        });
                        editor.dispatchEvent(pasteEvt);
                    } catch (e) {}
                }

                await new Promise(r => setTimeout(r, 450));
                if (hasPreview()) {
                    return { attached: true, method: 'main_paste' };
                }

                // 2. Secuencia Drag & Drop completa en MAIN world
                try {
                    const dropTarget = (editor && editor.closest('rich-textarea')) || editor || document.body;
                    const dtDrop = new DataTransfer();
                    dtDrop.items.add(file);
                    for (const evtName of ['dragenter', 'dragover', 'drop']) {
                        dropTarget.dispatchEvent(new DragEvent(evtName, {
                            bubbles: true,
                            cancelable: true,
                            composed: true,
                            dataTransfer: dtDrop
                        }));
                    }
                } catch (e) {}

                await new Promise(r => setTimeout(r, 450));
                if (hasPreview()) {
                    return { attached: true, method: 'main_drop' };
                }

                // 3. Intercepción de input[type="file"] en MAIN world (incluyendo inputs creados en memoria por Angular)
                let interceptedInput = false;
                const assignToInput = (inputEl) => {
                    if (!inputEl || interceptedInput) return;
                    try {
                        const dtInput = new DataTransfer();
                        dtInput.items.add(file);
                        inputEl.files = dtInput.files;
                        inputEl.dispatchEvent(new Event('change', { bubbles: true, composed: true }));
                        inputEl.dispatchEvent(new Event('input', { bubbles: true, composed: true }));
                        interceptedInput = true;
                    } catch (e) {}
                };

                const existingInputs = Array.from(document.querySelectorAll('input[type="file"]'));
                if (existingInputs.length > 0) {
                    assignToInput(existingInputs[0]);
                    await new Promise(r => setTimeout(r, 450));
                    if (hasPreview()) {
                        return { attached: true, method: 'existing_file_input' };
                    }
                }

                const origInputClick = HTMLInputElement.prototype.click;
                HTMLInputElement.prototype.click = function () {
                    if (this && this.type === 'file') {
                        assignToInput(this);
                        return;
                    }
                    return origInputClick.apply(this, arguments);
                };

                try {
                    const uploadSelectors = [
                        'button[aria-label*="Añadir archivos" i]',
                        'button[aria-label*="Add files" i]',
                        'button[aria-label*="Agregar archivos" i]',
                        'button[aria-label*="Subir archivos" i]',
                        'button[aria-label*="Upload files" i]',
                        'button[aria-label*="Subir archivo" i]',
                        'button[aria-label*="Upload file" i]',
                        'button[aria-label*="Subir imagen" i]',
                        'button[aria-label*="Upload image" i]',
                        'button[aria-label="Abrir menú para subir archivos"]',
                        'button[aria-label="Open upload file menu"]',
                        '[data-test-id="upload-button"]',
                        'button.upload-card-button'
                    ];
                    const candidates = Array.from(document.querySelectorAll(uploadSelectors.join(',')));
                    const realBtn = candidates.find(btn => {
                        const isInsidePreview = btn.closest('uploader, .uploader, user-query-file-preview, [data-test-id*="uploaded"], .file-preview, .attachment-container');
                        const hasThumbnail = btn.querySelector('img') !== null;
                        const ariaLabel = (btn.getAttribute('aria-label') || '').toLowerCase();
                        const isFileChip = ariaLabel.includes('.jpg') || ariaLabel.includes('.jpeg') || ariaLabel.includes('.png') || ariaLabel.includes('.webp') || ariaLabel.includes('repuesto');
                        return !isInsidePreview && !hasThumbnail && !isFileChip;
                    });

                    if (realBtn) {
                        realBtn.click();
                        await new Promise(r => setTimeout(r, 350));

                        if (!interceptedInput && !hasPreview()) {
                            const menuSelectors = [
                                '[data-test-id*="local-images-files-uploader-button"]',
                                '[data-test-id*="upload-file"]',
                                '[data-test-id*="upload-image"]',
                                'button[aria-label*="Subir archivos" i]',
                                'button[aria-label*="Upload files" i]',
                                'button[aria-label*="Subir archivo" i]',
                                'button[aria-label*="Upload file" i]',
                                '[role="menuitem"]'
                            ];
                            const menuItems = Array.from(document.querySelectorAll(menuSelectors.join(',')));
                            const uploadMenuItem = menuItems.find(item => {
                                const txt = ((item.innerText || '') + ' ' + (item.getAttribute('aria-label') || '') + ' ' + (item.getAttribute('data-test-id') || '')).toLowerCase();
                                return txt.includes('subir') || txt.includes('upload') || txt.includes('local') || txt.includes('archivo') || txt.includes('file') || txt.includes('imagen');
                            });
                            if (uploadMenuItem) {
                                uploadMenuItem.click();
                                await new Promise(r => setTimeout(r, 350));
                            }
                        }

                        if (!interceptedInput && !hasPreview()) {
                            const newInputs = Array.from(document.querySelectorAll('input[type="file"]'));
                            if (newInputs.length > 0) {
                                assignToInput(newInputs[0]);
                            }
                        }
                    }
                } finally {
                    HTMLInputElement.prototype.click = origInputClick;
                }

                await new Promise(r => setTimeout(r, 450));
                return { attached: hasPreview() || interceptedInput, method: 'main_file_input_hook' };
            },
            args: [message.imageBase64, message.filename || 'repuesto_cauchos_alfa.jpg']
        }).then((results) => {
            const res = results && results[0] ? results[0].result : null;
            sendResponse({ success: true, result: res });
        }).catch((err) => {
            console.error('[Background] Error en ATTACH_IMAGE_MAIN_WORLD:', err);
            sendResponse({ success: false, error: err.message });
        });

        return true;
    }

    if (message.action === 'FETCH_IMAGE_URL') {
        fetchUrlAsBase64(message.url)
            .then(base64 => sendResponse({ success: true, base64 }))
            .catch(err => {
                console.error('[Background] Error al descargar imagen con privilegios:', err);
                sendResponse({ success: false, error: err.message });
            });
        return true;
    }

    if (message.action === 'GEMINI_FAILED') {
        const geminiTabId = sender.tab ? sender.tab.id : null;
        resolveSessionForGeminiTab(geminiTabId, message).then((session) => {
            if (session && session.originTabId) {
                console.error('[Background] Notificando error a Cauchos Alfa:', message.error, '(jobId:', session.jobId, ')');
                chrome.tabs.sendMessage(session.originTabId, {
                    action: 'GEMINI_AUTOMATION_ERROR',
                    error: message.error,
                    productId: session.productId || message.productId,
                    jobId: session.jobId || message.jobId
                });

                delete activeSessions[geminiTabId];
                persistActiveSessions();
            }
        });

        sendResponse({ ack: true });
        return true;
    }
});

async function fetchUrlAsBase64(url) {
    const res = await fetch(url, { credentials: 'include' });
    if (!res.ok) throw new Error(`HTTP ${res.status} al descargar imagen`);
    const blob = await res.blob();
    const buffer = await blob.arrayBuffer();
    const bytes = new Uint8Array(buffer);
    let binary = '';
    const chunkSize = 8192;
    for (let i = 0; i < bytes.length; i += chunkSize) {
        binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunkSize));
    }
    const mime = blob.type || 'image/jpeg';
    return `data:${mime};base64,${btoa(binary)}`;
}


