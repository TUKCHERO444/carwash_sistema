/**
 * Núcleo puro del menú "Productos" del sitio público
 * (nav-productos.js).
 *
 * Funciones puras (sin DOM) para poder probarlas con property tests.
 * Ver tests/js/publica/nav-productos.property.test.js.
 */

/**
 * Umbral de "mantener presionado" (ms) para abrir el submenú sin navegar.
 * Por debajo, una pulsación corta navega a la vista de productos.
 */
export const LONG_PRESS_MS = 450;

/**
 * Decide la acción al soltar la pulsación sobre el enlace "Productos".
 *
 * - 'menu'    : la pulsación alcanzó (o superó) el umbral => abrir submenú.
 * - 'navigate': pulsación corta => navegar a la vista de productos.
 *
 * @param {number} holdMs      Tiempo presionado en milisegundos.
 * @param {number} thresholdMs Umbral del press-and-hold (default LONG_PRESS_MS).
 * @returns {'menu' | 'navigate'}
 */
export function getReleaseAction(holdMs, thresholdMs = LONG_PRESS_MS) {
    return holdMs >= thresholdMs ? 'menu' : 'navigate';
}