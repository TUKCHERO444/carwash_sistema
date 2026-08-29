/**
 * Módulo: utils/input-filters.js
 * Responsabilidad: Filtrado en vivo de inputs según data-filter:
 *   - "letters"      → solo letras (incluye acentos, ü y ñ) y espacios simples
 *   - "alphanumeric" → letras, números y espacios simples (sin símbolos)
 *   - "digits"       → solo dígitos numéricos (0-9), sin letras ni símbolos
 *
 * Opcionalmente respeta data-length="N" para recortar el valor a N caracteres,
 * además del atributo nativo maxlength.
 */

const FILTER_PATTERNS = {
    letters: /[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/g,
    alphanumeric: /[^A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ ]/g,
    digits: /\D/g,
};

/**
 * Elimina los caracteres no permitidos según el filtro declarado.
 * En campos de texto colapsa espacios múltiples y elimina espacios al inicio.
 * @param {HTMLInputElement} input
 * @param {string} raw - Valor a sanitizar
 * @returns {string} Valor limpio
 */
function sanitizeValue(input, raw) {
    const filter = input.dataset.filter;
    const pattern = FILTER_PATTERNS[filter];
    if (!pattern) return raw;

    let clean = String(raw).replace(pattern, '');

    if (filter === 'letters' || filter === 'alphanumeric') {
        clean = clean.replace(/ {2,}/g, ' ').replace(/^ +/, '');
    }

    return clean;
}

/**
 * Recorta el valor al límite declarado en data-length o maxlength.
 * @param {HTMLInputElement} input
 * @param {string} value
 * @returns {string}
 */
function enforceMaxLength(input, value) {
    const max = parseInt(input.dataset.length || input.getAttribute('maxlength') || '', 10);
    if (!Number.isNaN(max) && max > 0 && value.length > max) {
        return value.slice(0, max);
    }
    return value;
}

/**
 * Inicializa el filtrado en vivo sobre todos los elementos con [data-filter]
 * dentro del contenedor indicado (por defecto todo el documento).
 * Normaliza también los valores precargados (p. ej., datos legados).
 *
 * @param {ParentNode} [root=document]
 */
export function initInputFilters(root = document) {
    if (!root) return;

    const inputs = root instanceof Element
        ? (root.matches('[data-filter]') ? [root] : Array.from(root.querySelectorAll('[data-filter]')))
        : Array.from(root.querySelectorAll('[data-filter]'));

    inputs.forEach((input) => {
        // Evitar doble binding (p. ej., init global + init de módulo)
        if (input.dataset.filterBound === '1') return;
        input.dataset.filterBound = '1';

        // Normaliza caracteres inválidos en valores precargados.
        // NO recorta por longitud al cargar: un valor legado mayor al límite
        // debe corregirse manualmente (explícito), no truncarse en silencio.
        const initial = sanitizeValue(input, input.value);
        if (initial !== input.value) {
            input.value = initial;
        }

        input.addEventListener('input', () => {
            const cleaned = enforceMaxLength(input, sanitizeValue(input, input.value));
            if (cleaned !== input.value) {
                input.value = cleaned;
            }
        });

        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const text = e.clipboardData ? e.clipboardData.getData('text/plain') : '';
            if (!text) return;

            const start = input.selectionStart ?? input.value.length;
            const end = input.selectionEnd ?? start;
            const merged = input.value.slice(0, start) + text + input.value.slice(end);

            input.value = enforceMaxLength(input, sanitizeValue(input, merged));
        });
    });
}
