/**
 * resources/js/cambio-aceite/create.js
 *
 * Lógica específica de la vista cambio-aceite/create.
 * Importa funciones compartidas desde ./shared.js.
 */

import {
    initBusquedaProductos,
    renderTablaProductos,
    recalcularTotales,
    sincronizarHiddens,
    initFotoPreview,
} from './shared.js';
import { initBuscadorPlaca } from '../buscador-placa.js';
import { Validation } from '../utils/validation.js';

// ── Estado local ──
let items = []; // [{producto_id, nombre, cantidad, precio, total}]

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
    // Búsqueda de productos con debounce
    initBusquedaProductos({
        inputId:      'buscar-producto',
        resultadosId: 'resultados-busqueda',
        onAgregar:    onAgregarProducto,
    });

    // Buscador de placa para cliente frecuente
    initBuscadorPlaca();

    // Preview de foto (sin currentId — no hay foto previa en create)
    initFotoPreview('foto', 'foto-preview');

    // Toggle descuento por porcentaje
    const toggleDescuento = document.getElementById('toggle-descuento');
    if (toggleDescuento) {
        toggleDescuento.addEventListener('change', function () {
            document.getElementById('campo-porcentaje')?.classList.toggle('hidden', !this.checked);
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
                errorEl?.classList.remove('hidden');
            } else {
                errorEl?.classList.add('hidden');
            }
            recalcularTotales(items, 'precio', 'total', 'toggle-descuento', 'porcentaje');
            sincronizarHiddens(items, 'form-cambio-aceite', 'hidden-producto');
        });
    }

    // Renderizar estado inicial
    renderTablaProductos(items, 'tbody-detalle', actualizarCantidad, eliminarItem);
    recalcularTotales(items, 'precio', 'total', 'toggle-descuento', 'porcentaje');

    // Validación del formulario
    const form = document.getElementById('form-cambio-aceite');
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!Validation.validate(form)) {
                e.preventDefault();
            }
        });
    }
});
