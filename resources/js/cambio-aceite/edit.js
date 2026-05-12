/**
 * resources/js/cambio-aceite/edit.js
 *
 * Lógica específica de la vista cambio-aceite/edit.
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
const productosExistentes    = window.productosExistentes    ?? [];
const cambioAceiteMetodoPago = window.cambioAceiteMetodoPago ?? 'efectivo';
const cambioAceiteMontos     = window.cambioAceiteMontos     ?? {};

// ── Estado local ──
// Los datos vienen aplanados desde el controlador (sin pivot)
let items = productosExistentes.map(p => ({
    producto_id: p.id,
    nombre:      p.nombre,
    cantidad:    p.cantidad ?? 1,
    precio:      parseFloat(p.precio),
    total:       parseFloat(p.total ?? p.precio),
}));

// ── Callbacks de tabla ──
function actualizarCantidad(idx, val) {
    const cantidad = Math.max(1, parseInt(val) || 1);
    items[idx].cantidad = cantidad;
    items[idx].total    = +(cantidad * items[idx].precio).toFixed(2);
    renderTablaProductos(items, 'tbody-detalle', actualizarCantidad, eliminarItem);
    recalcularTotales(items, 'precio', 'total', 'toggle-descuento', 'porcentaje');
    sincronizarHiddens(items, 'form-cambio-aceite', 'hidden-producto');
}

function eliminarItem(idx) {
    items.splice(idx, 1);
    renderTablaProductos(items, 'tbody-detalle', actualizarCantidad, eliminarItem);
    recalcularTotales(items, 'precio', 'total', 'toggle-descuento', 'porcentaje');
    sincronizarHiddens(items, 'form-cambio-aceite', 'hidden-producto');
}

// ── Callback para agregar un producto desde la búsqueda ──
function onAgregarProducto(producto) {
    const existente = items.find(i => i.producto_id === producto.id);
    if (existente) {
        existente.cantidad++;
        existente.total = +(existente.cantidad * existente.precio).toFixed(2);
    } else {
        items.push({
            producto_id: producto.id,
            nombre:      producto.nombre,
            cantidad:    1,
            precio:      +parseFloat(producto.precio_venta).toFixed(2),
            total:       +parseFloat(producto.precio_venta).toFixed(2),
        });
    }
    renderTablaProductos(items, 'tbody-detalle', actualizarCantidad, eliminarItem);
    recalcularTotales(items, 'precio', 'total', 'toggle-descuento', 'porcentaje');
    sincronizarHiddens(items, 'form-cambio-aceite', 'hidden-producto');

    const inputBuscar = document.getElementById('buscar-producto');
    if (inputBuscar) inputBuscar.value = '';
}

// ── Inicialización al cargar el DOM ──
document.addEventListener('DOMContentLoaded', () => {
    // Renderizar tabla con productos pre-cargados
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

    // Método de pago
    const options          = document.querySelectorAll('.metodo-pago-option');
    const radios           = document.querySelectorAll('.metodo-pago-radio');
    const bloqueMixto      = document.getElementById('bloque-mixto');
    const inputTotal       = document.getElementById('total');
    const inputAncla       = document.getElementById('precio');
    const toggleDescManual = document.getElementById('toggle-descuento-manual');

    const { actualizarUI: actualizarUIMetodoPago } = initMetodoPago({
        options,
        radios,
        bloqueMixto,
        inputTotal,
        inputAncla,
        toggleDescManual,
    });

    // Restaurar método de pago guardado
    radios.forEach(r => {
        if (r.value === cambioAceiteMetodoPago) r.checked = true;
    });

    // Restaurar montos mixtos guardados
    if (cambioAceiteMontos.efectivo != null) {
        const el = document.getElementById('monto_efectivo');
        if (el) el.value = parseFloat(cambioAceiteMontos.efectivo).toFixed(2);
    }
    if (cambioAceiteMontos.yape != null) {
        const el = document.getElementById('monto_yape');
        if (el) el.value = parseFloat(cambioAceiteMontos.yape).toFixed(2);
    }
    if (cambioAceiteMontos.izipay != null) {
        const el = document.getElementById('monto_izipay');
        if (el) el.value = parseFloat(cambioAceiteMontos.izipay).toFixed(2);
    }

    // Actualizar UI de método de pago con el valor restaurado
    actualizarUIMetodoPago();

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

    // Toggle descuento por porcentaje
    const toggleDescuento = document.getElementById('toggle-descuento');
    if (toggleDescuento) {
        toggleDescuento.addEventListener('change', function () {
            document.getElementById('campo-porcentaje')?.classList.toggle('hidden', !this.checked);
            if (this.checked) {
                if (inputTotal) {
                    inputTotal.readOnly = false;
                    inputTotal.classList.remove('bg-gray-50');
                }
            } else if (!toggleDescManual?.checked) {
                if (inputTotal) {
                    inputTotal.readOnly = true;
                    inputTotal.classList.add('bg-gray-50');
                }
            }
            recalcularTotales(items, 'precio', 'total', 'toggle-descuento', 'porcentaje');
            sincronizarHiddens(items, 'form-cambio-aceite', 'hidden-producto');
        });
    }

    // Validación de porcentaje
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

    // No recalcular totales en edición — preservar precio/total guardados
});
