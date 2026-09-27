// Feature: compras-ingreso-mercaderia
// Module: compras/create.js
// Dynamic lines for purchase detail.

// ── Pure calculation functions (exported for testing) ──

/**
 * Calculates the total from an array of items.
 * @param {Array<{subtotal: number}>} items
 * @returns {number}
 */
export function calcularTotal(items) {
    return +items.reduce((acc, i) => acc + i.subtotal, 0).toFixed(2);
}

/**
 * Calculates the subtotal for a line.
 * @param {number} cantidad
 * @param {number} costoUnitario
 * @returns {number}
 */
export function calcularSubtotal(cantidad, costoUnitario) {
    return +(cantidad * costoUnitario).toFixed(2);
}

/**
 * Renders the HTML for the detail table body.
 * @param {Array<{producto_id: number, nombre: string, cantidad: number, costo_unitario: number, subtotal: number}>} items
 * @param {Array<{id: number, nombre: string}>} productos - Available products for the select
 * @returns {string} HTML string
 */
export function renderTablaHTML(items, productos) {
    const opcionesProducto = productos.map(p => `<option value="${p.id}">${p.nombre}</option>`).join('');

    if (!items.length) {
        return '<tr><td colspan="5" class="px-6 py-8 text-center text-sm text-secondary">No hay productos agregados. Haga clic en "Agregar línea".</td></tr>';
    }

    return items.map((item, idx) => `
        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
            <td class="px-4 py-6">
                <select name="detalle[${idx}][producto_id]"
                        class="w-full border border-main rounded px-2 py-1 text-sm input-main"
                        onchange="actualizarProducto(${idx}, this.value)">
                    <option value="">Seleccione...</option>
                    ${opcionesProducto}
                </select>
            </td>
            <td class="px-4 py-6">
                <input type="number" min="1" step="1" value="${item.cantidad}"
                    class="w-24 border border-main rounded px-2 py-1 text-sm input-main"
                    onchange="actualizarCantidad(${idx}, this.value)">
            </td>
            <td class="px-4 py-6">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-2 text-sm text-secondary">S/</span>
                    <input type="number" min="0" step="0.01" value="${item.costo_unitario.toFixed(2)}"
                        class="w-28 pl-6 pr-2 py-1 border border-main rounded text-sm input-main"
                        onchange="actualizarCosto(${idx}, this.value)">
                </div>
            </td>
            <td class="px-4 py-6 text-sm text-secondary font-mono">S/ ${item.subtotal.toFixed(2)}</td>
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
            input.name = `detalle[${idx}][${field}]`;
            input.value = item[field];
            input.className = className;
            form.appendChild(input);
        });
    });
}

// ── Module initialisation ──

// Guard para entornos sin DOM (tests de Node importan este módulo para
// ejercitar las funciones puras): en el navegador siempre está definido.
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        let items = [];

        // DOM references
        const form           = document.getElementById('form-compra');
        const tbodyDetalle   = document.getElementById('tbody-detalle');
        const inputSubtotal  = document.getElementById('subtotal');
        const inputTotal     = document.getElementById('total');
        const btnAgregar     = document.getElementById('btn-agregar-linea');
        const errorDetalle   = document.getElementById('error-detalle');
        const productos      = window.productos || [];

        // Add a new line
        btnAgregar.addEventListener('click', () => {
            items.push({
                producto_id: '',
                nombre: '',
                cantidad: 1,
                costo_unitario: 0,
                subtotal: 0,
            });
            refrescarTabla();
        });

        function actualizarProducto(idx, value) {
            const prod = productos.find(p => p.id == value);
            items[idx].producto_id = value;
            items[idx].nombre = prod?.nombre || '';
            sincronizarHiddens(items, form, 'compra-hidden', ['producto_id', 'cantidad', 'costo_unitario', 'subtotal']);
        }

        function actualizarCantidad(idx, value) {
            const cantidad = parseInt(value, 10) || 1;
            items[idx].cantidad = cantidad;
            items[idx].subtotal = calcularSubtotal(cantidad, items[idx].costo_unitario);
            refrescarTabla();
        }

        function actualizarCosto(idx, value) {
            const costo = parseFloat(value) || 0;
            items[idx].costo_unitario = costo;
            items[idx].subtotal = calcularSubtotal(items[idx].cantidad, costo);
            refrescarTabla();
        }

        function eliminarItem(idx) {
            items.splice(idx, 1);
            refrescarTabla();
        }

        function refrescarTabla() {
            tbodyDetalle.innerHTML = renderTablaHTML(items, productos);
            const total = calcularTotal(items);
            inputSubtotal.value = total.toFixed(2);
            inputTotal.value = total.toFixed(2);
            sincronizarHiddens(items, form, 'compra-hidden', ['producto_id', 'cantidad', 'costo_unitario', 'subtotal']);
        }

        // Initial sync for edit view (when old data is present)
        const initHiddens = form.querySelectorAll('input[name^="detalle["]');
        if (initHiddens.length) {
            const tempItems = [];
            initHiddens.forEach(h => {
                const match = h.name.match(/detalle\[(\d+)\]\[(\w+)\]/);
                if (match) {
                    const [, idx, field] = match;
                    if (!tempItems[idx]) tempItems[idx] = {};
                    tempItems[idx][field] = h.value;
                }
            });
            items = tempItems.map(item => ({
                producto_id: item.producto_id || '',
                nombre: item.producto_id ? (productos.find(p => p.id == item.producto_id)?.nombre || '') : '',
                cantidad: parseInt(item.cantidad, 10) || 1,
                costo_unitario: parseFloat(item.costo_unitario) || 0,
                subtotal: parseFloat(item.subtotal) || 0,
            }));
            refrescarTabla();
        }
    });
}