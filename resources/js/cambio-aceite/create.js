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
import Swal from 'sweetalert2';

// ── Estado local ──
let items = []; // [{producto_id, nombre, cantidad, precio, total}]

// ── Callbacks de tabla ──
function actualizarCantidad(idx, val) {
    const cantidadIngresada = parseInt(val) || 1;
    const stock = items[idx].stock;

    if (stock != null && cantidadIngresada > stock) {
        Swal.fire({
            icon: 'warning',
            title: 'Stock insuficiente',
            html: `La cantidad ingresada (<strong>${cantidadIngresada}</strong>) excede el stock disponible de <strong>${items[idx].nombre}</strong>.<br>Stock disponible: <strong>${stock}</strong>`,
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'Entendido',
        }).then(() => {
            const input = document.querySelector(`#tbody-detalle tr:nth-child(${idx + 1}) input[type="number"]`);
            if (input) {
                input.value = stock;
                input.focus();
            }
        });
        items[idx].cantidad = stock;
    } else {
        items[idx].cantidad = Math.max(1, cantidadIngresada);
    }

    items[idx].total = +(items[idx].cantidad * items[idx].precio).toFixed(2);
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
    const stock = parseInt(producto.stock) || 0;
    const existente = items.find(i => i.producto_id === producto.id);
    if (existente) {
        const nuevaCantidad = existente.cantidad + 1;
        if (existente.stock != null && nuevaCantidad > existente.stock) {
            Swal.fire({
                icon: 'warning',
                title: 'Stock insuficiente',
                html: `No hay suficiente stock de <strong>${existente.nombre}</strong>.<br>Stock disponible: <strong>${existente.stock}</strong>`,
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'Entendido',
            });
            return;
        }
        existente.cantidad = nuevaCantidad;
        existente.total = +(existente.cantidad * existente.precio).toFixed(2);
    } else {
        items.push({
            producto_id: producto.id,
            nombre:      producto.nombre,
            cantidad:    1,
            precio:      +parseFloat(producto.precio_venta).toFixed(2),
            total:       +parseFloat(producto.precio_venta).toFixed(2),
            stock:       stock,
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
