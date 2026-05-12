/**
 * stock-modal.js
 * Módulo JavaScript para el modal de actualización de stock de productos.
 * Requisitos: 1.2, 1.3, 1.4, 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 4.1, 4.2, 4.3, 4.4
 */

/**
 * Cierra el modal de stock ocultándolo con la clase `hidden`.
 */
function closeStockModal() {
    const modal = document.getElementById('stock-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

/**
 * Muestra un mensaje de error dentro del modal.
 * @param {string} message
 */
function showModalError(message) {
    const errorEl = document.getElementById('stock-modal-error');
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }
}

/**
 * Oculta el mensaje de error dentro del modal.
 */
function hideModalError() {
    const errorEl = document.getElementById('stock-modal-error');
    if (errorEl) {
        errorEl.classList.add('hidden');
        errorEl.textContent = '';
    }
}

/**
 * Muestra un mensaje flash de éxito en la página, consistente con los
 * flash messages de Blade (fondo verde, borde verde).
 * @param {string} message
 */
function showFlashSuccess(message) {
    // Eliminar cualquier flash previo generado por JS
    const existing = document.getElementById('js-flash-success');
    if (existing) {
        existing.remove();
    }

    const flash = document.createElement('div');
    flash.id = 'js-flash-success';
    flash.setAttribute('role', 'alert');
    flash.className = 'mb-4 px-4 py-3 rounded-lg bg-green-100 text-green-800 border border-green-200 text-sm';
    flash.textContent = message;

    // Insertar al inicio del contenedor principal (primer hijo de .p-6)
    const container = document.querySelector('.p-6');
    if (container) {
        container.insertBefore(flash, container.firstChild);
    } else {
        document.body.insertBefore(flash, document.body.firstChild);
    }

    // Auto-eliminar tras 5 segundos
    setTimeout(() => flash.remove(), 5000);
}

/**
 * Inicializa el modal de actualización de stock.
 * Registra todos los event listeners necesarios.
 * Requisitos: 1.2, 1.3, 1.4, 4.4
 */
export function initStockModal() {
    const modal = document.getElementById('stock-modal');
    if (!modal) return;

    // ── Task 6.1: Apertura del modal ──────────────────────────────────────────
    // Escuchar clics en cualquier botón [data-stock-btn] (delegación en document
    // para soportar paginación dinámica si se añade en el futuro)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-stock-btn]');
        if (!btn) return;

        // Poblar campos del modal con los data-attributes del botón
        const nombre    = btn.dataset.productoNombre ?? '';
        const stock     = btn.dataset.productoStock  ?? '0';
        const productoId = btn.dataset.productoId    ?? '';
        const url       = btn.dataset.updateUrl      ?? '';

        document.getElementById('stock-modal-nombre').textContent       = nombre;
        document.getElementById('stock-modal-stock-actual').textContent = stock;
        document.getElementById('stock-modal-producto-id').value        = productoId;
        document.getElementById('stock-modal-url').value                = url;

        // Limpiar campo de cantidad y ocultar error
        const cantidadField = document.getElementById('stock-modal-cantidad');
        cantidadField.value = '';
        hideModalError();

        // Mostrar el modal y enfocar el campo de cantidad
        modal.classList.remove('hidden');
        cantidadField.focus();
    });

    // ── Task 6.2: Cierre del modal ────────────────────────────────────────────

    // Cerrar al hacer clic en el botón Cancelar
    document.getElementById('stock-modal-cancel').addEventListener('click', closeStockModal);

    // Cerrar al hacer clic en el backdrop (el div exterior, fuera del panel blanco)
    modal.addEventListener('click', (e) => {
        // El panel blanco es el primer hijo directo del modal
        const panel = modal.querySelector('.bg-white');
        if (panel && !panel.contains(e.target)) {
            closeStockModal();
        }
    });

    // Cerrar al presionar Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeStockModal();
        }
    });

    // ── Task 6.3: Envío del formulario con fetch PATCH ────────────────────────
    const submitBtn = document.getElementById('stock-modal-submit');

    submitBtn.addEventListener('click', async () => {
        const url       = document.getElementById('stock-modal-url').value;
        const cantidad  = document.getElementById('stock-modal-cantidad').value;
        const productoId = document.getElementById('stock-modal-producto-id').value;

        // Obtener el token CSRF del meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        // Deshabilitar el botón para evitar doble submit
        submitBtn.disabled = true;

        try {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ cantidad_adicional: Number(cantidad) }),
            });

            const data = await response.json();

            // ── Task 6.4: Respuesta exitosa ───────────────────────────────────
            if (response.ok && data.success) {
                // Cerrar el modal
                closeStockModal();

                // Actualizar la celda de stock en la tabla usando data-stock-value
                const stockCell = document.querySelector(`[data-stock-value="${productoId}"]`);
                if (stockCell) {
                    stockCell.textContent = data.nuevo_stock;
                }

                // Mostrar flash de confirmación
                showFlashSuccess('Stock actualizado correctamente.');

                // Re-habilitar el botón (modal cerrado, pero por si se reabre)
                submitBtn.disabled = false;
                return;
            }

            // ── Task 6.5: Manejo de errores HTTP ─────────────────────────────

            if (response.status === 422) {
                // Mostrar el primer mensaje de error de cantidad_adicional
                const firstError =
                    data?.errors?.cantidad_adicional?.[0] ??
                    data?.message ??
                    'Error de validación.';
                showModalError(firstError);
            } else {
                // Otros errores 4xx / 5xx
                showModalError('Error al actualizar el stock. Intente nuevamente.');
            }

        } catch (_networkError) {
            // ── Task 6.5: Error de red ────────────────────────────────────────
            showModalError('Error de conexión. Intente nuevamente.');
        }

        // Re-habilitar el botón tras cualquier error
        submitBtn.disabled = false;
    });
}

// Auto-inicialización al cargar el DOM
document.addEventListener('DOMContentLoaded', () => initStockModal());
