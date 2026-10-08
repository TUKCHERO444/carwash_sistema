// Feature: frecuencia-lavados-cliente
// Module: clientes/index.js
// Modal de resumen de lavados confirmados por cliente (solo lectura).

// ── Pure rendering functions (exported for testing) ──

/**
 * Renders the vehicle list items for the summary modal.
 * @param {Array<{placa: string, lavados: number}>} vehiculos
 * @returns {string} HTML string for <ul> content.
 */
export function renderVehiculosHTML(vehiculos) {
    if (!vehiculos.length) {
        return '<li>Sin vehículos registrados.</li>';
    }
    return vehiculos
        .map((v) => `<li>${v.placa} — ${v.lavados} ${v.lavados === 1 ? 'lavado' : 'lavados'}</li>`)
        .join('');
}

// ── Module initialisation ──

// Guard para entornos sin DOM (tests de Node importan este módulo para
// ejercitar las funciones puras): en el navegador siempre está definido.
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        const modalId = 'modal-lavados-cliente';
        const elCargando = document.getElementById('lavados-cargando');
        const elError = document.getElementById('lavados-error');
        const elContenido = document.getElementById('lavados-contenido');

        function mostrarEstado(estado) {
            elCargando.classList.toggle('hidden', estado !== 'cargando');
            elError.classList.toggle('hidden', estado !== 'error');
            elContenido.classList.toggle('hidden', estado !== 'listo');
        }

        function rellenar(datos) {
            document.getElementById('lavados-nombre').textContent = datos.cliente.nombre_completo ?? '—';
            document.getElementById('lavados-dni').textContent = datos.cliente.dni ?? 'No registrado';
            document.getElementById('lavados-telefono').textContent = datos.cliente.telefono ?? 'No registrado';
            document.getElementById('lavados-total').textContent = datos.resumen.total_lavados;

            const ultimo = datos.resumen.ultimo_lavado;
            document.getElementById('lavados-ultimo').textContent = ultimo
                ? `${ultimo.fecha}${ultimo.placa ? ` · ${ultimo.placa}` : ''}${ultimo.vehiculo ? ` (${ultimo.vehiculo})` : ''}`
                : 'Sin lavados confirmados';

            document.getElementById('lavados-vehiculos').innerHTML = renderVehiculosHTML(datos.resumen.vehiculos ?? []);
        }

        document.querySelectorAll('[data-cliente-lavados]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                mostrarEstado('cargando');
                window.openModal(modalId);

                try {
                    const res = await fetch(btn.dataset.url, { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    const datos = await res.json();
                    if (!datos.success) throw new Error('Respuesta inválida');
                    rellenar(datos);
                    mostrarEstado('listo');
                } catch (e) {
                    mostrarEstado('error');
                }
            });
        });
    });
}
