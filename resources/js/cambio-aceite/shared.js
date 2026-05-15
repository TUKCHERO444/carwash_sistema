/**
 * resources/js/cambio-aceite/shared.js
 *
 * Lógica compartida entre cambio-aceite/create.js y cambio-aceite/edit.js.
 * Exporta funciones de inicialización de UI y funciones puras de cálculo para testing.
 */

// ─────────────────────────────────────────────
// Funciones puras de cálculo (exportadas para PBT)
// ─────────────────────────────────────────────

/**
 * Calcula el total sumando el campo `total` de cada item y aplicando un descuento porcentual.
 *
 * @param {Array<{total: number}>} items - Array de productos con su total (cantidad * precio).
 * @param {number} porcentaje - Porcentaje de descuento en el rango [0, 100].
 * @returns {number} Total con descuento aplicado (2 decimales).
 */
export function calcularTotal(items, porcentaje) {
    const suma = items.reduce((acc, i) => acc + i.total, 0);
    const pct  = Math.min(Math.max(porcentaje, 0), 100);
    return +(suma * (1 - pct / 100)).toFixed(2);
}

/**
 * Genera el HTML de las filas de la tabla de productos.
 * Función pura: no accede al DOM, recibe items y devuelve HTML string.
 *
 * @param {Array<{nombre: string, cantidad: number, precio: number, total: number}>} items
 * @param {function} onActualizarCantidad - Función serializable para el onchange inline.
 * @param {function} onEliminar           - Función serializable para el onclick inline.
 * @returns {string} HTML de las filas <tr>.
 */
export function renderTablaHTML(items, onActualizarCantidad, onEliminar) {
    if (!items.length) {
        return '<tr><td colspan="5" class="px-6 py-4 text-center text-sm text-secondary">No hay productos agregados.</td></tr>';
    }
    return items.map((item, idx) => `
        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
            <td class="px-4 py-2 text-sm text-primary">${item.nombre}</td>
            <td class="px-4 py-2">
                <input type="number" min="1" value="${item.cantidad}"
                    class="w-20 border border-main rounded px-2 py-1 text-sm input-main"
                    data-cantidad-idx="${idx}">
            </td>
            <td class="px-4 py-2 text-sm text-secondary">S/ ${item.precio.toFixed(2)}</td>
            <td class="px-4 py-2 text-sm text-secondary">S/ ${item.total.toFixed(2)}</td>
            <td class="px-4 py-2">
                <button type="button" data-eliminar-idx="${idx}"
                    class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-xs font-medium">Eliminar</button>
            </td>
        </tr>
    `).join('');
}

// ─────────────────────────────────────────────
// Búsqueda de productos con debounce
// ─────────────────────────────────────────────

/**
 * Inicializa la búsqueda Ajax de productos con debounce.
 *
 * @param {object} config
 * @param {string}   config.inputId      - ID del input de búsqueda.
 * @param {string}   config.resultadosId - ID del div de resultados.
 * @param {function} config.onAgregar    - Callback invocado con el objeto producto al seleccionar.
 */
export function initBusquedaProductos({ inputId, resultadosId, onAgregar }) {
    const inputBuscar   = document.getElementById(inputId);
    const resultadosDiv = document.getElementById(resultadosId);
    let debounceTimer;

    if (!inputBuscar || !resultadosDiv) return;

    inputBuscar.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        if (q.length < 2) { _ocultarResultados(resultadosDiv); return; }
        debounceTimer = setTimeout(() => _buscarProductos(q, resultadosDiv, onAgregar), 300);
    });

    // Cerrar resultados al hacer clic fuera
    document.addEventListener('click', function (e) {
        if (!inputBuscar.contains(e.target) && !resultadosDiv.contains(e.target)) {
            _ocultarResultados(resultadosDiv);
        }
    });

    // Exponer para uso interno (limpiar input tras agregar)
    return { inputBuscar, resultadosDiv };
}

async function _buscarProductos(q, resultadosDiv, onAgregar) {
    const res  = await fetch(`/cambio-aceite/buscar-productos?q=${encodeURIComponent(q)}`);
    const data = await res.json();
    _mostrarResultados(data, resultadosDiv, onAgregar);
}

function _mostrarResultados(productos, resultadosDiv, onAgregar) {
    if (!productos.length) { _ocultarResultados(resultadosDiv); return; }
    resultadosDiv.innerHTML = productos.map((p, idx) =>
        `<div class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-slate-700 cursor-pointer text-sm text-primary" data-producto-idx="${idx}">
            ${p.nombre} — S/ ${parseFloat(p.precio_venta).toFixed(2)} (Stock: ${p.stock})
        </div>`
    ).join('');

    resultadosDiv._productosCache = productos;

    resultadosDiv.querySelectorAll('[data-producto-idx]').forEach(el => {
        el.addEventListener('click', function () {
            const idx = parseInt(this.dataset.productoIdx, 10);
            const producto = resultadosDiv._productosCache[idx];
            if (producto) onAgregar(producto);
        });
    });

    resultadosDiv.classList.remove('hidden');
}

function _ocultarResultados(resultadosDiv) {
    resultadosDiv.innerHTML = '';
    resultadosDiv.classList.add('hidden');
}

// ─────────────────────────────────────────────
// Renderizado de tabla de productos
// ─────────────────────────────────────────────

/**
 * Renderiza las filas de la tabla de productos en el tbody indicado.
 *
 * @param {Array<{nombre: string, cantidad: number, precio: number, total: number}>} items
 * @param {string}   tbodyId              - ID del elemento <tbody>.
 * @param {function} onActualizarCantidad - Callback invocado con (idx, valor) al cambiar cantidad.
 * @param {function} onEliminar           - Callback invocado con el índice al pulsar "Eliminar".
 */
export function renderTablaProductos(items, tbodyId, onActualizarCantidad, onEliminar) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    tbody.innerHTML = renderTablaHTML(items, onActualizarCantidad, onEliminar);

    tbody.querySelectorAll('[data-cantidad-idx]').forEach(input => {
        input.addEventListener('change', function () {
            onActualizarCantidad(parseInt(this.dataset.cantidadIdx, 10), this.value);
        });
    });

    tbody.querySelectorAll('[data-eliminar-idx]').forEach(btn => {
        btn.addEventListener('click', function () {
            onEliminar(parseInt(this.dataset.eliminarIdx, 10));
        });
    });
}

// ─────────────────────────────────────────────
// Recálculo de totales
// ─────────────────────────────────────────────

/**
 * Recalcula precio y total en base a los items.
 * Aplica descuento por porcentaje si el toggle está activo.
 * No llama a sincronizarHiddens — el caller es responsable de hacerlo.
 *
 * @param {Array<{total: number}>} items - Array de productos.
 * @param {string} precioId              - ID del input de precio (suma bruta).
 * @param {string} totalId               - ID del input de total (con descuento).
 * @param {string} toggleDescuentoId     - ID del checkbox de descuento por porcentaje.
 * @param {string} porcentajeId          - ID del input de porcentaje.
 */
export function recalcularTotales(items, precioId, totalId, toggleDescuentoId, porcentajeId) {
    const precio = items.reduce((acc, i) => acc + i.total, 0);

    const precioInput = document.getElementById(precioId);
    if (precioInput) precioInput.value = precio.toFixed(2);

    const toggleDescuento = document.getElementById(toggleDescuentoId);
    const totalInput      = document.getElementById(totalId);

    if (toggleDescuento?.checked) {
        const pct   = Math.min(parseFloat(document.getElementById(porcentajeId)?.value ?? 0) || 0, 100);
        const total = calcularTotal(items, pct);
        if (totalInput) totalInput.value = total.toFixed(2);
    } else {
        if (totalInput) totalInput.value = precio.toFixed(2);
    }
}

// ─────────────────────────────────────────────
// Sincronización de hidden inputs
// ─────────────────────────────────────────────

/**
 * Sincroniza el array de items con hidden inputs en el formulario.
 * Elimina los inputs previos (identificados por className) y crea nuevos.
 *
 * @param {Array<{producto_id: number, cantidad: number, precio: number, total: number}>} items
 * @param {string} formId    - ID del formulario.
 * @param {string} className - Clase CSS usada para identificar los hidden inputs (ej. 'hidden-producto').
 */
export function sincronizarHiddens(items, formId, className) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.querySelectorAll(`.${className}`).forEach(el => el.remove());

    items.forEach((item, idx) => {
        ['producto_id', 'cantidad', 'precio', 'total'].forEach(field => {
            const input     = document.createElement('input');
            input.type      = 'hidden';
            input.name      = `productos[${idx}][${field}]`;
            input.value     = item[field];
            input.className = className;
            form.appendChild(input);
        });
    });
}

// ─────────────────────────────────────────────
// Preview de foto
// ─────────────────────────────────────────────

/**
 * Inicializa el preview de imagen al seleccionar un archivo.
 * Si se proporciona currentId, oculta la foto actual al seleccionar una nueva.
 *
 * @param {string}      inputId   - ID del input[type=file].
 * @param {string}      previewId - ID del elemento <img> de preview.
 * @param {string|null} currentId - ID del elemento <img> de foto actual (opcional).
 */
export function initFotoPreview(inputId, previewId, currentId = null) {
    const input = document.getElementById(inputId);
    if (!input) return;

    input.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const preview = document.getElementById(previewId);
            if (preview) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            }
            if (currentId) {
                const current = document.getElementById(currentId);
                if (current) current.classList.add('hidden');
            }
        };
        reader.readAsDataURL(file);
    });
}

// ─────────────────────────────────────────────
// Método de pago
// ─────────────────────────────────────────────

/**
 * Inicializa la UI de selección de método de pago.
 *
 * @param {object}      config
 * @param {NodeList|Array} config.options          - Elementos .metodo-pago-option.
 * @param {NodeList|Array} config.radios           - Elementos .metodo-pago-radio.
 * @param {HTMLElement}    config.bloqueMixto      - Div del bloque mixto.
 * @param {HTMLElement}    config.inputTotal       - Input del total.
 * @param {HTMLElement}    config.inputAncla       - Input del precio (ancla para mixto).
 * @param {HTMLElement}    config.toggleDescManual - Checkbox de descuento manual.
 * @returns {{ actualizarUI: function, getMetodoPago: function }}
 */
export function initMetodoPago({ options, radios, bloqueMixto, inputTotal, inputAncla, toggleDescManual }) {
    function getMetodoPago() {
        for (const r of radios) { if (r.checked) return r.value; }
        return 'efectivo';
    }

    function actualizarUI() {
        const metodo  = getMetodoPago();
        const esMixto = metodo === 'mixto';

        options.forEach(opt => {
            const radio = opt.querySelector('.metodo-pago-radio');
            const span  = opt.querySelector('span');
            if (radio && radio.checked) {
                opt.classList.add('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900/20');
                opt.classList.remove('border-main');
                if (span) { span.classList.add('text-blue-700', 'dark:text-blue-400'); span.classList.remove('text-secondary'); }
            } else {
                opt.classList.remove('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900/20');
                opt.classList.add('border-main');
                if (span) { span.classList.remove('text-blue-700', 'dark:text-blue-400'); span.classList.add('text-secondary'); }
            }
        });

        const campoPorcentaje = document.getElementById('campo-porcentaje');
        const toggleDescuento = document.getElementById('toggle-descuento');

            if (esMixto) {
            if (bloqueMixto) bloqueMixto.classList.remove('hidden');
            if (campoPorcentaje) campoPorcentaje.classList.add('hidden');
            if (toggleDescuento) toggleDescuento.checked = false;
            if (inputTotal) {
                inputTotal.readOnly = true;
                inputTotal.classList.add('bg-slate-50', 'dark:bg-slate-800/50');
                if (inputAncla) inputTotal.value = parseFloat(inputAncla.value ?? 0).toFixed(2);
            }
        } else {
            if (bloqueMixto) bloqueMixto.classList.add('hidden');
            const descManualActivo    = toggleDescManual && toggleDescManual.checked;
            const descPorcentajeActivo = toggleDescuento && toggleDescuento.checked;
            if (!descManualActivo && !descPorcentajeActivo && inputTotal) {
                inputTotal.readOnly = true;
                inputTotal.classList.add('bg-slate-50', 'dark:bg-slate-800/50');
            }
        }
    }

    options.forEach(opt => {
        opt.addEventListener('click', function () {
            const radio = this.querySelector('.metodo-pago-radio');
            if (radio) radio.checked = true;
            actualizarUI();
        });
    });

    if (toggleDescManual) {
        toggleDescManual.addEventListener('change', function () {
            if (this.checked) {
                if (inputTotal) {
                    inputTotal.readOnly = false;
                    inputTotal.classList.remove('bg-slate-50', 'dark:bg-slate-800/50');
                    inputTotal.focus();
                }
            } else {
                if (inputTotal) {
                    inputTotal.readOnly = true;
                    inputTotal.classList.add('bg-slate-50', 'dark:bg-slate-800/50');
                    if (inputAncla) inputTotal.value = parseFloat(inputAncla.value ?? 0).toFixed(2);
                }
            }
        });
    }

    if (inputTotal) {
        inputTotal.addEventListener('input', function () {
            if (getMetodoPago() === 'mixto') {
                // Notificar a los callers para que re-validen mixto
                inputTotal.dispatchEvent(new CustomEvent('mixto:revalidar', { bubbles: true }));
            }
        });
    }

    return { actualizarUI, getMetodoPago };
}

// ─────────────────────────────────────────────
// Validación de pago mixto
// ─────────────────────────────────────────────

/**
 * Valida que la suma de los montos mixtos coincida con el total.
 * Muestra u oculta la alerta según corresponda.
 *
 * @param {HTMLElement} inputTotal - Input del total.
 * @param {string[]}    montoIds   - Array de IDs de los inputs de monto (efectivo, yape, izipay).
 * @param {string}      alertaId   - ID del div de alerta.
 */
export function validarMixto(inputTotal, montoIds, alertaId) {
    const total = parseFloat(inputTotal?.value ?? 0) || 0;
    const suma  = +montoIds.reduce((acc, id) => {
        const val = parseFloat(document.getElementById(id)?.value);
        return acc + (isNaN(val) ? 0 : val);
    }, 0).toFixed(2);

    const sumaDisplay  = document.getElementById('suma-mixto-display');
    const totalDisplay = document.getElementById('total-mixto-display');
    if (sumaDisplay)  sumaDisplay.textContent  = suma.toFixed(2);
    if (totalDisplay) totalDisplay.textContent = total.toFixed(2);

    const alerta = document.getElementById(alertaId);
    if (!alerta) return;

    // Mostrar alerta si algún campo tiene valor Y la suma no coincide con el total
    const algunoConValor = montoIds.some(id => {
        const val = parseFloat(document.getElementById(id)?.value);
        return !isNaN(val) && val > 0;
    });

    if (algunoConValor && Math.abs(suma - total) > 0.01) {
        alerta.classList.remove('hidden');
    } else {
        alerta.classList.add('hidden');
    }
}
