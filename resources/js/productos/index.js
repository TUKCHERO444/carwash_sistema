/**
 * Módulo: productos/index.js
 * Responsabilidad: Manejo de la UI en el listado de productos.
 * - Toggle de estado activo/inactivo via AJAX.
 * - Búsqueda dinámica por nombre via AJAX con debounce.
 */

// ─── Toggle de estado ──────────────────────────────────────────────────────────

/**
 * Actualiza el badge de estado de un producto en el DOM.
 * @param {Element} badge - Elemento span con el badge de estado.
 * @param {boolean} activo - Nuevo estado del producto.
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
 * Actualiza el botón de toggle de un producto en el DOM.
 * @param {Element} button - Elemento button del toggle.
 * @param {boolean} activo - Nuevo estado del producto.
 * @param {string}  nombre - Nombre del producto para el aria-label.
 */
export function updateButton(button, activo, nombre) {
    if (!button) return;

    button.classList.remove(
        'bg-yellow-100', 'text-yellow-800', 'hover:bg-yellow-200',
        'bg-green-100',  'text-green-800',  'hover:bg-green-200'
    );

    if (activo) {
        button.classList.add('bg-yellow-100', 'dark:bg-yellow-900/30', 'text-yellow-800', 'dark:text-yellow-400', 'hover:bg-yellow-200', 'dark:hover:bg-yellow-900/50');
        button.textContent = 'Inactivar';
        button.setAttribute('aria-label', `Inactivar producto ${nombre ?? ''}`.trim());
    } else {
        button.classList.add('bg-green-100', 'dark:bg-green-900/30', 'text-green-800', 'dark:text-green-400', 'hover:bg-green-200', 'dark:hover:bg-green-900/50');
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

// ─── Búsqueda dinámica AJAX ────────────────────────────────────────────────────

/**
 * Construye el HTML de una fila <tr> para un producto recibido por AJAX.
 * Replica la estructura exacta del template Blade para garantizar consistencia visual.
 * @param {Object} p - Objeto producto del JSON de respuesta.
 * @returns {string} HTML de la fila.
 */
function buildRow(p) {
    const fotoHtml = p.foto
        ? `<img src="${p.foto}" alt="Foto de ${escHtml(p.nombre)}"
               class="w-10 h-10 object-cover rounded border border-gray-200 dark:border-border-dark">`
        : `<div class="w-10 h-10 rounded border border-main bg-gray-100 dark:bg-slate-800 flex items-center justify-center">
               <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                         d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
               </svg>
           </div>`;

    const badgeClass = p.activo
        ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400'
        : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400';

    const toggleClass = p.activo
        ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-400 hover:bg-yellow-200 dark:hover:bg-yellow-900/50'
        : 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400 hover:bg-green-200 dark:hover:bg-green-900/50';

    const toggleLabel = p.activo ? 'Inactivar' : 'Activar';
    const csrfToken   = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    return `
        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
            <td class="px-4 py-6 whitespace-nowrap">${fotoHtml}</td>

            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">
                ${escHtml(p.nombre)}
            </td>

            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                ${escHtml(p.categoria)}
            </td>

            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                S/ ${p.precio_compra}
            </td>

            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                S/ ${p.precio_venta}
            </td>

            <td class="px-6 py-8 whitespace-nowrap text-sm text-gray-700 dark:text-text-secondary-dark"
                data-stock-value="${p.id}">
                ${p.stock}
            </td>

            <td class="px-6 py-8 whitespace-nowrap text-sm">
                <span data-producto-id="${p.id}"
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badgeClass}">
                    ${p.activo ? 'Activo' : 'Inactivo'}
                </span>
            </td>

            <td class="px-6 py-8 whitespace-nowrap text-sm flex items-center gap-2">
                <a href="${p.edit_url}"
                   aria-label="Editar producto ${escHtml(p.nombre)}"
                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-xs font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Editar
                </a>

                <button type="button"
                        data-toggle-status
                        data-url="${p.toggle_url}"
                        data-producto-id="${p.id}"
                        data-producto-nombre="${escHtml(p.nombre)}"
                        aria-label="${toggleLabel} producto ${escHtml(p.nombre)}"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors ${toggleClass}">
                    ${toggleLabel}
                </button>

                <button type="button"
                        data-stock-btn
                        data-producto-id="${p.id}"
                        data-producto-nombre="${escHtml(p.nombre)}"
                        data-producto-stock="${p.stock}"
                        data-update-url="${p.stock_url}"
                        aria-label="Actualizar stock de ${escHtml(p.nombre)}"
                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-400 text-xs font-medium rounded-lg hover:bg-teal-200 dark:hover:bg-teal-900/50 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 7v10c0 1.1.9 2 2 2h12a2 2 0 002-2V7M4 7h16M4 7l2-3h12l2 3"/>
                    </svg>
                    Stock
                </button>

                <form method="POST" action="${p.destroy_url}" class="inline"
                      onsubmit="return confirm('¿Estás seguro de que deseas eliminar este producto?')">
                    <input type="hidden" name="_token" value="${escHtml(csrfToken)}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit"
                            aria-label="Eliminar producto ${escHtml(p.nombre)}"
                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-xs font-medium rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Eliminar
                    </button>
                </form>
            </td>
        </tr>`;
}

/**
 * Escapa caracteres HTML para evitar XSS al inyectar texto en el DOM.
 * @param {string} str
 * @returns {string}
 */
function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Inicializa la barra de búsqueda dinámica con debounce de 400ms.
 * Restaura el contenido original cuando el campo queda vacío.
 */
export function initBuscador() {
    const input       = document.getElementById('buscador-productos');
    const tbody       = document.getElementById('productos-tbody');
    const noResults   = document.getElementById('productos-no-results');
    const searchInfo  = document.getElementById('productos-search-info');
    const pagination  = document.getElementById('productos-pagination');
    const tableWrapper = document.getElementById('productos-table-wrapper');

    // Si alguno de los elementos clave no existe (ej: listado vacío), salir
    if (!input || !tbody) return;

    // Guardar el HTML original del tbody y la paginación para restaurarlos
    const originalTbody      = tbody.innerHTML;
    const originalPagination = pagination ? pagination.innerHTML : '';

    let debounceTimer;
    let lastQuery = '';

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const q = input.value.trim();

        // Si el query no cambió, no hacer nada
        if (q === lastQuery) return;

        debounceTimer = setTimeout(() => ejecutarBusqueda(q), 400);
    });

    async function ejecutarBusqueda(q) {
        lastQuery = q;

        // Campo vacío → restaurar estado original con paginación
        if (q === '') {
            tbody.innerHTML = originalTbody;
            if (pagination) pagination.innerHTML = originalPagination;
            if (tableWrapper) tableWrapper.classList.remove('hidden');
            if (noResults)   noResults.classList.add('hidden');
            if (searchInfo)  searchInfo.classList.add('hidden');
            return;
        }

        try {
            const url      = `/productos/buscar?q=${encodeURIComponent(q)}`;
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json();

            if (!data.success) throw new Error('Respuesta no exitosa del servidor');

            // Ocultar paginación durante búsqueda activa
            if (pagination) pagination.innerHTML = '';

            if (data.productos.length === 0) {
                // Sin resultados
                tbody.innerHTML = '';
                if (tableWrapper) tableWrapper.classList.add('hidden');
                if (noResults)    noResults.classList.remove('hidden');
                if (searchInfo) {
                    searchInfo.textContent = `Sin resultados para "${q}"`;
                    searchInfo.classList.remove('hidden');
                }
            } else {
                // Renderizar resultados
                if (tableWrapper) tableWrapper.classList.remove('hidden');
                if (noResults)    noResults.classList.add('hidden');

                tbody.innerHTML = data.productos.map(buildRow).join('');

                if (searchInfo) {
                    const n = data.productos.length;
                    searchInfo.textContent = `${n} resultado${n !== 1 ? 's' : ''} para "${q}"`;
                    searchInfo.classList.remove('hidden');
                }
            }
        } catch (error) {
            console.error('[buscador-productos] Error en la búsqueda:', error);
        }
    }
}

// ─── Inicialización automática ─────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initToggleStatus();
    initBuscador();
});
