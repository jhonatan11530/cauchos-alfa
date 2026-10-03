import './bootstrap';
import { BatchImageEnhancer } from './batch-enhancer';

document.addEventListener('DOMContentLoaded', () => {
    const btnStandardize = document.getElementById('btn-standardize-images');

    if (btnStandardize) {
        btnStandardize.addEventListener('click', async () => {
            // Deshabilitar botón mientras procesa
            btnStandardize.disabled = true;
            const originalText = btnStandardize.innerHTML;
            btnStandardize.innerHTML = `
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Procesando...
            `;

            const enhancer = new BatchImageEnhancer(800, 800);

            // Selector de las imágenes a estandarizar (las que tengan esta clase)
            await enhancer.processAllImages('.target-image');

            // Restaurar botón
            btnStandardize.innerHTML = originalText;
            btnStandardize.disabled = false;
        });
    }
});
