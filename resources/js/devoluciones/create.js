// Feature: devoluciones-ventas
// Module: devoluciones/create.js
// Formulario de devolución/cambio de productos vendidos.

// ── Pure calculation functions (exported for testing) ──

/**
 * Calculates the refund total from return lines.
 * @param {Array<{cantidad: number, precio_unitario: number}>} lineas
 * @returns {number} Total rounded to 2 decimals.
 */
export function calcularTotalDevuelto(lineas) {
    const total = lineas.reduce((acc, l) => {
        const cantidad = Math.max(0, parseInt(l.cantidad) || 0);
        const precio = parseFloat(l.precio_unitario) || 0;
        return acc + cantidad * precio;
    }, 0);
    return +total.toFixed(2);
}

// ── Module initialisation ──

// Guard para entornos sin DOM (tests de Node importan este módulo para
// ejercitar las funciones puras): en el navegador siempre está definido.
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('form-devolucion');
        if (!form) return;

        const inputsCantidad = Array.from(form.querySelectorAll('.cantidad-devolver'));
        const inputTotal = document.getElementById('total-devuelto');
        const tipoRadios = Array.from(form.querySelectorAll('.tipo-radio'));
        const tipoOptions = Array.from(form.querySelectorAll('.tipo-option'));
        const bloqueCambio = document.getElementById('bloque-cambio');
        const inputBuscar = document.getElementById('buscar-sustituto');
        const resultadosDiv = document.getElementById('resultados-sustituto');
        const sustitutoBox = document.getElementById('sustituto-seleccionado');
        const inputSustitutoId = document.getElementById('producto_nuevo_id');

        let debounceTimer;

        function leerLineas() {
            return inputsCantidad
                .filter((el) => !el.disabled)
                .map((el) => ({
                    cantidad: el.value,
                    precio_unitario: el.dataset.precio,
                }));
        }

        function recalcularTotal() {
            if (inputTotal) {
                inputTotal.value = calcularTotalDevuelto(leerLineas()).toFixed(2);
            }
        }

        inputsCantidad.forEach((el) => {
            el.addEventListener('input', () => {
                const max = parseInt(el.dataset.disponible) || 0;
                let val = parseInt(el.value) || 0;
                if (val < 0) val = 0;
                if (val > max) val = max;
                if (String(val) !== el.value) el.value = val;
                recalcularTotal();
            });
        });

        function getTipo() {
            for (const r of tipoRadios) { if (r.checked) return r.value; }
            return 'devolucion';
        }

        function actualizarUITipo() {
            const tipo = getTipo();
            tipoOptions.forEach((opt) => {
                const radio = opt.querySelector('.tipo-radio');
                const span = opt.querySelector('span');
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
            if (bloqueCambio) {
                bloqueCambio.classList.toggle('hidden', tipo !== 'cambio');
            }
        }

        tipoOptions.forEach((opt) => {
            opt.addEventListener('click', function () {
                this.querySelector('.tipo-radio').checked = true;
                actualizarUITipo();
            });
        });

        // ── Sustituto search ──

        if (inputBuscar) {
            inputBuscar.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                const q = this.value.trim();
                if (q.length < 2) { ocultarResultados(); return; }
                debounceTimer = setTimeout(() => buscarSustituto(q), 300);
            });

            document.addEventListener('click', function (e) {
                if (!inputBuscar.contains(e.target) && !resultadosDiv.contains(e.target)) {
                    ocultarResultados();
                }
            });
        }

        async function buscarSustituto(q) {
            const res = await fetch(`/ventas/buscar-productos?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            mostrarResultados(data);
        }

        function mostrarResultados(productos) {
            if (!productos.length) { ocultarResultados(); return; }
            resultadosDiv.innerHTML = productos.map((p) =>
                `<div class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-slate-700 cursor-pointer text-sm text-primary" onclick='seleccionarSustituto(${JSON.stringify(p)})'>
                    ${p.nombre} — S/ ${parseFloat(p.precio_venta).toFixed(2)} (Stock: ${p.stock})
                </div>`
            ).join('');
            resultadosDiv.classList.remove('hidden');
        }

        function ocultarResultados() {
            resultadosDiv.innerHTML = '';
            resultadosDiv.classList.add('hidden');
        }

        window.seleccionarSustituto = function seleccionarSustituto(producto) {
            inputSustitutoId.value = producto.id;
            document.getElementById('sustituto-nombre').textContent = producto.nombre;
            document.getElementById('sustituto-precio').textContent = parseFloat(producto.precio_venta).toFixed(2);
            document.getElementById('sustituto-stock').textContent = producto.stock;
            sustitutoBox.classList.remove('hidden');
            const cantidadNueva = document.getElementById('cantidad_nueva');
            if (cantidadNueva) cantidadNueva.max = producto.stock;
            ocultarResultados();
            inputBuscar.value = '';
        };

        // ── Initialise ──
        recalcularTotal();
        actualizarUITipo();
    });
}
