/**
 * resources/js/users/toggle.js
 *
 * Módulo JavaScript para el toggle de activación de usuarios.
 * Escucha clicks en botones con `data-toggle-url` usando delegación de eventos
 * en `document`, envía PATCH vía fetch con el token CSRF, y actualiza el DOM
 * en respuesta exitosa o muestra un mensaje de error sin modificar el estado visual.
 *
 * Las funciones de actualización del DOM se exportan para facilitar los tests.
 *
 * Requisitos: 2.5, 2.6, 4.3
 */

// ─────────────────────────────────────────────────────────────────────────────
// Funciones puras de actualización del DOM (exportadas para testabilidad)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Actualiza el badge de estado de un usuario en el DOM.
 *
 * @param {Element} badge - Elemento span con el badge de estado.
 * @param {number|boolean} activo - Nuevo estado del usuario (1/true = activo, 0/false = inactivo).
 */
export function updateBadge(badge, activo) {
    if (!badge) return;

    // Limpiar clases de color anteriores
    badge.classList.remove(
        'bg-green-100', 'text-green-800',
        'bg-red-100', 'text-red-800'
    );

    if (activo) {
        badge.classList.add('bg-green-100', 'text-green-800');
        badge.textContent = 'Activo';
    } else {
        badge.classList.add('bg-red-100', 'text-red-800');
        badge.textContent = 'Inactivo';
    }
}

/**
 * Actualiza el botón de toggle de un usuario en el DOM.
 *
 * @param {Element} button - Elemento button del toggle.
 * @param {number|boolean} activo - Nuevo estado del usuario (1/true = activo, 0/false = inactivo).
 * @param {string} userName - Nombre del usuario para el aria-label.
 */
export function updateButton(button, activo, userName) {
    if (!button) return;

    // Limpiar clases de color anteriores
    button.classList.remove(
        'bg-yellow-100', 'text-yellow-800', 'hover:bg-yellow-200',
        'bg-green-100', 'text-green-800', 'hover:bg-green-200'
    );

    if (activo) {
        // Usuario activo → botón para inactivar (amarillo)
        button.classList.add('bg-yellow-100', 'text-yellow-800', 'hover:bg-yellow-200');
        button.textContent = 'Inactivar';
        button.setAttribute('aria-label', `Inactivar usuario ${userName ?? ''}`.trim());
    } else {
        // Usuario inactivo → botón para activar (verde)
        button.classList.add('bg-green-100', 'text-green-800', 'hover:bg-green-200');
        button.textContent = 'Activar';
        button.setAttribute('aria-label', `Activar usuario ${userName ?? ''}`.trim());
    }
}

/**
 * Muestra un mensaje de error temporal en el DOM.
 *
 * @param {string} message - Mensaje de error a mostrar.
 */
export function showError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.setAttribute('role', 'alert');
    errorDiv.className = 'fixed top-4 right-4 z-50 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-200 text-sm shadow-md';
    errorDiv.textContent = message;

    document.body.appendChild(errorDiv);

    // Auto-eliminar después de 4 segundos
    setTimeout(() => {
        errorDiv.remove();
    }, 4000);
}

// ─────────────────────────────────────────────────────────────────────────────
// Lógica principal de toggle
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Maneja el click en un botón de toggle.
 * Envía PATCH al servidor y actualiza el DOM según la respuesta.
 *
 * @param {Element} button - Botón que fue clickeado.
 */
async function handleToggleClick(button) {
    const url    = button.dataset.toggleUrl;
    const userId = button.dataset.userId;

    if (!url || !userId) return;

    // Leer el token CSRF desde el meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    // Deshabilitar el botón durante la petición para evitar doble-click
    button.disabled = true;

    try {
        const response = await fetch(url, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN':  csrfToken,
                'Content-Type':  'application/json',
                'Accept':        'application/json',
            },
        });

        if (response.ok) {
            // HTTP 200: actualizar badge y botón
            const data = await response.json();

            const badge = document.querySelector(`[data-user-id="${userId}"].inline-flex.rounded-full`);
            updateBadge(badge, data.activo);
            updateButton(button, data.activo);
        } else {
            // HTTP 403, 404 u otro error: mostrar mensaje sin modificar el estado visual
            let errorMessage = 'Error al cambiar el estado del usuario.';

            try {
                const errorData = await response.json();
                if (errorData.message) {
                    errorMessage = errorData.message;
                }
            } catch {
                // No se pudo parsear el JSON de error — usar mensaje genérico
            }

            showError(errorMessage);
        }
    } catch {
        // Error de red o timeout
        showError('Error de conexión. Por favor, inténtalo de nuevo.');
    } finally {
        // Re-habilitar el botón
        button.disabled = false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Delegación de eventos (solo en entorno de navegador)
// ─────────────────────────────────────────────────────────────────────────────

if (typeof document !== 'undefined') {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-toggle-url]');
        if (!button) return;

        handleToggleClick(button);
    });
}
