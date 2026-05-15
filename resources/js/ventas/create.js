// Feature: js-modularization
// Module: ventas/create.js
// Extracted from resources/views/ventas/create.blade.php

// ── Pure calculation functions (exported for testing) ──

/**
 * Calculates the total from an array of items applying an optional discount percentage.
 * @param {Array<{subtotal: number}>} items
 * @param {number} porcentaje - Discount percentage [0, 100]
 * @returns {number}
 */
export function calcularTotal(items, porcentaje) {
    const subtotal = items.reduce((acc, i) => acc + i.subtotal, 0);
    const pct = Math.min(Math.max(porcentaje, 0), 100);
    return +(subtotal * (1 - pct / 100)).toFixed(2);
}

import { Validation } from '../utils/validation.js';

/**
 * Renders the HTML for the detail table body.
 * @param {Array<{nombre: string, cantidad: number, precio_unitario: number, subtotal: number}>} items
 * @returns {string} HTML string
 */
export function renderTablaHTML(items) {
    if (!items.length) {
        return '<tr><td colspan="5" class="px-6 py-8 text-center text-sm text-secondary">No hay productos agregados.</td></tr>';
    }
    return items.map((item, idx) => `
        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
            <td class="px-4 py-6 text-sm text-primary">${item.nombre}</td>
            <td class="px-4 py-6">
                <input type="number" min="1" value="${item.cantidad}"
                    class="w-20 border border-main rounded px-2 py-1 text-sm input-main"
                    onchange="actualizarCantidad(${idx}, this.value)">
            </td>
            <td class="px-4 py-6 text-sm text-secondary">S/ ${item.precio_unitario.toFixed(2)}</td>
            <td class="px-4 py-6 text-sm text-secondary">S/ ${item.subtotal.toFixed(2)}</td>
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
            input.name = `productos[${idx}][${field}]`;
            input.value = item[field];
            input.className = className;
            form.appendChild(input);
        });
    });
}

// ── Module initialisation ──

document.addEventListener('DOMContentLoaded', () => {
    let items = [];

    // DOM references
    const inputBuscar    = document.getElementById('buscar-producto');
    const resultadosDiv  = document.getElementById('resultados-busqueda');
    const form           = document.getElementById('form-venta');
    const tbodyDetalle   = document.getElementById('tbody-detalle');
    const inputSubtotal  = document.getElementById('subtotal');
    const inputTotal     = document.getElementById('total');
    const inputAncla     = document.getElementById('subtotal');
    const toggleDesc     = document.getElementById('toggle-descuento');
    const campoPorcentaje = document.getElementById('campo-porcentaje');
    const inputPorcentaje = document.getElementById('porcentaje');
    const toggleDescManual = document.getElementById('toggle-descuento-manual');
    const bloqueMixto    = document.getElementById('bloque-mixto');
    const metodoPagoOptions = document.querySelectorAll('.metodo-pago-option');
    const metodoPagoRadios  = document.querySelectorAll('.metodo-pago-radio');

    let debounceTimer;

    // ── Search ──

    inputBuscar.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        if (q.length < 2) { ocultarResultados(); return; }
        debounceTimer = setTimeout(() => buscarProductos(q), 300);
    });

    async function buscarProductos(q) {
        const res = await fetch(`/ventas/buscar-productos?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        mostrarResultados(data);
    }

    function mostrarResultados(productos) {
        if (!productos.length) { ocultarResultados(); return; }
        resultadosDiv.innerHTML = productos.map(p =>
            `<div class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-slate-700 cursor-pointer text-sm text-primary" onclick='agregarProducto(${JSON.stringify(p)})'>
                ${p.nombre} — S/ ${parseFloat(p.precio_venta).toFixed(2)} (Stock: ${p.stock})
            </div>`
        ).join('');
        resultadosDiv.classList.remove('hidden');
    }

    function ocultarResultados() {
        resultadosDiv.innerHTML = '';
        resultadosDiv.classList.add('hidden');
    }

    // Close search results when clicking outside
    document.addEventListener('click', function (e) {
        if (!inputBuscar.contains(e.target) && !resultadosDiv.contains(e.target)) {
            ocultarResultados();
        }
    });

    // ── Items management ──

    // Expose to window so inline onclick handlers in rendered HTML can call them
    window.agregarProducto = function agregarProducto(producto) {
        const existente = items.find(i => i.producto_id === producto.id);
        if (existente) {
            existente.cantidad++;
            existente.subtotal = +(existente.cantidad * existente.precio_unitario).toFixed(2);
        } else {
            items.push({
                producto_id:     producto.id,
                nombre:          producto.nombre,
                cantidad:        1,
                precio_unitario: +parseFloat(producto.precio_venta).toFixed(2),
                subtotal:        +parseFloat(producto.precio_venta).toFixed(2),
            });
        }
        renderTabla();
        recalcularTotales();
        ocultarResultados();
        inputBuscar.value = '';
    };

    window.actualizarCantidad = function actualizarCantidad(idx, val) {
        const cantidad = Math.max(1, parseInt(val) || 1);
        items[idx].cantidad = cantidad;
        items[idx].subtotal = +(cantidad * items[idx].precio_unitario).toFixed(2);
        renderTabla();
        recalcularTotales();
    };

    window.eliminarItem = function eliminarItem(idx) {
        items.splice(idx, 1);
        renderTabla();
        recalcularTotales();
    };

    function renderTabla() {
        tbodyDetalle.innerHTML = renderTablaHTML(items);
    }

    function recalcularTotales() {
        const subtotal = items.reduce((acc, i) => acc + i.subtotal, 0);
        inputSubtotal.value = subtotal.toFixed(2);

        const usarPorcentaje = toggleDesc.checked;
        if (usarPorcentaje) {
            const pct = Math.min(parseFloat(inputPorcentaje.value) || 0, 100);
            inputTotal.value = (subtotal * (1 - pct / 100)).toFixed(2);
        } else {
            inputTotal.value = subtotal.toFixed(2);
        }
        sincronizarHiddens(items, form, 'hidden-producto', ['producto_id', 'cantidad', 'precio_unitario', 'subtotal']);
    }

    // ── Discount controls ──

    inputPorcentaje.addEventListener('input', function () {
        if (parseFloat(this.value) > 100) {
            this.value = 100;
        }
        recalcularTotales();
    });

    toggleDesc.addEventListener('change', function () {
        campoPorcentaje.classList.toggle('hidden', !this.checked);
        if (this.checked) {
            inputTotal.readOnly = false;
            inputTotal.classList.remove('bg-gray-50');
        } else if (!toggleDescManual.checked) {
            inputTotal.readOnly = true;
            inputTotal.classList.add('bg-gray-50');
        }
        recalcularTotales();
    });

    toggleDescManual.addEventListener('change', function () {
        if (this.checked) {
            inputTotal.readOnly = false;
            inputTotal.classList.remove('bg-gray-50');
            inputTotal.focus();
        } else {
            inputTotal.readOnly = true;
            inputTotal.classList.add('bg-gray-50');
            if (inputAncla) inputTotal.value = parseFloat(inputAncla.value || 0).toFixed(2);
        }
    });

    // ── Método de pago ──

    function getMetodoPago() {
        for (const r of metodoPagoRadios) { if (r.checked) return r.value; }
        return 'efectivo';
    }

    function actualizarUIMetodoPago() {
        const metodo  = getMetodoPago();
        const esMixto = metodo === 'mixto';

        metodoPagoOptions.forEach(opt => {
            const radio = opt.querySelector('.metodo-pago-radio');
            const span  = opt.querySelector('span');
            if (radio.checked) {
                opt.classList.add('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900/20');
                opt.classList.remove('border-main');
                span.classList.add('text-blue-700', 'dark:text-blue-400');
                span.classList.remove('text-secondary');
            } else {
                opt.classList.remove('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900/20');
                opt.classList.add('border-main');
                span.classList.remove('text-blue-700', 'dark:text-blue-400');
                span.classList.add('text-secondary');
            }
        });

        if (esMixto) {
            bloqueMixto.classList.remove('hidden');
            campoPorcentaje.classList.add('hidden');
            toggleDesc.checked = false;
            inputTotal.readOnly = true;
            inputTotal.classList.add('bg-gray-50');
            if (inputAncla) inputTotal.value = parseFloat(inputAncla.value || 0).toFixed(2);
            validarMixto();
        } else {
            bloqueMixto.classList.add('hidden');
            if (!toggleDescManual.checked && !toggleDesc.checked) {
                inputTotal.readOnly = true;
                inputTotal.classList.add('bg-gray-50');
            }
        }
    }

    metodoPagoOptions.forEach(opt => {
        opt.addEventListener('click', function () {
            this.querySelector('.metodo-pago-radio').checked = true;
            actualizarUIMetodoPago();
        });
    });

    // Re-validate mixto when total is edited manually
    inputTotal.addEventListener('input', function () {
        if (getMetodoPago() === 'mixto') validarMixto();
    });

    function validarMixto() {
        if (getMetodoPago() !== 'mixto') return;
        const total    = parseFloat(inputTotal.value || 0);
        const efectivo = parseFloat(document.getElementById('monto_efectivo').value || 0);
        const yape     = parseFloat(document.getElementById('monto_yape').value || 0);
        const izipay   = parseFloat(document.getElementById('monto_izipay').value || 0);
        const suma     = +(efectivo + yape + izipay).toFixed(2);
        document.getElementById('suma-mixto-display').textContent  = suma.toFixed(2);
        document.getElementById('total-mixto-display').textContent = total.toFixed(2);
        const alerta = document.getElementById('alerta-mixto');
        if (suma > 0 && Math.abs(suma - total) > 0.01) {
            alerta.classList.remove('hidden');
        } else {
            alerta.classList.add('hidden');
        }
    }

    ['monto_efectivo', 'monto_yape', 'monto_izipay'].forEach(id => {
        document.getElementById(id).addEventListener('input', validarMixto);
    });

    // ── Initialise ──
    renderTabla();
    recalcularTotales();
    actualizarUIMetodoPago();

    // Validación del formulario
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!Validation.validate(form)) {
                e.preventDefault();
            }
        });
    }
});
