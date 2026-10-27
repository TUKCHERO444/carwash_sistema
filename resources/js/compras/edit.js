// Feature: compras-ingreso-mercaderia
// Module: compras/edit.js
// Dynamic lines for purchase detail.

import { initBusquedaProveedores } from './buscar-proveedores.js';
import { initBusquedaProductos } from './buscar-productos.js';
import { escapeHtml } from '../utils/escape-html.js';

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
    if (!items.length) {
        return '<tr><td colspan="5" class="px-6 py-8 text-center text-sm text-secondary">No hay productos agregados. Haga clic en "Agregar línea".</td></tr>';
    }

    return items.map((item, idx) => {
        const nombreProducto = item.producto_id
            ? (productos.find(p => p.id == item.producto_id)?.nombre || '')
            : '';

        return `
        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors" data-idx="${idx}">
            <td class="px-4 py-6">
                <div class="relative">
                    <input type="text"
                        id="buscar-producto-${idx}"
                        class="w-full border border-main rounded px-2 py-1 text-sm input-main"
                        placeholder="Buscar producto..."
                        autocomplete="off"
                        value="${escapeHtml(nombreProducto)}">
                    <input type="hidden" id="producto-id-${idx}" name="detalle[${idx}][producto_id]" value="${escapeHtml(item.producto_id || '')}">
                    <div id="resultados-producto-${idx}"
                         class="hidden absolute z-10 w-full mt-1 bg-surface border border-main rounded-lg shadow-lg max-h-60 overflow-y-auto">
                    </div>
                </div>
            </td>
            <td class="px-4 py-6">
                <input type="number" min="1" step="1" value="${escapeHtml(item.cantidad)}"
                    class="w-24 border border-main rounded px-2 py-1 text-sm input-main"
                    oninput="actualizarCantidadRealTime(${idx}, this.value)"
                    required>
            </td>
            <td class="px-4 py-6">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-2 text-sm text-secondary">S/</span>
                    <input type="number" min="0" step="0.01" value="${escapeHtml(item.costo_unitario > 0 ? item.costo_unitario.toFixed(2) : '')}"
                        class="w-28 pl-6 pr-2 py-1 border border-main rounded text-sm input-main"
                        oninput="actualizarCostoRealTime(${idx}, this.value)"
                        required
                        placeholder="0.00">
                </div>
            </td>
            <td class="px-4 py-6 text-sm text-secondary font-mono">${item.costo_unitario > 0 ? 'S/ ' + item.subtotal.toFixed(2) : ''}</td>
            <td class="px-4 py-6">
                <button type="button" onclick="eliminarItem(${idx})"
                    class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-xs font-medium">Eliminar</button>
            </td>
        </tr>
        `;
    }).join('');
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

        // Inicializar búsqueda dinámica de proveedores
        initBusquedaProveedores({
            inputId: 'buscar-proveedor',
            resultadosId: 'resultados-proveedores',
            hiddenInputId: 'proveedor_id',
        });

        // Capture current DOM input values into items array before re-render
        function capturarValoresDOM() {
            const rows = tbodyDetalle.querySelectorAll('tr');
            rows.forEach((row, idx) => {
                if (!items[idx]) return;
                const inputProducto = row.querySelector('input[name$="[producto_id]"]');
                const inputCantidad  = row.querySelector('input[type="number"][min="1"]');
                const inputCosto     = row.querySelector('input[type="number"][min="0"][step="0.01"]');
                if (inputProducto) items[idx].producto_id = inputProducto.value;
                if (inputCantidad)  items[idx].cantidad = parseInt(inputCantidad.value, 10) || 1;
                if (inputCosto)     items[idx].costo_unitario = parseFloat(inputCosto.value) || 0;
                items[idx].subtotal = calcularSubtotal(items[idx].cantidad, items[idx].costo_unitario);
            });
        }

        // Add a new line
        btnAgregar.addEventListener('click', () => {
            capturarValoresDOM();
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
            capturarValoresDOM();
            items.splice(idx, 1);
            refrescarTabla();
        }

        function refrescarTabla() {
            tbodyDetalle.innerHTML = renderTablaHTML(items, productos);
            const total = calcularTotal(items);
            inputSubtotal.value = total.toFixed(2);
            inputTotal.value = total.toFixed(2);
            sincronizarHiddens(items, form, 'compra-hidden', ['producto_id', 'cantidad', 'costo_unitario', 'subtotal']);

            // Inicializar autocomplete de productos para cada fila
            items.forEach((item, idx) => {
                initBusquedaProductos({
                    inputId: `buscar-producto-${idx}`,
                    resultadosId: `resultados-producto-${idx}`,
                    hiddenInputId: `producto-id-${idx}`,
                    onSelect: (id, nombre, precioCompra, stock) => {
                        items[idx].producto_id = id;
                        items[idx].nombre = nombre;
                        // Si no tiene costo, sugerir el precio de compra
                        if (!items[idx].costo_unitario || items[idx].costo_unitario === 0) {
                            items[idx].costo_unitario = parseFloat(precioCompra) || 0;
                            items[idx].subtotal = calcularSubtotal(items[idx].cantidad, items[idx].costo_unitario);
                            refrescarTabla();
                        }
                        sincronizarHiddens(items, form, 'compra-hidden', ['producto_id', 'cantidad', 'costo_unitario', 'subtotal']);
                    },
                });
            });
        }

        // Use oninput for real-time subtotal calculation
        function actualizarCantidadRealTime(idx, value) {
            const cantidad = parseInt(value, 10) || 1;
            items[idx].cantidad = cantidad;
            items[idx].subtotal = calcularSubtotal(cantidad, items[idx].costo_unitario);
            // Update only the subtotal cell and total inputs without full re-render
            const row = tbodyDetalle.rows[idx];
            if (row) {
                const subtotalCell = row.cells[3];
                if (subtotalCell) subtotalCell.textContent = `S/ ${items[idx].subtotal.toFixed(2)}`;
            }
            const total = calcularTotal(items);
            inputSubtotal.value = total.toFixed(2);
            inputTotal.value = total.toFixed(2);
            sincronizarHiddens(items, form, 'compra-hidden', ['producto_id', 'cantidad', 'costo_unitario', 'subtotal']);
        }

        function actualizarCostoRealTime(idx, value) {
            const costo = parseFloat(value) || 0;
            items[idx].costo_unitario = costo;
            items[idx].subtotal = calcularSubtotal(items[idx].cantidad, costo);
            const row = tbodyDetalle.rows[idx];
            if (row) {
                const subtotalCell = row.cells[3];
                if (subtotalCell) subtotalCell.textContent = `S/ ${items[idx].subtotal.toFixed(2)}`;
            }
            const total = calcularTotal(items);
            inputSubtotal.value = total.toFixed(2);
            inputTotal.value = total.toFixed(2);
            sincronizarHiddens(items, form, 'compra-hidden', ['producto_id', 'cantidad', 'costo_unitario', 'subtotal']);
        }

        // Make real-time functions globally available for oninput handlers
        window.actualizarCantidadRealTime = actualizarCantidadRealTime;
        window.actualizarCostoRealTime = actualizarCostoRealTime;

        // Capture DOM values and sync hidden inputs before form submission
        form.addEventListener('submit', (e) => {
            if (!validarDetalle()) {
                e.preventDefault();
                return false;
            }
            capturarValoresDOM();
            refrescarTabla();
        });

        function validarDetalle() {
            const rows = tbodyDetalle.querySelectorAll('tr');
            let valido = true;
            let mensaje = '';

            rows.forEach((row, idx) => {
                const inputProducto = row.querySelector('input[name$="[producto_id]"]');
                const inputCantidad  = row.querySelector('input[type="number"][min="1"]');
                const inputCosto     = row.querySelector('input[type="number"][min="0"][step="0.01"]');

                if (!inputProducto || !inputProducto.value) {
                    valido = false;
                    mensaje = `Línea ${idx + 1}: debe seleccionar un producto.`;
                } else if (!inputCantidad || !inputCantidad.value || parseInt(inputCantidad.value, 10) < 1) {
                    valido = false;
                    mensaje = `Línea ${idx + 1}: la cantidad debe ser al menos 1.`;
                } else if (!inputCosto || inputCosto.value === '' || parseFloat(inputCosto.value) <= 0) {
                    valido = false;
                    mensaje = `Línea ${idx + 1}: debe ingresar un costo unitario mayor a 0.`;
                }
            });

            if (!valido) {
                errorDetalle.textContent = mensaje;
                errorDetalle.classList.remove('hidden');
            } else {
                errorDetalle.classList.add('hidden');
            }

            return valido;
        }

        // Initial sync (when old input data is present after validation errors)
        const oldDetalle = window.oldDetalle || [];
        if (Array.isArray(oldDetalle) && oldDetalle.length) {
            items = oldDetalle
                .filter(item => item && (item.producto_id || item.cantidad || item.costo_unitario))
                .map(item => {
                    const productoId = item.producto_id || '';
                    return {
                        producto_id: productoId,
                        nombre: productoId ? (productos.find(p => p.id == productoId)?.nombre || '') : '',
                        cantidad: parseInt(item.cantidad, 10) || 1,
                        costo_unitario: parseFloat(item.costo_unitario) || 0,
                        subtotal: parseFloat(item.subtotal) || parseFloat((parseInt(item.cantidad, 10) || 1) * (parseFloat(item.costo_unitario) || 0)).toFixed(2),
                    };
                });
            if (items.length) {
                refrescarTabla();
            }
        } else {
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
                if (items.some(i => i.producto_id || i.cantidad > 1 || i.costo_unitario > 0)) {
                    refrescarTabla();
                }
            }
        }
    });
}