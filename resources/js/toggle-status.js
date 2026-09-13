/**
 * Módulo: toggle-status.js
 * Responsabilidad: Toggle AJAX de estado activo/inactivo compartido por los
 * listados del panel (productos, servicios, usuarios, trabajadores).
 * - updateBadge: actualiza el badge de estado dentro de la fila.
 * - updateButton: actualiza el botón de toggle (texto y aria-label).
 * - initToggleStatus: delegación en document; el badge se localiza dentro de la
 *   misma fila via `closest('tr').querySelector('[data-badge]')`.
 */

/**
 * Actualiza el badge de estado en el DOM.
 * @param {Element} badge - Elemento span con el badge de estado.
 * @param {boolean} activo - Nuevo estado del registro.
 */
export function updateBadge(badge, activo) {
    if (!badge) return;

    badge.classList.remove(
        'bg-green-100', 'dark:bg-green-900/30', 'text-green-800', 'dark:text-green-400',
        'bg-red-100',   'dark:bg-red-900/30',   'text-red-700',  'dark:text-red-400'
    );

    if (activo) {
        badge.classList.add('bg-green-100', 'dark:bg-green-900/30', 'text-green-800', 'dark:text-green-400');
        badge.textContent = 'Activo';
    } else {
        badge.classList.add('bg-red-100', 'dark:bg-red-900/30', 'text-red-700', 'dark:text-red-400');
        badge.textContent = 'Inactivo';
    }
}

/**
 * Actualiza el botón de toggle en el DOM.
 * @param {Element} button - Elemento button del toggle.
 * @param {boolean} activo - Nuevo estado del registro.
 * @param {string}  nombre - Nombre del registro para el aria-label.
 * @param {string}  tipo   - Tipo de registro ('producto', 'servicio', ...).
 */
export function updateButton(button, activo, nombre, tipo = 'producto') {
    if (!button) return;

    button.classList.remove(
        'bg-yellow-100', 'text-yellow-800', 'hover:bg-yellow-200',
        'bg-green-100',  'text-green-800',  'hover:bg-green-200'
    );

    if (activo) {
        button.classList.add('bg-yellow-100', 'dark:bg-yellow-900/30', 'text-yellow-800', 'dark:text-yellow-400', 'hover:bg-yellow-200', 'dark:hover:bg-yellow-900/50');
        button.textContent = 'Inactivar';
        button.setAttribute('aria-label', `Inactivar ${tipo} ${nombre ?? ''}`.trim());
    } else {
        button.classList.add('bg-green-100', 'dark:bg-green-900/30', 'text-green-800', 'dark:text-green-400', 'hover:bg-green-200', 'dark:hover:bg-green-900/50');
        button.textContent = 'Activar';
        button.setAttribute('aria-label', `Activar ${tipo} ${nombre ?? ''}`.trim());
    }
}

/**
 * Inicializa el listener de toggle de estado mediante delegación en el document.
 * El botón debe tener `data-toggle-status`, `data-url` y `data-nombre`;
 * opcionalmente `data-tipo` para el aria-label.
 */
export function initToggleStatus() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-toggle-status]');
        if (!button) return;

        const url       = button.getAttribute('data-url');
        const nombre    = button.getAttribute('data-nombre');
        const tipo      = button.getAttribute('data-tipo') ?? 'producto';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!url) return;

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

            if (result.success) {
                const badge = button.closest('tr')?.querySelector('[data-badge]');
                updateBadge(badge, result.activo);
                updateButton(button, result.activo, nombre, tipo);
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