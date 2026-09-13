/**
 * Módulo: contenido-web/edit.js
 * Responsabilidad: mejoras del panel de contenidos de la web.
 * - Curaduría de marcas: subir/bajar filas y sincronizar el input
 *   `marcas_orden[]` con la posición visual de las marcas seleccionadas.
 * Funciona igual sin JS: el formulario se envía con el orden natural de las
 * filas; este módulo solo reordena filas y renumera el orden en vivo.
 */

/**
 * Renumera el input `marcas_orden` de una lista de curaduría según la
 * posición de las marcas seleccionadas (las no seleccionadas quedan vacías).
 * @param {Element} lista - Elemento raíz con `data-curaduria-list`.
 */
export function sincronizarOrden(lista) {
    if (!lista) return;

    let posicion = 0;

    lista.querySelectorAll('[data-curaduria-row]').forEach((fila) => {
        const checkbox = fila.querySelector('input[type="checkbox"]');
        const orden = fila.querySelector('input[name^="marcas_orden"]');

        if (!checkbox || !orden) return;

        if (checkbox.checked) {
            orden.value = String(posicion++);
        } else {
            orden.value = '';
        }
    });
}

/**
 * Mueve una fila de la curaduría hacia arriba o hacia abajo y renumera.
 * @param {Element} fila - Fila `[data-curaduria-row]` a mover.
 * @param {string} direccion - 'arriba' | 'abajo'.
 */
export function moverFila(fila, direccion) {
    if (!fila) return;

    const lista = fila.parentElement;
    if (!lista) return;

    if (direccion === 'arriba') {
        const previa = fila.previousElementSibling;
        if (!previa) return;
        lista.insertBefore(fila, previa);
    } else {
        const siguiente = fila.nextElementSibling;
        if (!siguiente) return;
        lista.insertBefore(siguiente, fila);
    }

    sincronizarOrden(lista);
}

/**
 * Inicializa los listeners de la curaduría por delegación en el document.
 */
export function initCuraduria() {
    document.addEventListener('click', (event) => {
        const fila = event.target.closest('[data-curaduria-row]');
        if (!fila) return;

        if (event.target.closest('[data-curaduria-up]')) {
            moverFila(fila, 'arriba');
        } else if (event.target.closest('[data-curaduria-down]')) {
            moverFila(fila, 'abajo');
        }
    });

    document.addEventListener('change', (event) => {
        const checkbox = event.target.closest('[data-curaduria-row] input[type="checkbox"]');
        if (!checkbox) return;

        sincronizarOrden(checkbox.closest('[data-curaduria-list]'));
    });
}

initCuraduria();