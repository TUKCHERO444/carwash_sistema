/**
 * Módulo: trabajadores/index.js
 * Responsabilidad: Manejo de la UI en el listado de trabajadores, incluyendo el toggle de estado.
 * Se utiliza delegación de eventos para consistencia con el patrón de diseño.
 */

/**
 * Actualiza el badge de estado de un trabajador en el DOM.
 * @param {Element} badge - Elemento span con el badge de estado.
 * @param {boolean} estado - Nuevo estado del trabajador.
 */
export function updateBadge(badge, estado) {
    if (!badge) return;

    badge.classList.remove('bg-green-100', 'text-green-800', 'bg-red-100', 'text-red-800');

    if (estado) {
        badge.classList.add('bg-green-100', 'text-green-800');
        badge.textContent = 'Activo';
    } else {
        badge.classList.add('bg-red-100', 'text-red-800');
        badge.textContent = 'Inactivo';
    }
}

/**
 * Actualiza el botón de toggle de un trabajador en el DOM.
 * @param {Element} button - Elemento button del toggle.
 * @param {boolean} estado - Nuevo estado del trabajador.
 * @param {string} nombre  - Nombre del trabajador para el aria-label.
 */
export function updateButton(button, estado, nombre) {
    if (!button) return;

    button.classList.remove(
        'bg-yellow-100', 'text-yellow-800', 'hover:bg-yellow-200',
        'bg-green-100', 'text-green-800', 'hover:bg-green-200'
    );

    if (estado) {
        button.classList.add('bg-yellow-100', 'text-yellow-800', 'hover:bg-yellow-200');
        button.textContent = 'Inactivar';
        button.setAttribute('aria-label', `Inactivar trabajador ${nombre ?? ''}`.trim());
    } else {
        button.classList.add('bg-green-100', 'text-green-800', 'hover:bg-green-200');
        button.textContent = 'Activar';
        button.setAttribute('aria-label', `Activar trabajador ${nombre ?? ''}`.trim());
    }
}

/**
 * Inicializa el listener de toggle de estado mediante delegación en el document.
 */
export function initToggleStatus() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-toggle-status]');
        if (!button) return;

        // Verificar que sea un botón de trabajador (podría haber otros toggles)
        const trabajadorId = button.getAttribute('data-trabajador-id');
        if (!trabajadorId) return;

        const url    = button.getAttribute('data-url');
        const nombre = button.getAttribute('data-trabajador-nombre');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!url) return;

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
                const badge = document.querySelector(`span[data-trabajador-id="${trabajadorId}"]`);
                updateBadge(badge, result.estado);
                updateButton(button, result.estado, nombre);
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

document.addEventListener('DOMContentLoaded', () => {
    initToggleStatus();
});
