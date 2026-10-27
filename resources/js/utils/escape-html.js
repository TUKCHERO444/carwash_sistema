/**
 * Utilidades de escape para contenido no confiable insertado en HTML.
 *
 * Cualquier dato proveniente de la base de datos (nombres de productos,
 * proveedores, servicios, etc.) debe pasar por `escapeHtml` antes de
 * interpolarse en un template string que se asigna a `innerHTML` o a un
 * atributo HTML.
 *
 * @param {unknown} value
 * @returns {string}
 */
export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/`/g, '&#096;');
}
