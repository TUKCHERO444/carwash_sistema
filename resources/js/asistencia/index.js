/**
 * asistencia/index.js
 * Módulo JavaScript del panel de asistencia: calendario mensual consultable
 * con indicadores de color por día y modal de detalle con registro manual
 * de marcas de entrada (full-sync vía POST /asistencia/marcar).
 * Feature: asistencia — Requisitos 2, 3, 4, 5, 6, 7
 *
 * Sin librerías externas: la cuadrícula del calendario se genera con
 * funciones puras exportadas para los property tests (tests/js/asistencia).
 */

/**
 * Día que inicia la semana: 1 = lunes (ISO 8601). Decisión UI revisable.
 */
export const INICIO_SEMANA = 1;

const CELDAS_POR_GRID = 42;
const CLASES_VARIANTE = [
    'bg-emerald-500/15', 'text-emerald-700', 'dark:text-emerald-400', 'hover:bg-emerald-500/25',
    'bg-amber-500/15', 'text-amber-700', 'dark:text-amber-400', 'hover:bg-amber-500/25',
    'bg-red-500/15', 'text-red-700', 'dark:text-red-400', 'hover:bg-red-500/25',
    'text-secondary', 'hover:bg-slate-100', 'dark:hover:bg-slate-800',
];

const pad = (n) => String(n).padStart(2, '0');

/**
 * Devuelve 'YYYY-MM-DD' para una fecha (mes 1-based).
 * @param {number} year
 * @param {number} month 1..12
 * @param {number} day
 * @returns {string}
 */
export function fechaKey(year, month, day) {
    return `${year}-${pad(month)}-${pad(day)}`;
}

/**
 * Parsea una clave canónica 'YYYY-MM-DD' → { year, month (1-based), day }.
 * @param {string} key
 * @returns {{year:number, month:number, day:number}}
 */
export function parseFechaKey(key) {
    const [year, month, day] = key.split('-').map(Number);
    return { year, month, day };
}

/**
 * Construye la cuadrícula del mes: exactamente 42 celdas (6 semanas),
 * comenzando el INICIO_SEMANA. Las celdas fuera del mes son null.
 * @param {number} year
 * @param {number} month 1..12
 * @returns {Array<{year:number, month:number, day:number} | null>}
 */
export function buildMonthGrid(year, month) {
    const primerDia = new Date(year, month - 1, 1);
    const dow = primerDia.getDay(); // 0 = domingo..6 = sábado
    const offset = (dow - INICIO_SEMANA + 7) % 7;
    const diasEnMes = new Date(year, month, 0).getDate();

    return Array.from({ length: CELDAS_POR_GRID }, (_, i) => {
        const dia = i - offset + 1;
        if (dia >= 1 && dia <= diasEnMes) {
            return { year, month, day: dia };
        }
        return null;
    });
}

/**
 * ¿La fecha es hoy o pasada (consultable y gestionable)?
 * La comparación lexicográfica es válida con claves ISO 'YYYY-MM-DD'.
 * @param {string} key
 * @param {string} hoyKey
 * @returns {boolean}
 */
export function esFechaPasadaOActual(key, hoyKey) {
    return key <= hoyKey;
}

/**
 * Clasifica un día según sus conteos de asistencia.
 * @param {number} asistentes
 * @param {number} totalActivos
 * @returns {'completo' | 'parcial' | 'nulo' | 'sin-datos'}
 */
export function claseDia(asistentes, totalActivos) {
    if (!Number.isFinite(totalActivos) || totalActivos <= 0) return 'sin-datos';
    if (asistentes >= totalActivos) return 'completo';
    if (asistentes > 0) return 'parcial';
    return 'nulo';
}

/**
 * Etiqueta descriptiva de una fecha: 'Sábado, 12 de septiembre de 2026'.
 * @param {string} key
 * @returns {string}
 */
export function etiquetaFecha(key) {
    const { year, month, day } = parseFechaKey(key);
    const texto = new Intl.DateTimeFormat('es-PE', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    }).format(new Date(year, month - 1, day));
    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

function mesTitulo(year, month) {
    const texto = new Intl.DateTimeFormat('es-PE', { month: 'long', year: 'numeric' })
        .format(new Date(year, month - 1, 1));
    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

function limpiarVariantes(btn) {
    btn.classList.remove(...CLASES_VARIANTE);
}

function aplicarClaseCelda(btn, clase) {
    limpiarVariantes(btn);

    const variante = {
        completo: ['bg-emerald-500/15', 'text-emerald-700', 'dark:text-emerald-400', 'hover:bg-emerald-500/25'],
        parcial: ['bg-amber-500/15', 'text-amber-700', 'dark:text-amber-400', 'hover:bg-amber-500/25'],
        nulo: ['bg-red-500/15', 'text-red-700', 'dark:text-red-400', 'hover:bg-red-500/25'],
        'sin-datos': ['text-secondary', 'hover:bg-slate-100', 'dark:hover:bg-slate-800'],
    }[clase] ?? [];

    btn.classList.add(...variante);
}

function crearCelda(celda, key, hoyKey) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.setAttribute('data-dia', key);

    if (!celda) {
        btn.disabled = true;
        btn.className = 'invisible aspect-square';
        return btn;
    }

    const esFuturo = key > hoyKey;
    const esHoy = key === hoyKey;

    btn.textContent = String(celda.day);
    btn.className = `relative aspect-square rounded-lg text-sm font-medium transition-colors focus:outline-none flex flex-col items-center justify-center ${
        esFuturo
            ? 'text-slate-400 dark:text-slate-600 cursor-not-allowed pointer-events-none'
            : 'text-primary hover:bg-slate-100 dark:hover:bg-slate-800'
    }${esHoy ? ' ring-2 ring-blue-500 ring-inset' : ''}`;

    if (esFuturo) {
        btn.disabled = true;
    }

    return btn;
}

/**
 * Punto de entrada: renderiza el calendario, maneja la navegación de mes,
 * el marcado de indicadores y las acciones del modal de detalle.
 */
export function initAsistencia() {
    const calendario = document.getElementById('asistencia-calendario');
    if (!calendario) return;

    const hoyKey = window.asistenciaHoy ?? new Date().toISOString().slice(0, 10);
    const hoy = parseFechaKey(hoyKey);

    let estado = { year: hoy.year, month: hoy.month };
    let fechaModal = null;

    const titulo = document.getElementById('asistencia-titulo-mes');
    const resumenEl = document.getElementById('asistencia-resumen');
    const asistentesEl = document.getElementById('asistencia-asistentes');
    const noAsistentesEl = document.getElementById('asistencia-no-asistentes');
    const gestionEl = document.getElementById('asistencia-gestion');
    const gestionFilasEl = document.getElementById('asistencia-gestion-filas');
    const guardarBtn = document.getElementById('asistencia-guardar');
    const mensajeEl = document.getElementById('asistencia-mensaje');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    function mostrarMensaje(mensaje) {
        mensajeEl.textContent = mensaje;
        mensajeEl.classList.remove('hidden');
    }

    function ocultarMensaje() {
        mensajeEl.classList.add('hidden');
        mensajeEl.textContent = '';
    }

    function mostrarAlerta(mensaje, tipo = 'success') {
        const existente = document.getElementById('js-flash-asistencia');
        if (existente) existente.remove();

        const flash = document.createElement('div');
        flash.id = 'js-flash-asistencia';
        flash.setAttribute('role', 'alert');
        flash.className = `mb-4 px-4 py-3 rounded-lg text-sm border ${
            tipo === 'error'
                ? 'bg-red-100 text-red-800 border-red-200'
                : 'bg-green-100 text-green-800 border-green-200'
        }`;
        flash.textContent = mensaje;

        const contenedor = document.querySelector('.p-6');
        (contenedor ?? document.body).insertBefore(flash, (contenedor ?? document.body).firstChild);
        setTimeout(() => flash.remove(), 5000);
    }

    function renderCalendario() {
        titulo.textContent = mesTitulo(estado.year, estado.month);

        calendario.innerHTML = '';
        const grid = document.createElement('div');
        grid.className = 'grid grid-cols-7 gap-1 sm:gap-2';

        ['L', 'M', 'X', 'J', 'V', 'S', 'D'].forEach((nombre) => {
            const h = document.createElement('div');
            h.className = 'text-center text-xs font-medium text-secondary py-2';
            h.textContent = nombre;
            grid.appendChild(h);
        });

        buildMonthGrid(estado.year, estado.month).forEach((celda) => {
            const key = celda ? fechaKey(celda.year, celda.month, celda.day) : 'vacio';
            grid.appendChild(crearCelda(celda, key, hoyKey));
        });

        calendario.appendChild(grid);
        cargarIndicadores();
    }

    async function cargarIndicadores() {
        try {
            const respuesta = await fetch(`/asistencia/por-mes?mes=${estado.year}-${pad(estado.month)}`, {
                headers: { 'Accept': 'application/json' },
            });
            if (!respuesta.ok) return;

            const data = await respuesta.json();
            document.querySelectorAll('#asistencia-calendario [data-dia]').forEach((btn) => {
                const key = btn.dataset.dia;
                if (key > hoyKey) return;
                const info = data.dias?.[key];
                const total = data.total_activos ?? Number(window.asistenciaTotalActivos ?? 0);
                aplicarClaseCelda(btn, info ? claseDia(info.asistentes, total) : 'sin-datos');
            });
        } catch (_error) {
            // Los indicadores son decorativos: ante fallo se muestra el mes sin colores.
        }
    }

    async function abrirDetalle(key) {
        if (key > hoyKey) return;

        try {
            const respuesta = await fetch(`/asistencia/por-fecha?fecha=${key}`, {
                headers: { 'Accept': 'application/json' },
            });
            if (!respuesta.ok) {
                mostrarAlerta('No se pudo consultar la asistencia. Intente nuevamente.', 'error');
                return;
            }

            const data = await respuesta.json();
            fechaModal = key;
            poblarModal(data);
            window.openModal?.('modal-asistencia');
        } catch (_error) {
            mostrarAlerta('Error de conexión al consultar la asistencia.', 'error');
        }
    }

    function filaAsistente(item, verde) {
        const li = document.createElement('li');
        li.className = 'flex items-center gap-3 px-3 py-3 whitespace-nowrap';

        const punto = document.createElement('span');
        punto.className = `inline-block w-2.5 h-2.5 rounded-full shrink-0 ${verde ? 'bg-emerald-500' : 'bg-red-500'}`;
        li.appendChild(punto);

        const avatar = document.createElement('span');
        avatar.className = 'w-8 h-8 rounded-full border border-main bg-gray-100 dark:bg-slate-800 flex items-center justify-center shrink-0 overflow-hidden';
        if (item.foto_url) {
            const img = document.createElement('img');
            img.src = item.foto_url;
            img.alt = item.nombre_completo;
            img.className = 'w-full h-full object-cover';
            avatar.appendChild(img);
        } else {
            avatar.innerHTML = `<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>`;
        }
        li.appendChild(avatar);

        const nombre = document.createElement('span');
        nombre.className = 'text-sm text-primary truncate';
        nombre.textContent = item.nombre_completo;
        li.appendChild(nombre);

        return li;
    }

    function poblarListado(contenedor, items, vacio, verde = true) {
        contenedor.innerHTML = '';

        if (items.length === 0) {
            const li = document.createElement('li');
            li.className = 'px-3 py-4 text-center text-xs text-secondary';
            li.textContent = vacio;
            contenedor.appendChild(li);
            return;
        }

        items.forEach((item) => {
            const li = filaAsistente(item, verde);
            const hora = document.createElement('span');
            hora.className = 'text-xs text-secondary font-medium';
            hora.textContent = item.hora_entrada ?? '';
            li.appendChild(hora);
            contenedor.appendChild(li);
        });
    }

    function poblarGestion(data) {
        gestionFilasEl.innerHTML = '';

        const trabajadores = [
            ...data.asistentes.map((a) => ({ id: a.trabajador_id, nombre: a.nombre_completo, hora: a.hora_entrada })),
            ...data.no_asistentes.map((n) => ({ id: n.trabajador_id, nombre: n.nombre_completo, hora: null })),
        ];

        trabajadores.forEach((t) => {
            const label = document.createElement('label');
            label.className = 'flex items-center gap-3 py-2 px-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800/40 cursor-pointer transition-colors';

            const check = document.createElement('input');
            check.type = 'checkbox';
            check.value = String(t.id);
            check.checked = Boolean(t.hora);
            check.className = 'h-4 w-4 rounded accent-emerald-600 shrink-0';
            check.setAttribute('data-gestion-check', '');

            const nombre = document.createElement('span');
            nombre.className = 'text-sm text-primary flex-1 truncate';
            nombre.textContent = t.nombre;

            const hora = document.createElement('input');
            hora.type = 'time';
            hora.value = t.hora ?? window.asistenciaHoraActual ?? '08:00';
            hora.className = 'input-main border border-main rounded-lg px-2 py-1 text-sm w-28 flex-shrink-0';
            hora.setAttribute('data-gestion-hora', '');

            label.append(check, nombre, hora);
            gestionFilasEl.appendChild(label);
        });

        gestionEl.classList.remove('hidden');
        guardarBtn.classList.remove('hidden');
        guardarBtn.classList.add('inline-flex');
    }

    function poblarModal(data) {
        document.getElementById('asistencia-fecha').textContent = etiquetaFecha(fechaModal);

        resumenEl.innerHTML = [
            cardResumen('Asistieron', data.asistieron, 'bg-emerald-500', 'bg-emerald-100 dark:bg-emerald-900/30 border-emerald-200 dark:border-emerald-900/50 text-emerald-800 dark:text-emerald-400'),
            cardResumen('No asistieron', data.no_asistieron, 'bg-red-500', 'bg-red-100 dark:bg-red-900/30 border-red-200 dark:border-red-900/50 text-red-800 dark:text-red-400'),
        ].join('');

        poblarListado(asistentesEl, data.asistentes, 'Ningún trabajador asistió', true);
        poblarListado(noAsistentesEl, data.no_asistentes, 'Todos los trabajadores asistieron', false);

        ocultarMensaje();

        if (esFechaPasadaOActual(fechaModal, hoyKey)) {
            poblarGestion(data);
        } else {
            gestionEl.classList.add('hidden');
            guardarBtn.classList.add('hidden');
            guardarBtn.classList.remove('inline-flex');
        }
    }

    function cardResumen(tituloCadena, conteo, puntoClase, cardClase) {
        return `<div class="rounded-lg border px-4 py-3 ${cardClase}">
            <div class="flex items-center gap-2">
                <span class="inline-block w-2.5 h-2.5 rounded-full ${puntoClase}"></span>
                <span class="text-xs font-medium uppercase tracking-wide">${tituloCadena}</span>
            </div>
            <p class="mt-1 text-2xl font-bold">${conteo}</p>
        </div>`;
    }

    async function guardarAsistencia() {
        const marcas = {};

        gestionFilasEl.querySelectorAll('[data-gestion-check]').forEach((check) => {
            if (!check.checked) return;
            const hora = check.closest('label').querySelector('[data-gestion-hora]');
            marcas[check.value] = hora.value;
        });

        if (Object.values(marcas).some((hora) => !hora)) {
            mostrarMensaje('Todos los trabajadores marcados deben tener una hora de entrada.');
            return;
        }

        guardarBtn.disabled = true;
        ocultarMensaje();

        try {
            const respuesta = await fetch('/asistencia/marcar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ fecha: fechaModal, marcas }),
            });

            const data = await respuesta.json();

            if (respuesta.ok && data.success) {
                poblarModal(data);
                renderCalendario();
                mostrarAlerta(data.message ?? 'Asistencia registrada correctamente.');
            } else if (respuesta.status === 422) {
                const errores = data?.errors ?? {};
                const fechaError = (typeof errores.fecha === 'string')
                    ? [errores.fecha]
                    : errores.fecha ?? Object.values(errores)[0];
                mostrarMensaje((fechaError?.[0] ?? 'Error de validación.'));
            } else {
                mostrarMensaje('Error al guardar la asistencia. Intente nuevamente.');
            }
        } catch (_error) {
            mostrarMensaje('Error de conexión al guardar la asistencia.');
        }

        guardarBtn.disabled = false;
    }

    // ── Eventos (delegación en document) ────────────────────────────────────
    document.addEventListener('click', (e) => {
        const hoyBtn = e.target.closest('[data-abrir-hoy]');
        if (hoyBtn) {
            e.preventDefault();
            abrirDetalle(hoyKey);
            return;
        }

        const prev = e.target.closest('[data-nav-prev]');
        if (prev) {
            const anterior = new Date(estado.year, estado.month - 2, 1);
            estado = { year: anterior.getFullYear(), month: anterior.getMonth() + 1 };
            renderCalendario();
            return;
        }

        const next = e.target.closest('[data-nav-next]');
        if (next) {
            const siguiente = new Date(estado.year, estado.month, 1);
            estado = { year: siguiente.getFullYear(), month: siguiente.getMonth() + 1 };
            renderCalendario();
            return;
        }

        const dia = e.target.closest('[data-dia]');
        if (dia && dia.dataset.dia !== 'vacio') {
            abrirDetalle(dia.dataset.dia);
        }
    });

    guardarBtn.addEventListener('click', guardarAsistencia);

    renderCalendario();
}

// Auto-inicialización (protegida para permitir imports en test Node sin DOM).
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => initAsistencia());
}