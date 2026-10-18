// content_cauchos.js - Inyectado en las páginas de Cauchos Alfa

function markInstalled() {
    if (!document.getElementById('__cauchos_gemini_bridge_installed__')) {
        const marker = document.createElement('div');
        marker.id = '__cauchos_gemini_bridge_installed__';
        marker.setAttribute('data-version', '1.0.0');
        marker.style.display = 'none';
        document.documentElement.appendChild(marker);
    }
}

markInstalled();
document.addEventListener('DOMContentLoaded', markInstalled);

// Escuchar peticiones desde la página de Cauchos Alfa (window.postMessage)
window.addEventListener('message', (event) => {
    if (event.source !== window || !event.data || typeof event.data !== 'object') {
        return;
    }

    if (event.data.type === 'CAUCHOS_START_GEMINI_AUTOMATION') {
        const { prompt, productId, jobId, imageBase64, isLast } = event.data;
        console.log('[Cauchos Alfa Bridge] Iniciando automatización con Gemini Web...', { productId, jobId, isLast, hasImage: !!imageBase64 });

        chrome.runtime.sendMessage({
            action: 'START_GEMINI',
            prompt: prompt,
            productId: productId,
            jobId: jobId || 'main',
            imageBase64: imageBase64,
            isLast: !!isLast
        }, (response) => {
            if (chrome.runtime.lastError) {
                console.error('[Cauchos Alfa Bridge] Error comunicando con background:', chrome.runtime.lastError);
                window.postMessage({
                    type: 'CAUCHOS_GEMINI_ERROR',
                    error: chrome.runtime.lastError.message,
                    productId: productId,
                    jobId: jobId || 'main'
                }, '*');
            }
        });
    }
});

// Escuchar respuestas provenientes del background worker (desde la pestaña de Gemini)
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message.action === 'GEMINI_STATUS_UPDATE') {
        window.postMessage({
            type: 'CAUCHOS_GEMINI_STATUS',
            status: message.status,
            productId: message.productId,
            jobId: message.jobId
        }, '*');
        sendResponse({ received: true });
    } else if (message.action === 'GEMINI_AUTOMATION_COMPLETED') {
        console.log('[Cauchos Alfa Bridge] ¡Imagen recibida desde Gemini Web!', { productId: message.productId, jobId: message.jobId });
        window.postMessage({
            type: 'CAUCHOS_GEMINI_SUCCESS',
            imageBase64: message.imageBase64,
            productId: message.productId,
            jobId: message.jobId
        }, '*');
        sendResponse({ received: true });
    } else if (message.action === 'GEMINI_AUTOMATION_ERROR') {
        console.error('[Cauchos Alfa Bridge] Error reportado por Gemini Web:', message.error);
        window.postMessage({
            type: 'CAUCHOS_GEMINI_ERROR',
            error: message.error,
            productId: message.productId,
            jobId: message.jobId
        }, '*');
        sendResponse({ received: true });
    }
    return true;
});
