import Chart from 'chart.js/auto';

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

export function etiquetaHora(hora) {
    return `${String(hora).padStart(2, '0')}:00`;
}

function colorTexto() {
    return document.documentElement.classList.contains('dark') ? '#94a3b8' : '#6b7280';
}

function colorBordes() {
    return document.documentElement.classList.contains('dark') ? '#1e293b' : '#e5e7eb';
}

function initSerie() {
    const canvas = document.getElementById('chart-operativo-serie');
    const serie = window.operativoSerie;
    if (!canvas || !serie || !Array.isArray(serie.dias)) return;

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: serie.dias.map((d) => etiquetaDia(d)),
            datasets: [
                { label: 'Atenciones', data: serie.atenciones, backgroundColor: '#10b981' },
                { label: 'Vehículos únicos', data: serie.vehiculos_unicos, backgroundColor: '#3b82f6' },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: colorTexto() } },
            },
            scales: {
                x: { grid: { color: colorBordes() }, ticks: { color: colorTexto() } },
                y: { beginAtZero: true, ticks: { color: colorTexto(), precision: 0 }, grid: { color: colorBordes() } },
            },
        },
    });
}

function initHoras() {
    const canvas = document.getElementById('chart-operativo-horas');
    const horas = window.operativoHoras;
    if (!canvas || !horas) return;

    const datos = Array.isArray(horas) ? horas : Object.values(horas);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: datos.map((_, i) => etiquetaHora(i)),
            datasets: [{ label: 'Atenciones', data: datos, backgroundColor: '#f59e0b' }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: colorTexto() } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: colorTexto(), maxRotation: 90, minRotation: 90 } },
                y: { beginAtZero: true, ticks: { color: colorTexto(), precision: 0 }, grid: { color: colorBordes() } },
            },
        },
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        initSerie();
        initHoras();
    });
}
