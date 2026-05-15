/**
 * resources/js/cambio-aceite/confirmar.js
 *
 * Lógica específica de la vista cambio-aceite/confirmar (Panel_Confirmacion).
 * Importa funciones compartidas desde ./shared.js.
 * Lee datos iniciales desde variables globales expuestas por la vista Blade.
 */

import {
    initBusquedaProductos,
    renderTablaProductos,
    recalcularTotales,
    sincronizarHiddens,
    initFotoPreview,
    initMetodoPago,
    validarMixto,
} from './shared.js';
import { initBuscadorPlaca } from '../buscador-placa.js';

// ── Datos iniciales desde la vista Blade ──
const productosConfirmar  = window.productosConfirmar  ?? [];
const confirmarMetodoPago = window.confirmarMetodoPago ?? 'efectivo';
const confirmarMontos     = window.confirmarMontos     ?? {};

// ── Estado local ──
let items = productosConfirmar.map(p => ({
    producto_id: p.id,
    nombre:      p.nombre,
    precio:      +parseFloat(p.precio).toFixed(2),
    cantidad:    p.cantidad ?? 1,
    total:       +parseFloat(p.total ?? p.precio).toFixed(2),
}));

// ── Funciones expuestas en window para uso inline desde Blade ──

/**
 * Cambia la acción del formulario a la ruta de actualización (PUT)
 * y lo envía para guardar cambios sin confirmar el ticket.
 */
window.submitActualizar = function submitActualizar() {
    const form = document.getElementById('form-cambio-aceite');
    if (!form) return;

    form.action = window._confirmarUpdateUrl ?? form.action;

    // Agregar _method=PUT si no existe ya
    if (!form.querySelector('input[name="_method"]')) {
        const methodInput   = document.createElement('input');
        methodInput.type    = 'hidden';
        methodInput.name    = '_method';
        methodInput.value   = 'PUT';
        form.appendChild(methodInput);
    }

    form.submit();
};

/**
 * Solicita confirmación al usuario y, si acepta, envía el formulario
 * de eliminación del ticket.
 */
window.confirmarEliminacion = function confirmarEliminacion() {
    if (confirm('¿Estás seguro de eliminar este ticket? Esta acción no se puede deshacer.')) {
        const formEliminar = document.getElementById('form-eliminar');
        if (formEliminar) formEliminar.submit();
    }
};

// ── Callbacks de tabla ──

/**
 * Actualiza la cantidad de un producto en la tabla, recalcula totales
 * y sincroniza los hidden inputs del formulario.
 *
 * @param {number} idx - Índice del item en el array.
 * @param {string|number} val - Nuevo valor de cantidad.
 */
function actualizarCantidad(idx, val) {
    const cantidad = Math.max(1, parseInt(val) || 1);
    items[idx].cantidad = cantidad;
    items[idx].total    = +(cantidad * items[idx].precio).toFixed(2);
    renderTablaProductos(items, 'tbody-detalle', actualizarCantidad, eliminarItem);
    recalcularTotales(items, 'precio', 'total', 'toggle-descuento', 'porcentaje');
    sincronizarHiddens(items, 'form-cambio-aceite', 'hidden-producto');
}

/**
 * Elimina un producto de la tabla, recalcula totales y sincroniza hiddens.
 *
 * @param {number} idx - Índice del item a eliminar.
 */
function eliminarItem(idx) {
    items.splice(idx, 1);
    renderTablaProductos(items, 'tbody-detalle', actualizarCantidad, eliminarItem);
    recalcularTotales(items, 'precio', 'total', 'toggle-descuento', 'porcentaje');
    sincronizarHiddens(items, 'form-cambio-aceite', 'hidden-producto');
}

/**
 * Agrega un producto seleccionado desde la búsqueda.
 * Ignora duplicados (sin agregar segunda fila).
 *
 * @param {object} producto - Objeto producto devuelto por la búsqueda Ajax.
 */
function onAgregarProducto(producto) {
    // Sin duplicados — ignorar si ya está en la tabla
    if (items.find(i => i.producto_id === producto.id)) return;

    items.push({
        producto_id: producto.id,
        nombre:      producto.nombre,
        cantidad:    1,
        precio:      +parseFloat(producto.precio_venta).toFixed(2),
        total:       +parseFloat(producto.precio_venta).toFixed(2),
    });

    renderTablaProductos(items, 'tbody-detalle', actualizarCantidad, eliminarItem);
    recalcularTotales(items, 'precio', 'total', 'toggle-descuento', 'porcentaje');
    sincronizarHiddens(items, 'form-cambio-aceite', 'hidden-producto');

    const inputBuscar = document.getElementById('buscar-producto');
    if (inputBuscar) inputBuscar.value = '';
}

// ── Inicialización al cargar el DOM ──
document.addEventListener('DOMContentLoaded', () => {
    // Renderizar tabla con productos pre-cargados desde el servidor
    renderTablaProductos(items, 'tbody-detalle', actualizarCantidad, eliminarItem);

    // Sincronizar hidden inputs con el estado inicial
    sincronizarHiddens(items, 'form-cambio-aceite', 'hidden-producto');

    // Búsqueda de productos con debounce
    initBusquedaProductos({
        inputId:      'buscar-producto',
        resultadosId: 'resultados-busqueda',
        onAgregar:    onAgregarProducto,
    });

    // Buscador de placa para cliente frecuente
    initBuscadorPlaca();

    // Preview de foto con foto actual (currentId = 'foto-current')
    initFotoPreview('foto', 'foto-preview', 'foto-current');

    // ── Método de pago ──
    const options          = document.querySelectorAll('.metodo-pago-option');
    const radios           = document.querySelectorAll('.metodo-pago-radio');
    const bloqueMixto      = document.getElementById('bloque-mixto');
    const inputTotal       = document.getElementById('total');
    const inputAncla       = document.getElementById('precio');
    const toggleDescManual = document.getElementById('toggle-descuento-manual');

    const { actualizarUI } = initMetodoPago({
        options,
        radios,
        bloqueMixto,
        inputTotal,
        inputAncla,
        toggleDescManual,
    });

    // Restaurar método de pago guardado desde el servidor
    radios.forEach(r => {
        if (r.value === confirmarMetodoPago) r.checked = true;
    });

    // Restaurar montos mixtos guardados
    if (confirmarMontos.efectivo != null) {
        const el = document.getElementById('monto_efectivo');
        if (el) el.value = parseFloat(confirmarMontos.efectivo).toFixed(2);
    }
    if (confirmarMontos.yape != null) {
        const el = document.getElementById('monto_yape');
        if (el) el.value = parseFloat(confirmarMontos.yape).toFixed(2);
    }
    if (confirmarMontos.izipay != null) {
        const el = document.getElementById('monto_izipay');
        if (el) el.value = parseFloat(confirmarMontos.izipay).toFixed(2);
    }

    // Actualizar UI de método de pago con el valor restaurado
    actualizarUI();

    // Validación de mixto al cambiar montos
    ['monto_efectivo', 'monto_yape', 'monto_izipay'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', () =>
                validarMixto(inputTotal, ['monto_efectivo', 'monto_yape', 'monto_izipay'], 'alerta-mixto')
            );
        }
    });

    // Re-validar mixto cuando el total cambia (evento emitido por initMetodoPago)
    if (inputTotal) {
        inputTotal.addEventListener('mixto:revalidar', () =>
            validarMixto(inputTotal, ['monto_efectivo', 'monto_yape', 'monto_izipay'], 'alerta-mixto')
        );
    }

    // ── Toggle descuento por porcentaje ──
    const toggleDescuento = document.getElementById('toggle-descuento');
    if (toggleDescuento) {
        toggleDescuento.addEventListener('change', function () {
            document.getElementById('campo-porcentaje')?.classList.toggle('hidden', !this.checked);
            if (this.checked) {
                if (inputTotal) {
                    inputTotal.readOnly = false;
                    inputTotal.classList.remove('bg-slate-50', 'dark:bg-slate-800/50');
                }
            } else if (!toggleDescManual?.checked) {
                if (inputTotal) {
                    inputTotal.readOnly = true;
                    inputTotal.classList.add('bg-slate-50', 'dark:bg-slate-800/50');
                }
            }
            recalcularTotales(items, 'precio', 'total', 'toggle-descuento', 'porcentaje');
            sincronizarHiddens(items, 'form-cambio-aceite', 'hidden-producto');
        });
    }

    // ── Validación de porcentaje ──
    const porcentajeInput = document.getElementById('porcentaje');
    if (porcentajeInput) {
        porcentajeInput.addEventListener('input', function () {
            const errorEl = document.getElementById('error-porcentaje');
            if (parseFloat(this.value) > 100) {
                this.value = 100;
                if (errorEl) errorEl.classList.remove('hidden');
            } else {
                if (errorEl) errorEl.classList.add('hidden');
            }
            recalcularTotales(items, 'precio', 'total', 'toggle-descuento', 'porcentaje');
            sincronizarHiddens(items, 'form-cambio-aceite', 'hidden-producto');
        });
    }

    // NO recalcular totales en la carga inicial — preservar precio/total del servidor
});
