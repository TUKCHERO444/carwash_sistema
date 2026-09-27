// Feature: ajustes-inventario
// Module: ajustes/create.js
// Dynamic lines for inventory adjustments.

// ── Pure calculation functions (exported for testing) ──

/**
 * Renders the HTML for the detail table body.
 * @param {Array<{producto_id: number, nombre: string, stock: number, cantidad: number, tipo_linea: string}>} items
 * @param {Array<{id: number, nombre: string, stock: number}>} productos - Available products for the select
 * @param {string} tipoAjuste - Current adjustment type
 * @returns {string} HTML string
 */
export function renderTablaHTML(items, productos, tipoAjuste) {
    const opcionesProducto = productos.map(p => `<option value="${p.id}">${p.nombre} (Stock: ${p.stock})</option>`).join('');

    if (!items.length) {
        return '<tr><td colspan="5" class="px-6 py-8 text-center text-sm text-secondary">No hay productos agregados. Haga clic en "Agregar línea".</td></tr>';
    }

    const esConteo = tipoAjuste === 'conteo_fisico';

    return items.map((item, idx) => `
        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
            <td class="px-4 py-6">
                <select name="lineas[${idx}][producto_id]"
                        class="w-full border border-main rounded px-2 py-1 text-sm input-main"
                        onchange="actualizarStockDisplay(${idx}, this.value)"
                        ${item.producto_id ? '' : 'required'}>
                    <option value="">Seleccione...</option>
                    ${opcionesProducto}
                </select>
            </td>
            <td class="px-4 py-6 text-sm text-secondary">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                    ${item.cantidad > 0 ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'}">
                    ${item.cantidad > 0 ? 'Entrada' : 'Salida'}
                </span>
            </td>
            <td class="px-4 py-6 text-sm text-secondary font-mono">
                ${item.stock}
            </td>
            <td class="px-4 py-6">
                ${!esConteo
                    ? `<input type="number" min="-9999" max="9999" step="1" value="${item.cantidad}"
                        class="w-24 border border-main rounded px-2 py-1 text-sm input-main"
                        onchange="actualizarCantidad(${idx}, this.value)"
                        ${Math.abs(item.cantidad) > 0 ? '' : 'required'}>`
                    : `<div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-2 text-sm text-secondary">Cnt:</span>
                        <input type="number" min="0" max="99999" step="1" value="${item.cantidad + Math.abs(item.stock)}"
                            class="w-28 pl-8 pr-2 py-1 border border-main rounded text-sm input-main"
                            onchange="actualizarConteoFisico(${idx}, this.value)">
                       </div>`
                }
            </td>
            <td class="px-4 py-6">
                <button type="button" onclick="eliminarItem(${idx})"
                    class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-xs font-medium">Eliminar</button>
            </td>
        </tr>
    `).join('');
}

/**
 * Synchronises hidden inputs in the form to reflect the current items array.
 * @param {Array<Object>} items
 * @param {HTMLFormElement} form
 * @param {string} className - CSS class used to identify and remove old inputs
 * @param {string[]} fields - Field names to sync
 */
export function sincronizarHiddens(items, form, className, fields) {
    form.querySelectorAll(`.${className}`).forEach(el => el.remove());
    items.forEach((item, idx) => {
        fields.forEach(field => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `lineas[${idx}][${field}]`;
            input.value = item[field];
            input.className = className;
            form.appendChild(input);
        });
    });
}

// ── Module initialisation ──

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        let items = [];
        let tipoAjuste = window.tipoAjusteInicial || '';
        const productos = window.productos || [];

        // DOM references
        const form           = document.getElementById('form-ajuste');
        const tbodyDetalle   = document.getElementById('tbody-detalle');
        const selectTipo     = document.getElementById('tipo');
        const campoMotivo    = document.getElementById('campo-motivo');
        const ayudaMotivo    = document.getElementById('ayuda-motivo');
        const btnAgregar     = document.getElementById('btn-agregar-linea');
        const errorDetalle   = document.getElementById('error-detalle');

        // Update fields visibility based on tipo
        function actualizarCamposPorTipo(tipo) {
            tipoAjuste = tipo;
            const esConteo = tipo === 'conteo_fisico';
            const requiereMotivo = ['merma', 'daño', 'conteo_fisico'].includes(tipo);

            // Mostrar/ocultar campo motivo
            campoMotivo.classList.toggle('hidden', !requiereMotivo);
            ayudaMotivo.classList.toggle('hidden', !requiereMotivo);

            // Re-render tabla
            refrescarTabla();
        }

        // Add a new line
        btnAgregar.addEventListener('click', () => {
            items.push({
                producto_id: '',
                nombre: '',
                stock: 0,
                cantidad: 1,
            });
            refrescarTabla();
        });

        function actualizarStockDisplay(idx, value) {
            const prod = productos.find(p => p.id == value);
            items[idx].producto_id = value;
            items[idx].nombre = prod?.nombre || '';
            items[idx].stock = prod?.stock || 0;
            sincronizarHiddens(items, form, 'ajuste-hidden', ['producto_id', 'cantidad', 'conteo_fisico']);
        }

        function actualizarCantidad(idx, value) {
            const cantidad = parseInt(value, 10) || 0;
            items[idx].cantidad = cantidad;
            sincronizarHiddens(items, form, 'ajuste-hidden', ['producto_id', 'cantidad', 'conteo_fisico']);
        }

        function actualizarConteoFisico(idx, value) {
            const conteo = parseInt(value, 10) || 0;
            const stockActual = items[idx].stock || 0;
            items[idx].cantidad = conteo - stockActual;
            sincronizarHiddens(items, form, 'ajuste-hidden', ['producto_id', 'cantidad', 'conteo_fisico']);
        }

        function eliminarItem(idx) {
            items.splice(idx, 1);
            refrescarTabla();
        }

        function refrescarTabla() {
            tbodyDetalle.innerHTML = renderTablaHTML(items, productos, tipoAjuste);
            sincronizarHiddens(items, form, 'ajuste-hidden', ['producto_id', 'cantidad', 'conteo_fisico']);
        }

        // Tipo change handler
        selectTipo.addEventListener('change', (e) => {
            actualizarCamposPorTipo(e.target.value);
        });

        // Initial sync for edit view (when old data is present)
        const initHiddens = form.querySelectorAll('input[name^="lineas["]');
        if (initHiddens.length) {
            const tempItems = [];
            initHiddens.forEach(h => {
                const match = h.name.match(/lineas\[(\d+)\]\[(\w+)\]/);
                if (match) {
                    const [, idx, field] = match;
                    if (!tempItems[idx]) tempItems[idx] = {};
                    tempItems[idx][field] = h.value;
                }
            });
            items = tempItems.map(item => ({
                producto_id: item.producto_id || '',
                nombre: item.producto_id ? (productos.find(p => p.id == item.producto_id)?.nombre || '') : '',
                stock: item.producto_id ? (productos.find(p => p.id == item.producto_id)?.stock || 0) : 0,
                cantidad: parseInt(item.cantidad, 10) || 0,
                conteo_fisico: item.conteo_fisico ? parseInt(item.conteo_fisico, 10) : null,
            }));
            refrescarTabla();
        }

        // Initialize tipo from old() if present
        if (tipoAjuste) {
            actualizarCamposPorTipo(tipoAjuste);
        }
    });
}