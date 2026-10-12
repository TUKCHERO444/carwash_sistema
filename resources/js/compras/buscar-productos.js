// Feature: compras-ingreso-mercaderia
// Module: compras/buscar-productos.js
// Autocomplete de productos para el formulario de compra.

/**
 * Inicializa la búsqueda dinámica de productos en una línea del detalle.
 * @param {Object} config
 * @param {string} config.inputId - ID del input de búsqueda
 * @param {string} config.resultadosId - ID del contenedor de resultados
 * @param {string} config.hiddenInputId - ID del input hidden donde se guarda el ID seleccionado
 * @param {Function} config.onSelect - Callback cuando se selecciona un producto
 */
export function initBusquedaProductos({ inputId, resultadosId, hiddenInputId, onSelect }) {
    const inputBuscar = document.getElementById(inputId);
    const resultadosDiv = document.getElementById(resultadosId);
    const hiddenInput = document.getElementById(hiddenInputId);

    if (!inputBuscar || !resultadosDiv || !hiddenInput) return;

    // Nombre de la función global saneado: hiddenInputId puede contener guiones
    // (p.ej. "producto-id-0"), que son inválidos en notación de punto y se
    // interpretarían como resta en el onclick inline.
    const fnName = `seleccionarProducto_${hiddenInputId.replace(/[^a-zA-Z0-9_]/g, '_')}`;

    let debounceTimer;

    inputBuscar.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = this.value.trim();

        if (q.length < 2) {
            ocultarResultados();
            return;
        }

        debounceTimer = setTimeout(() => buscarProductos(q), 300);
    });

    // Cerrar resultados al hacer clic fuera
    document.addEventListener('click', function (e) {
        if (!inputBuscar.contains(e.target) && !resultadosDiv.contains(e.target)) {
            ocultarResultados();
        }
    });

    async function buscarProductos(q) {
        try {
            const res = await fetch(`/compras/buscar-productos?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            mostrarResultados(data);
        } catch {
            ocultarResultados();
        }
    }

    function mostrarResultados(productos) {
        if (!productos.length) {
            resultadosDiv.innerHTML = '<div class="px-4 py-3 text-sm text-secondary">No se encontraron productos</div>';
            resultadosDiv.classList.remove('hidden');
            return;
        }

        resultadosDiv.innerHTML = productos.map(p => `
            <div class="px-4 py-3 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                 onclick="window['${fnName}'](${p.id}, '${p.nombre.replace(/'/g, "\\'")}', ${p.precio_compra}, ${p.stock})">
                <div class="text-sm text-primary font-medium">${p.nombre}</div>
                <div class="text-xs text-secondary">Stock: ${p.stock} | Costo: S/ ${parseFloat(p.precio_compra).toFixed(2)}</div>
            </div>
        `).join('');
        resultadosDiv.classList.remove('hidden');
    }

    function ocultarResultados() {
        resultadosDiv.classList.add('hidden');
    }

    // Función global para seleccionar un producto
    window[fnName] = function (id, nombre, precioCompra, stock) {
        hiddenInput.value = id;
        inputBuscar.value = nombre;
        ocultarResultados();
        if (onSelect) onSelect(id, nombre, precioCompra, stock);
    };
}
