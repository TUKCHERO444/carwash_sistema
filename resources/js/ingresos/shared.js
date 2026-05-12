/**
 * resources/js/ingresos/shared.js
 *
 * Lógica compartida entre ingresos/create.js, ingresos/edit.js e ingresos/confirmar.js.
 * Exporta funciones de inicialización de UI y funciones puras de cálculo para testing.
 */

// ─────────────────────────────────────────────
// Funciones puras de cálculo (exportadas para PBT)
// ─────────────────────────────────────────────

/**
 * Calcula el precio base sumando el precio del vehículo y los precios de los servicios.
 *
 * @param {number} precioVehiculo - Precio del vehículo seleccionado.
 * @param {Array<{precio: number}>} items - Array de servicios con su precio.
 * @returns {number} Precio base (2 decimales).
 */
export function calcularPrecio(precioVehiculo, items) {
    const sumServicios = items.reduce((acc, i) => acc + i.precio, 0);
    return +(precioVehiculo + sumServicios).toFixed(2);
}

/**
 * Aplica un descuento porcentual al precio base.
 *
 * @param {number} precio - Precio base.
 * @param {number} porcentaje - Porcentaje de descuento en el rango [0, 100].
 * @returns {number} Total con descuento (2 decimales).
 */
export function calcularTotalConDescuento(precio, porcentaje) {
    const pct = Math.min(Math.max(porcentaje, 0), 100);
    return +(precio * (1 - pct / 100)).toFixed(2);
}

// ─────────────────────────────────────────────
// Búsqueda de servicios con debounce
// ─────────────────────────────────────────────

/**
 * Inicializa la búsqueda Ajax de servicios con debounce.
 *
 * @param {object} config
 * @param {string} config.inputId      - ID del input de búsqueda.
 * @param {string} config.resultadosId - ID del div de resultados.
 * @param {function} config.onAgregar  - Callback invocado con el objeto servicio al seleccionar.
 */
export function initBusquedaServicios({ inputId, resultadosId, onAgregar }) {
    const inputBuscar   = document.getElementById(inputId);
    const resultadosDiv = document.getElementById(resultadosId);
    let debounceTimer;

    if (!inputBuscar || !resultadosDiv) return;

    inputBuscar.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        if (q.length < 2) { _ocultarResultados(resultadosDiv); return; }
        debounceTimer = setTimeout(() => _buscarServicios(q, resultadosDiv, onAgregar), 300);
    });

    // Cerrar resultados al hacer clic fuera
    document.addEventListener('click', function (e) {
        if (!inputBuscar.contains(e.target) && !resultadosDiv.contains(e.target)) {
            _ocultarResultados(resultadosDiv);
        }
    });
}

async function _buscarServicios(q, resultadosDiv, onAgregar) {
    const res  = await fetch(`/ingresos/buscar-servicios?q=${encodeURIComponent(q)}`);
    const data = await res.json();
    _mostrarResultados(data, resultadosDiv, onAgregar);
}

function _mostrarResultados(servicios, resultadosDiv, onAgregar) {
    if (!servicios.length) { _ocultarResultados(resultadosDiv); return; }
    resultadosDiv.innerHTML = servicios.map((s, idx) =>
        `<div class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm" data-servicio-idx="${idx}">
            ${s.nombre} — S/ ${parseFloat(s.precio).toFixed(2)}
        </div>`
    ).join('');

    // Guardar los objetos en el div contenedor para accederlos por índice
    resultadosDiv._serviciosCache = servicios;

    resultadosDiv.querySelectorAll('[data-servicio-idx]').forEach(el => {
        el.addEventListener('click', function () {
            const idx = parseInt(this.dataset.servicioIdx, 10);
            const servicio = resultadosDiv._serviciosCache[idx];
            if (servicio) onAgregar(servicio);
        });
    });

    resultadosDiv.classList.remove('hidden');
}

function _ocultarResultados(resultadosDiv) {
    resultadosDiv.innerHTML = '';
    resultadosDiv.classList.add('hidden');
}

// ─────────────────────────────────────────────
// Renderizado de tabla de servicios
// ─────────────────────────────────────────────

/**
 * Renderiza las filas de la tabla de servicios en el tbody indicado.
 *
 * @param {Array<{nombre: string, precio: number}>} items - Array de servicios.
 * @param {string} tbodyId   - ID del elemento <tbody>.
 * @param {function} onEliminar - Callback invocado con el índice al pulsar "Eliminar".
 */
export function renderTablaServicios(items, tbodyId, onEliminar) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;

    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">No hay servicios agregados.</td></tr>';
        return;
    }

    tbody.innerHTML = items.map((item, idx) => `
        <tr>
            <td class="px-4 py-2 text-sm text-gray-900">${item.nombre}</td>
            <td class="px-4 py-2 text-sm text-gray-700">S/ ${item.precio.toFixed(2)}</td>
            <td class="px-4 py-2">
                <button type="button" data-eliminar-idx="${idx}"
                    class="text-red-600 hover:text-red-800 text-xs font-medium">Eliminar</button>
            </td>
        </tr>
    `).join('');

    tbody.querySelectorAll('[data-eliminar-idx]').forEach(btn => {
        btn.addEventListener('click', function () {
            onEliminar(parseInt(this.dataset.eliminarIdx, 10));
        });
    });
}

// ─────────────────────────────────────────────
// Sincronización de hidden inputs
// ─────────────────────────────────────────────

/**
 * Sincroniza el array de items con hidden inputs en el formulario.
 * Elimina los inputs previos y crea uno nuevo por cada item.
 *
 * @param {Array<{servicio_id: number}>} items - Array de servicios.
 * @param {string} formId - ID del formulario.
 */
export function sincronizarHiddens(items, formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.querySelectorAll('.hidden-servicio').forEach(el => el.remove());

    items.forEach((item, idx) => {
        const input     = document.createElement('input');
        input.type      = 'hidden';
        input.name      = `servicios[${idx}][servicio_id]`;
        input.value     = item.servicio_id;
        input.className = 'hidden-servicio';
        form.appendChild(input);
    });
}

// ─────────────────────────────────────────────
// Preview de foto
// ─────────────────────────────────────────────

/**
 * Inicializa el preview de imagen al seleccionar un archivo.
 * Si se proporciona currentId, oculta la foto actual al seleccionar una nueva.
 *
 * @param {string} inputId   - ID del input[type=file].
 * @param {string} previewId - ID del elemento <img> de preview.
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
 * @param {object} config
 * @param {NodeList|Array} config.options        - Elementos .metodo-pago-option.
 * @param {NodeList|Array} config.radios         - Elementos .metodo-pago-radio.
 * @param {HTMLElement}    config.bloqueMixto    - Div del bloque mixto.
 * @param {HTMLElement}    config.inputTotal     - Input del total.
 * @param {HTMLElement}    config.inputAncla     - Input del precio (ancla para mixto).
 * @param {HTMLElement}    config.toggleDescManual - Checkbox de descuento manual.
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
                opt.classList.add('border-blue-500', 'bg-blue-50');
                opt.classList.remove('border-gray-300');
                if (span) { span.classList.add('text-blue-700'); span.classList.remove('text-gray-700'); }
            } else {
                opt.classList.remove('border-blue-500', 'bg-blue-50');
                opt.classList.add('border-gray-300');
                if (span) { span.classList.remove('text-blue-700'); span.classList.add('text-gray-700'); }
            }
        });

        const campoPorcentaje   = document.getElementById('campo-porcentaje');
        const toggleDescuento   = document.getElementById('toggle-descuento');

        if (esMixto) {
            if (bloqueMixto) bloqueMixto.classList.remove('hidden');
            if (campoPorcentaje) campoPorcentaje.classList.add('hidden');
            if (toggleDescuento) toggleDescuento.checked = false;
            if (inputTotal) {
                inputTotal.readOnly = true;
                inputTotal.classList.add('bg-gray-50');
                if (inputAncla) inputTotal.value = parseFloat(inputAncla.value ?? 0).toFixed(2);
            }
        } else {
            if (bloqueMixto) bloqueMixto.classList.add('hidden');
            const descManualActivo = toggleDescManual && toggleDescManual.checked;
            const descPorcentajeActivo = toggleDescuento && toggleDescuento.checked;
            if (!descManualActivo && !descPorcentajeActivo && inputTotal) {
                inputTotal.readOnly = true;
                inputTotal.classList.add('bg-gray-50');
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
                    inputTotal.classList.remove('bg-gray-50');
                    inputTotal.focus();
                }
            } else {
                if (inputTotal) {
                    inputTotal.readOnly = true;
                    inputTotal.classList.add('bg-gray-50');
                    if (inputAncla) inputTotal.value = parseFloat(inputAncla.value ?? 0).toFixed(2);
                }
            }
        });
    }

    if (inputTotal) {
        inputTotal.addEventListener('input', function () {
            if (getMetodoPago() === 'mixto') {
                // Trigger validarMixto via custom event so callers can hook in
                inputTotal.dispatchEvent(new CustomEvent('mixto:revalidar', { bubbles: true }));
            }
        });
    }

    // Expose actualizarUI so callers can trigger it on init
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
 * @param {string[]} montoIds      - Array de IDs de los inputs de monto (efectivo, yape, izipay).
 * @param {string} alertaId        - ID del div de alerta.
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

// ─────────────────────────────────────────────
// Recálculo de totales
// ─────────────────────────────────────────────

/**
 * Recalcula precio y total en base a los items y el vehículo seleccionado.
 * Aplica descuento por porcentaje si el toggle está activo.
 * Llama a sincronizarHiddens al final.
 *
 * @param {Array<{precio: number}>} items          - Array de servicios.
 * @param {string} vehiculoSelectId                - ID del select de vehículo.
 * @param {string} precioId                        - ID del input de precio.
 * @param {string} totalId                         - ID del input de total.
 * @param {string} toggleDescuentoId               - ID del checkbox de descuento por porcentaje.
 * @param {string} porcentajeId                    - ID del input de porcentaje.
 */
export function recalcularTotales(items, vehiculoSelectId, precioId, totalId, toggleDescuentoId, porcentajeId) {
    const vehiculoSelect = document.getElementById(vehiculoSelectId);
    const precioVehiculo = parseFloat(
        vehiculoSelect?.options[vehiculoSelect.selectedIndex]?.dataset.precio ?? 0
    );

    const precio = calcularPrecio(precioVehiculo, items);

    const precioInput = document.getElementById(precioId);
    if (precioInput) precioInput.value = precio.toFixed(2);

    const toggleDescuento = document.getElementById(toggleDescuentoId);
    const totalInput      = document.getElementById(totalId);

    if (toggleDescuento?.checked) {
        const pct   = Math.min(parseFloat(document.getElementById(porcentajeId)?.value ?? 0) || 0, 100);
        const total = calcularTotalConDescuento(precio, pct);
        if (totalInput) totalInput.value = total.toFixed(2);
    } else {
        if (totalInput) totalInput.value = precio.toFixed(2);
    }
}
