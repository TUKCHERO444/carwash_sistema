/**
 * resources/js/users/toggle.js
 *
 * Manejo del toggle de estado activo/inactivo para usuarios vía AJAX.
 * Emula el patrón y diseño del módulo de productos.
 */

/**
 * Actualiza el badge de estado de un usuario en el DOM.
 * @param {Element} badge - Elemento span con el badge de estado.
 * @param {boolean} activo - Nuevo estado del usuario.
 */
export function updateBadge(badge, activo) {
    if (!badge) return;

    badge.classList.remove(
        'bg-green-100', 'dark:bg-green-900/30', 'text-green-800', 'dark:text-green-400',
        'bg-red-100',   'dark:bg-red-900/30',   'text-red-800',  'dark:text-red-400'
    );

    if (activo) {
        badge.classList.add('bg-green-100', 'dark:bg-green-900/30', 'text-green-800', 'dark:text-green-400');
        badge.textContent = 'Activo';
    } else {
        badge.classList.add('bg-red-100', 'dark:bg-red-900/30', 'text-red-800', 'dark:text-red-400');
        badge.textContent = 'Inactivo';
    }
}

/**
 * Actualiza el botón de toggle de un usuario en el DOM.
 * @param {Element} button - Elemento button del toggle.
 * @param {boolean} activo - Nuevo estado del usuario.
 * @param {string}  nombre - Nombre del usuario para el aria-label.
 */
export function updateButton(button, activo, nombre) {
    if (!button) return;

    button.classList.remove(
        'bg-yellow-100', 'text-yellow-800', 'hover:bg-yellow-200',
        'bg-green-100',  'text-green-800',  'hover:bg-green-200',
        'dark:bg-yellow-900/30', 'dark:text-yellow-400', 'dark:hover:bg-yellow-900/50',
        'dark:bg-green-900/30',  'dark:text-green-400',  'dark:hover:bg-green-900/50'
    );

    if (activo) {
        button.classList.add('bg-yellow-100', 'dark:bg-yellow-900/30', 'text-yellow-800', 'dark:text-yellow-400', 'hover:bg-yellow-200', 'dark:hover:bg-yellow-900/50');
        button.textContent = 'Inactivar';
        button.setAttribute('aria-label', `Inactivar usuario ${nombre ?? ''}`.trim());
    } else {
        button.classList.add('bg-green-100', 'dark:bg-green-900/30', 'text-green-800', 'dark:text-green-400', 'hover:bg-green-200', 'dark:hover:bg-green-900/50');
        button.textContent = 'Activar';
        button.setAttribute('aria-label', `Activar usuario ${nombre ?? ''}`.trim());
    }
}

/**
 * Inicializa el listener de toggle de estado mediante delegación en el document.
 */
export function initToggleStatus() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-toggle-url]');
        if (!button) return;

        const url      = button.getAttribute('data-toggle-url');
        const userId   = button.getAttribute('data-user-id');
        const nombre   = button.getAttribute('data-user-nombre');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!url || !userId) return;

        button.disabled = true;

        try {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            });

            const result = await response.json();

            if (response.ok && result.activo !== undefined) {
                const badge = document.querySelector(`span[data-user-id="${userId}"]`);
                updateBadge(badge, result.activo);
                updateButton(button, result.activo, nombre);
            } else {
                alert(result.message || 'Error al actualizar el estado');
            }
        } catch (error) {
            console.error('[users-toggle] Error:', error);
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
