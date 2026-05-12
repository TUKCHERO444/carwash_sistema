/**
 * Módulo: productos/shared.js
 * Responsabilidad: Lógica compartida para el módulo de productos (ej. preview de fotos).
 */

/**
 * Inicializa el preview de imagen para un input de tipo file.
 * 
 * @param {string} inputId   - ID del input[type="file"]
 * @param {string} previewId - ID del elemento <img> donde se muestra la preview
 * @param {string} bloqueId  - ID del contenedor que se oculta/muestra
 */
export function initFotoPreview(inputId, previewId, bloqueId) {
    const input = document.getElementById(inputId);
    if (!input) return;

    input.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (event) {
            const preview = document.getElementById(previewId);
            const bloque = document.getElementById(bloqueId);
            
            if (preview) preview.src = event.target.result;
            if (bloque) bloque.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });
}
