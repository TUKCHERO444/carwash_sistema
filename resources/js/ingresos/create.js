/**
 * resources/js/ingresos/create.js
 *
 * Lógica específica de la vista ingresos/create.
 * Importa funciones compartidas desde ./shared.js.
 */

import {
    initBusquedaServicios,
    renderTablaServicios,
    sincronizarHiddens,
    initFotoPreview,
    calcularPrecio,
} from './shared.js';
import { initBuscadorPlaca } from '../buscador-placa.js';
import { Validation } from '../utils/validation.js';

// ── Estado local ──
let items = []; // [{servicio_id, nombre, precio}]

// ── Indicador visual de precio estimado ──
// (no usa recalcularTotales completo — no hay descuento ni total en create)
function actualizarPrecioDisplay() {
    const vehiculoSelect = document.getElementById('vehiculo_id');
    const precioVehiculo = parseFloat(
        vehiculoSelect?.options[vehiculoSelect.selectedIndex]?.dataset.precio ?? 0
    );
    const precio = calcularPrecio(precioVehiculo, items);
    const display = document.getElementById('precio-display');
    if (display) display.value = precio.toFixed(2);

    sincronizarHiddens(items, 'form-ingreso');
}

// ── Callback para agregar un servicio desde la búsqueda ──
function onAgregarServicio(servicio) {
    if (items.find(i => i.servicio_id === servicio.id)) return; // sin duplicados

    items.push({
        servicio_id: servicio.id,
        nombre:      servicio.nombre,
        precio:      +parseFloat(servicio.precio).toFixed(2),
    });

    renderTablaServicios(items, 'tbody-servicios', eliminarItem);
    actualizarPrecioDisplay();

    const inputBuscar = document.getElementById('buscar-servicio');
    if (inputBuscar) inputBuscar.value = '';
}

// ── Eliminar item de la tabla ──
function eliminarItem(idx) {
    items.splice(idx, 1);
    renderTablaServicios(items, 'tbody-servicios', eliminarItem);
    actualizarPrecioDisplay();
}

// ── Inicialización al cargar el DOM ──
document.addEventListener('DOMContentLoaded', () => {
    // Búsqueda de servicios con debounce
    initBusquedaServicios({
        inputId:      'buscar-servicio',
        resultadosId: 'resultados-busqueda',
        onAgregar:    onAgregarServicio,
    });

    // Buscador de placa para cliente frecuente
    initBuscadorPlaca();

    // Preview de foto (sin currentId — no hay foto previa en create)
    initFotoPreview('foto', 'foto-preview');

    // Actualizar precio al cambiar vehículo
    const vehiculoSelect = document.getElementById('vehiculo_id');
    if (vehiculoSelect) {
        vehiculoSelect.addEventListener('change', actualizarPrecioDisplay);
    }

    // Renderizar tabla y precio inicial
    renderTablaServicios(items, 'tbody-servicios', eliminarItem);
    actualizarPrecioDisplay();

    // Validación del formulario
    const form = document.getElementById('form-ingreso');
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!Validation.validate(form)) {
                e.preventDefault();
            }
        });
    }
});
