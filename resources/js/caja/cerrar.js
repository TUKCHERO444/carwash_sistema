// Feature: arqueo-cierre-caja
// Module: caja/cerrar.js
// Vista previa de la diferencia del arqueo (contado − esperado).

// ── Pure calculation function (exported for testing) ──

/**
 * Calculates the cash count difference (counted − expected).
 * @param {number|string} contado
 * @param {number|string} esperado
 * @returns {number} Difference rounded to 2 decimals.
 */
export function calcularDiferencia(contado, esperado) {
    const c = parseFloat(contado) || 0;
    const e = parseFloat(esperado) || 0;
    return +(c - e).toFixed(2);
}

/**
 * Label for the difference: sobrante, faltante o coincide.
 * @param {number} diferencia
 * @returns {string}
 */
export function etiquetaDiferencia(diferencia) {
    if (diferencia > 0) return 'Sobrante';
    if (diferencia < 0) return 'Faltante';
    return 'Coincide';
}

// ── Module initialisation ──

// Guard para entornos sin DOM (tests de Node importan este módulo para
// ejercitar las funciones puras): en el navegador siempre está definido.
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        const input = document.getElementById('efectivo_contado');
        const preview = document.getElementById('diferencia-preview');
        const etiqueta = document.getElementById('diferencia-etiqueta');
        if (!input || !preview) return;

        const esperado = parseFloat(input.dataset.esperado) || 0;

        function actualizar() {
            const diferencia = calcularDiferencia(input.value, esperado);
            preview.textContent = `S/ ${diferencia.toFixed(2)}`;
            if (etiqueta) etiqueta.textContent = etiquetaDiferencia(diferencia);
            preview.classList.remove('text-green-600', 'text-red-600', 'dark:text-green-400', 'dark:text-red-400');
            if (diferencia > 0) preview.classList.add('text-green-600', 'dark:text-green-400');
            if (diferencia < 0) preview.classList.add('text-red-600', 'dark:text-red-400');
        }

        input.addEventListener('input', actualizar);
        actualizar();
    });
}
