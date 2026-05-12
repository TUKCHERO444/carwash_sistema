/**
 * Módulo: productos/index.js
 * Responsabilidad: Manejo de la UI en el listado de productos, incluyendo el toggle de estado.
 * Se utiliza delegación de eventos para soportar elementos dinámicos y seguir el patrón de diseño.
 */

/**
 * Actualiza el badge de estado de un producto en el DOM.
 * @param {Element} badge - Elemento span con el badge de estado.
 * @param {boolean} activo - Nuevo estado del producto.
 */
export function updateBadge(badge, activo) {
    if (!badge) return;

    // Limpiar clases de color anteriores
    badge.classList.remove('bg-green-100', 'text-green-800', 'bg-red-100', 'text-red-800');

    if (activo) {
        badge.classList.add('bg-green-100', 'text-green-800');
        badge.textContent = 'Activo';
    } else {
        badge.classList.add('bg-red-100', 'text-red-800');
        badge.textContent = 'Inactivo';
    }
}

/**
 * Actualiza el botón de toggle de un producto en el DOM.
 * @param {Element} button - Elemento button del toggle.
 * @param {boolean} activo - Nuevo estado del producto.
 * @param {string} nombre  - Nombre del producto para el aria-label.
 */
export function updateButton(button, activo, nombre) {
    if (!button) return;

    // Limpiar clases de color anteriores
    button.classList.remove(
        'bg-yellow-100', 'text-yellow-800', 'hover:bg-yellow-200',
        'bg-green-100', 'text-green-800', 'hover:bg-green-200'
    );

    if (activo) {
        // Producto activo -> botón para inactivar (amarillo)
        button.classList.add('bg-yellow-100', 'text-yellow-800', 'hover:bg-yellow-200');
        button.textContent = 'Inactivar';
        button.setAttribute('aria-label', `Inactivar producto ${nombre ?? ''}`.trim());
    } else {
        // Producto inactivo -> botón para activar (verde)
        button.classList.add('bg-green-100', 'text-green-800', 'hover:bg-green-200');
        button.textContent = 'Activar';
        button.setAttribute('aria-label', `Activar producto ${nombre ?? ''}`.trim());
    }
}

/**
 * Inicializa el listener de toggle de estado mediante delegación en el document.
 */
export function initToggleStatus() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-toggle-status]');
        if (!button) return;

        const url        = button.getAttribute('data-url');
        const productoId = button.getAttribute('data-producto-id');
        const nombre     = button.getAttribute('data-producto-nombre');
        const csrfToken  = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!url || !productoId) return;

        // Evitar múltiples clicks
        button.disabled = true;

        try {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Actualizar badge y botón
                const badge = document.querySelector(`span[data-producto-id="${productoId}"]`);
                updateBadge(badge, result.activo);
                updateButton(button, result.activo, nombre);
            } else {
                alert(result.message || 'Error al actualizar el estado');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Ocurrió un error inesperado al intentar cambiar el estado.');
        } finally {
            button.disabled = false;
        }
    });
}

// Inicialización automática
document.addEventListener('DOMContentLoaded', () => {
    initToggleStatus();
});
