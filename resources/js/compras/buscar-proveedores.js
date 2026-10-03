// Feature: compras-ingreso-mercaderia
// Module: compras/buscar-proveedores.js
// Autocomplete de proveedores para el formulario de compra.

/**
 * Inicializa la búsqueda dinámica de proveedores.
 * @param {Object} config
 * @param {string} config.inputId - ID del input de búsqueda
 * @param {string} config.resultadosId - ID del contenedor de resultados
 * @param {string} config.hiddenInputId - ID del input hidden donde se guarda el ID seleccionado
 */
export function initBusquedaProveedores({ inputId, resultadosId, hiddenInputId }) {
    const inputBuscar = document.getElementById(inputId);
    const resultadosDiv = document.getElementById(resultadosId);
    const hiddenInput = document.getElementById(hiddenInputId);

    if (!inputBuscar || !resultadosDiv || !hiddenInput) return;

    let debounceTimer;

    inputBuscar.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = this.value.trim();

        if (q.length < 2) {
            ocultarResultados();
            return;
        }

        debounceTimer = setTimeout(() => buscarProveedores(q), 300);
    });

    // Cerrar resultados al hacer clic fuera
    document.addEventListener('click', function (e) {
        if (!inputBuscar.contains(e.target) && !resultadosDiv.contains(e.target)) {
            ocultarResultados();
        }
    });

    async function buscarProveedores(q) {
        try {
            const res = await fetch(`/compras/buscar-proveedores?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            mostrarResultados(data);
        } catch {
            ocultarResultados();
        }
    }

    function mostrarResultados(proveedores) {
        if (!proveedores.length) {
            resultadosDiv.innerHTML = '<div class="px-4 py-3 text-sm text-secondary">No se encontraron proveedores</div>';
            resultadosDiv.classList.remove('hidden');
            return;
        }

        resultadosDiv.innerHTML = proveedores.map(p => `
            <div class="px-4 py-3 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                 onclick="window.seleccionarProveedor(${p.id}, '${p.razon_social.replace(/'/g, "\\'")}', '${p.ruc}')">
                <div class="text-sm text-primary font-medium">${p.razon_social}</div>
                <div class="text-xs text-secondary">RUC: ${p.ruc}</div>
            </div>
        `).join('');
        resultadosDiv.classList.remove('hidden');
    }

    function ocultarResultados() {
        resultadosDiv.classList.add('hidden');
    }

    // Función global para seleccionar un proveedor
    window.seleccionarProveedor = function (id, razonSocial, ruc) {
        hiddenInput.value = id;
        inputBuscar.value = `${razonSocial} (${ruc})`;
        ocultarResultados();
    };
}
