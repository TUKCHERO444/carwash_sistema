/**
 * resources/js/buscador-placa.js
 *
 * Búsqueda de placa en los tickets (lavado / cambio de aceite) mediante botones de opción:
 * 1) "Buscar cliente" (local) → jala datos del vehículo y cliente verificados.
 * 2) "Consultar datos (API)" (solo si no existe localmente) → display-only, nunca bloquea.
 * 3) "Continuar registro" → colapsa el grupo; el alta la hace el upsert actual al guardar.
 */

/** Normaliza la placa: mayúsculas y sin espacios (igual que Automotor::normalizarPlaca). */
export function normalizarPlaca(placa) {
    return String(placa ?? '').toUpperCase().replaceAll(' ', '');
}

/** Valida con la misma regla que el backend: /^[A-Z0-9-]{6,7}$/i. */
export function placaValida(placa) {
    return /^[A-Z0-9-]{6,7}$/i.test(normalizarPlaca(placa));
}

/**
 * Máquina de estados de los botones de opción.
 *
 * @param {boolean} placaValida  Formato de placa correcto.
 * @param {string|null} busqueda 'encontrada' | 'noEncontrada' | null (sin buscar).
 * @param {boolean} apiConsultada Se intentó la consulta a la API.
 * @returns {{buscar: boolean, api: boolean, continuar: boolean}}
 */
export function estadoBotones(placaValida, busqueda, apiConsultada) {
    const buscar = placaValida === true;
    const api = buscar && busqueda === 'noEncontrada';
    const continuar = buscar && (busqueda === 'encontrada' || (busqueda === 'noEncontrada' && apiConsultada === true));

    return { buscar, api, continuar };
}

/** Escapa valores para inyectarlos con innerHTML. */
export function esc(valor) {
    return String(valor ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

/** Filas del resumen local (cliente + automotor) sin valores undefined. */
export function datosResumenLocal(cliente = {}, automotor = {}) {
    const fila = (clave, valor) => ({
        clave,
        valor: valor === null || valor === undefined || valor === '' ? '—' : String(valor),
    });

    return [
        fila('Placa', automotor.placa ?? cliente.placa),
        fila('Nombre', cliente.nombre_completo || cliente.nombre),
        fila('DNI', cliente.dni),
        fila('Teléfono', cliente.telefono),
        fila('Lavados', cliente.lavados_count),
        fila('Cambios de aceite', cliente.cambios_aceite_count),
        fila('Marca', automotor.marca),
        fila('Modelo', automotor.modelo),
        fila('Color', automotor.color),
        fila('Motor', automotor.motor),
    ];
}

/** Filas del resumen de la API (display-only, solo campos con dato). */
export function datosResumenApi(data = {}) {
    const filas = [];
    const agregar = (clave, valor) => {
        if (valor !== null && valor !== undefined && valor !== '') {
            filas.push({ clave, valor: String(valor) });
        }
    };

    agregar('Placa', data.placa);
    agregar('Marca', data.marca);
    agregar('Modelo', data.modelo);
    agregar('Serie', data.serie);
    agregar('Color', data.color);
    agregar('Motor', data.motor);
    agregar('VIN', data.vin);

    return filas;
}

function renderFilas(filas) {
    return filas
        .map(({ clave, valor }) => `<div><strong>${esc(clave)}:</strong> ${esc(valor)}</div>`)
        .join('');
}

function resumenHtml(titulo, filas, nota = '') {
    return `
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-sm font-semibold text-blue-800 mb-2 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                ${esc(titulo)}
            </h3>
            <div class="text-sm text-blue-700 grid grid-cols-2 gap-2">${renderFilas(filas)}</div>
            ${nota ? `<p class="mt-2 text-xs text-blue-600">${esc(nota)}</p>` : ''}
        </div>
    `;
}

const CLASES_ESTADO = {
    ok: 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400',
    warn: 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-400',
    error: 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
};

export function initBuscadorPlaca() {
    const inputPlaca = document.getElementById('placa');
    const container = document.getElementById('cliente-summary-container');
    const opciones = document.getElementById('placa-opciones');
    const estado = document.getElementById('placa-estado');
    const btnBuscar = document.getElementById('btn-buscar-cliente');
    const btnApi = document.getElementById('btn-consultar-placa-api');
    const btnContinuar = document.getElementById('btn-continuar-registro');
    const inputNombre = document.getElementById('nombre');
    const inputDni = document.getElementById('dni');
    const inputTelefono = document.getElementById('telefono');

    if (!inputPlaca || !container || !opciones || !btnBuscar || !btnApi || !btnContinuar) return;

    let busqueda = null;
    let apiConsultada = false;

    function mostrarEstado(tipo, texto) {
        if (!estado) return;
        estado.innerHTML = `<span class="${CLASES_ESTADO[tipo]}">${esc(texto)}</span>`;
        estado.classList.remove('hidden');
    }

    function ocultarEstado() {
        if (!estado) return;
        estado.innerHTML = '';
        estado.classList.add('hidden');
    }

    function pintarBotones() {
        const valido = placaValida(inputPlaca.value);
        const { buscar, api, continuar } = estadoBotones(valido, busqueda, apiConsultada);

        btnBuscar.disabled = !buscar;
        btnApi.disabled = !api;
        btnContinuar.disabled = !continuar;
        opciones.classList.toggle('hidden', !valido);
    }

    function reiniciarBusqueda() {
        busqueda = null;
        apiConsultada = false;
        container.innerHTML = '';
        container.classList.add('hidden');
        ocultarEstado();
    }

    function autofillSoloVacios(datos) {
        if (inputNombre && !inputNombre.value.trim() && datos.nombre) {
            inputNombre.value = datos.nombre;
        }
        if (inputDni && !inputDni.value.trim() && datos.dni) {
            inputDni.value = datos.dni;
        }
        if (inputTelefono && !inputTelefono.value.trim() && datos.telefono) {
            inputTelefono.value = datos.telefono;
        }
    }

    inputPlaca.addEventListener('input', () => {
        reiniciarBusqueda();
        pintarBotones();
    });

    btnBuscar.addEventListener('click', async () => {
        const placa = normalizarPlaca(inputPlaca.value);
        btnBuscar.disabled = true;

        try {
            const response = await fetch(`/clientes/buscar-por-placa?placa=${encodeURIComponent(placa)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });

            const data = response.ok ? await response.json() : { success: false };

            if (data.success) {
                busqueda = 'encontrada';
                inputPlaca.value = data.cliente.placa || placa;
                autofillSoloVacios(data.cliente);
                mostrarEstado('ok', 'Vehículo verificado');
                container.innerHTML = resumenHtml('Vehículo y cliente verificados', datosResumenLocal(data.cliente, data.automotor));
                container.classList.remove('hidden');
            } else {
                busqueda = 'noEncontrada';
                container.innerHTML = '';
                container.classList.add('hidden');
                mostrarEstado('warn', 'No registrado — complete los datos para dar de alta');
            }
        } catch (error) {
            busqueda = 'noEncontrada';
            mostrarEstado('error', 'No se pudo consultar — verifique la placa');
            container.classList.add('hidden');
        }

        pintarBotones();
    });

    btnApi.addEventListener('click', async () => {
        const placa = normalizarPlaca(inputPlaca.value);
        btnApi.disabled = true;

        try {
            const response = await fetch(`/consulta-placa-api?placa=${encodeURIComponent(placa)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });

            const data = response.ok ? await response.json() : { success: false, data: null };

            if (data.success && data.data && datosResumenApi(data.data).length > 1) {
                container.innerHTML = resumenHtml(
                    'Datos de referencia (API)',
                    datosResumenApi(data.data),
                    'Informativo: no se guarda hasta confirmar el ticket.'
                );
                container.classList.remove('hidden');
            } else {
                container.innerHTML = resumenHtml('Datos de referencia (API)', [{ clave: 'Placa', valor: placa }], 'La API no devolvió datos adicionales.');
                container.classList.remove('hidden');
            }
        } catch (error) {
            container.innerHTML = resumenHtml('Datos de referencia (API)', [{ clave: 'Placa', valor: placa }], 'API no disponible — continúe con el registro normal.');
            container.classList.remove('hidden');
        }

        apiConsultada = true;
        pintarBotones();
    });

    btnContinuar.addEventListener('click', () => {
        opciones.classList.add('hidden');
    });

    pintarBotones();
}
