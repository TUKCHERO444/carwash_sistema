import Chart from 'chart.js/auto';

const FORMATO_SOLES = new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
    minimumFractionDigits: 2,
});

const COLORES_FUENTE = {
    ventas: '#3b82f6',
    lavados: '#10b981',
    cambios: '#f59e0b',
};

function colorTexto() {
    return document.documentElement.classList.contains('dark') ? '#94a3b8' : '#6b7280';
}

function colorBordes() {
    return document.documentElement.classList.contains('dark') ? '#1e293b' : '#e5e7eb';
}

const ABREVIATURAS_MESES = {
    '01': 'ene',
    '02': 'feb',
    '03': 'mar',
    '04': 'abr',
    '05': 'may',
    '06': 'jun',
    '07': 'jul',
    '08': 'ago',
    '09': 'sep',
    '10': 'oct',
    '11': 'nov',
    '12': 'dic',
};

export function etiquetaDia(fechaKey) {
    const [, mes, dia] = /\d{4}-(\d{2})-(\d{2})/.exec(fechaKey) ?? [];
    if (!dia || !ABREVIATURAS_MESES[mes]) {
        return fechaKey;
    }

    return `${dia} ${ABREVIATURAS_MESES[mes]}`;
}

export function actividadDominante(dia) {
    if (!dia || !Number.isFinite(dia.ventas) || !Number.isFinite(dia.lavados) || !Number.isFinite(dia.cambios)) {
        return null;
    }

    const total = dia.ventas + dia.lavados + dia.cambios;
    if (total <= 0) return null;

    const dominante = ['ventas', 'lavados', 'cambios']
        .map((clave) => [clave, dia[clave] / total])
        .reduce((a, b) => (b[1] > a[1] ? b : a), ['', -Infinity]);

    return dominante[1] >= 0.5 ? dominante[0] : null;
}

function initIngresos() {
    const canvas = document.getElementById('chart-reporte-ingresos');
    const serie = window.reportesIngresos;
    if (!canvas || !serie || !Array.isArray(serie.dias)) return;

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: serie.dias.map((d) => etiquetaDia(d)),
            datasets: [
                { label: 'Ventas', data: serie.ventas, backgroundColor: COLORES_FUENTE.ventas },
                { label: 'Lavados', data: serie.lavados, backgroundColor: COLORES_FUENTE.lavados },
                { label: 'Cambio de aceite', data: serie.cambios, backgroundColor: COLORES_FUENTE.cambios },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: colorTexto() } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${FORMATO_SOLES.format(ctx.parsed.y)}`,
                    },
                },
            },
            scales: {
                x: { stacked: true, grid: { color: colorBordes() }, ticks: { color: colorTexto() } },
                y: {
                    stacked: true,
                    grid: { color: colorBordes() },
                    ticks: { color: colorTexto(), callback: (v) => FORMATO_SOLES.format(v) },
                },
            },
        },
    });
}

function initMetodoPago() {
    const canvas = document.getElementById('chart-reporte-metodo-pago');
    const pagos = window.reportesMetodoPago;
    if (!canvas || !pagos) return;

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Efectivo', 'Yape', 'Izipay'],
            datasets: [{
                data: [pagos.efectivo, pagos.yape, pagos.izipay],
                backgroundColor: ['#10b981', '#8b5cf6', '#3b82f6'],
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: colorTexto() } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.label}: ${FORMATO_SOLES.format(ctx.parsed)}`,
                    },
                },
            },
            cutout: '55%',
        },
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        initIngresos();
        initMetodoPago();
    });
}