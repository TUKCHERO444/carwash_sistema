/**
 * resources/js/lavados/edit.js
 *
 * Lógica específica de la vista lavados/edit.
 * Importa funciones compartidas desde ./shared.js.
 * Lee datos iniciales desde variables globales expuestas por la vista Blade.
 */

import {
    initBusquedaServicios,
    renderTablaServicios,
    sincronizarHiddens,
    initFotoPreview,
    initMetodoPago,
    validarMixto,
    recalcularTotales,
} from './shared.js';
import { initBuscadorPlaca } from '../buscador-placa.js';

// ── Datos iniciales desde la vista Blade ──
const serviciosExistentes = window.serviciosExistentes ?? [];
const lavadoMetodoPago   = window.lavadoMetodoPago   ?? 'efectivo';
const lavadoMontos       = window.lavadoMontos        ?? {};

// ── Estado local ──
let items = serviciosExistentes.map(s => ({
    servicio_id: s.id,
    nombre:      s.nombre,
    precio:      +parseFloat(s.precio).toFixed(2),
}));

// ── Callback para agregar un servicio desde la búsqueda ──
function onAgregarServicio(servicio) {
    if (items.find(i => i.servicio_id === servicio.id)) return; // sin duplicados

    items.push({
        servicio_id: servicio.id,
        nombre:      servicio.nombre,
        precio:      +parseFloat(servicio.precio).toFixed(2),
    });

    renderTablaServicios(items, 'tbody-servicios', eliminarItem);
    recalcularTotales(items, 'vehiculo_id', 'precio', 'total', 'toggle-descuento', 'porcentaje');
    sincronizarHiddens(items, 'form-lavado');

    const inputBuscar = document.getElementById('buscar-servicio');
    if (inputBuscar) inputBuscar.value = '';
}

// ── Eliminar item de la tabla ──
function eliminarItem(idx) {
    items.splice(idx, 1);
    renderTablaServicios(items, 'tbody-servicios', eliminarItem);
    recalcularTotales(items, 'vehiculo_id', 'precio', 'total', 'toggle-descuento', 'porcentaje');
    sincronizarHiddens(items, 'form-lavado');
}

// ── Inicialización al cargar el DOM ──
document.addEventListener('DOMContentLoaded', () => {
    // Renderizar tabla con servicios pre-cargados
    renderTablaServicios(items, 'tbody-servicios', eliminarItem);

    // Sincronizar hidden inputs con el estado inicial
    sincronizarHiddens(items, 'form-lavado');

    // Búsqueda de servicios con debounce
    initBusquedaServicios({
        inputId:      'buscar-servicio',
        resultadosId: 'resultados-busqueda',
        onAgregar:    onAgregarServicio,
    });

    // Buscador de placa para cliente frecuente
    initBuscadorPlaca();

    // Preview de foto con foto actual (currentId = 'foto-current')
    initFotoPreview('foto', 'foto-preview', 'foto-current');

    // Método de pago
    const options        = document.querySelectorAll('.metodo-pago-option');
    const radios         = document.querySelectorAll('.metodo-pago-radio');
    const bloqueMixto    = document.getElementById('bloque-mixto');
    const inputTotal     = document.getElementById('total');
    const inputAncla     = document.getElementById('precio');
    const toggleDescManual = document.getElementById('toggle-descuento-manual');

    const { actualizarUI } = initMetodoPago({
        options,
        radios,
        bloqueMixto,
        inputTotal,
        inputAncla,
        toggleDescManual,
    });

    // Restaurar método de pago guardado
    radios.forEach(r => {
        if (r.value === lavadoMetodoPago) r.checked = true;
    });

    // Restaurar montos mixtos guardados
    if (lavadoMontos.efectivo != null) {
        const el = document.getElementById('monto_efectivo');
        if (el) el.value = parseFloat(lavadoMontos.efectivo).toFixed(2);
    }
    if (lavadoMontos.yape != null) {
        const el = document.getElementById('monto_yape');
        if (el) el.value = parseFloat(lavadoMontos.yape).toFixed(2);
    }
    if (lavadoMontos.izipay != null) {
        const el = document.getElementById('monto_izipay');
        if (el) el.value = parseFloat(lavadoMontos.izipay).toFixed(2);
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

    // Vehículo cambia → recalcular
    const vehiculoSelect = document.getElementById('vehiculo_id');
    if (vehiculoSelect) {
        vehiculoSelect.addEventListener('change', () => {
            recalcularTotales(items, 'vehiculo_id', 'precio', 'total', 'toggle-descuento', 'porcentaje');
            sincronizarHiddens(items, 'form-lavado');
        });
    }

    // Toggle descuento por porcentaje
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
            recalcularTotales(items, 'vehiculo_id', 'precio', 'total', 'toggle-descuento', 'porcentaje');
            sincronizarHiddens(items, 'form-lavado');
        });
    }

    // Porcentaje de descuento
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
            recalcularTotales(items, 'vehiculo_id', 'precio', 'total', 'toggle-descuento', 'porcentaje');
            sincronizarHiddens(items, 'form-lavado');
        });
    }

    // No recalcular totales en edición — preservar precio/total guardados
});
