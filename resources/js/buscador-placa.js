/**
 * resources/js/buscador-placa.js
 *
 * Lógica para buscar el cliente por placa y mostrar un resumen de sus servicios.
 */

export function initBuscadorPlaca() {
    const inputPlaca = document.getElementById('placa');
    const container = document.getElementById('cliente-summary-container');
    const inputNombre = document.getElementById('nombre');
    const inputTelefono = document.getElementById('telefono');
    
    if (!inputPlaca || !container) return;

    let debounceTimer;

    inputPlaca.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const placa = this.value.trim();
        
        if (placa.length < 3) {
            container.classList.add('hidden');
            container.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => buscarCliente(placa), 500);
    });

    async function buscarCliente(placa) {
        try {
            const response = await fetch(`/clientes/buscar-por-placa?placa=${encodeURIComponent(placa)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                container.classList.add('hidden');
                return;
            }

            const data = await response.json();
            
            if (data.success) {
                const cliente = data.cliente;
                
                // Autocompletar nombre y teléfono si están vacíos
                if (inputNombre && !inputNombre.value && cliente.nombre) {
                    inputNombre.value = cliente.nombre;
                }
                if (inputTelefono && !inputTelefono.value && cliente.telefono) {
                    inputTelefono.value = cliente.telefono;
                }

                // Renderizar tabla de resumen si tiene servicios
                const totalServicios = cliente.ingresos_count + cliente.cambios_aceite_count;
                
                if (totalServicios > 0) {
                    container.innerHTML = `
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h3 class="text-sm font-semibold text-blue-800 mb-2 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Cliente Frecuente
                            </h3>
                            <div class="text-sm text-blue-700 grid grid-cols-2 gap-2">
                                <div><strong>Placa:</strong> ${cliente.placa}</div>
                                <div><strong>Nombre:</strong> ${cliente.nombre || 'N/A'}</div>
                                <div><strong>Teléfono:</strong> ${cliente.telefono || 'N/A'}</div>
                                <div><strong>Ingresos:</strong> ${cliente.ingresos_count}</div>
                                <div><strong>Cambios Aceite:</strong> ${cliente.cambios_aceite_count}</div>
                            </div>
                        </div>
                    `;
                    container.classList.remove('hidden');
                } else {
                    container.classList.add('hidden');
                }
            } else {
                container.classList.add('hidden');
            }
        } catch (error) {
            console.error('Error buscando placa:', error);
            container.classList.add('hidden');
        }
    }
}
