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

function configBase(horizontal = false) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: { color: colorTexto() },
            },
            tooltip: {
                callbacks: {
                    label: (ctx) => `${ctx.dataset.label}: ${FORMATO_SOLES.format(ctx.parsed.y ?? ctx.parsed)}`,
                },
            },
        },
        scales: horizontal
            ? {
                  x: { grid: { color: colorBordes() }, ticks: { color: colorTexto() } },
                  y: { grid: { color: colorBordes() }, ticks: { color: colorTexto() } },
              }
            : {
                  x: { grid: { color: colorBordes() }, ticks: { color: colorTexto() } },
                  y: { grid: { color: colorBordes() }, ticks: { color: colorTexto() } },
              },
    };
}

function initSeries() {
    const canvas = document.getElementById('chart-series');
    if (!canvas || !canvas.dataset.serie) return;

    const { dias, ventas, lavados, cambios } = JSON.parse(canvas.dataset.serie);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: dias.map((d) => new Date(d + 'T00:00:00').toLocaleDateString('es-PE', { day: '2-digit', month: 'short' })),
            datasets: [
                { label: 'Ventas', data: ventas, backgroundColor: COLORES_FUENTE.ventas },
                { label: 'Lavados', data: lavados, backgroundColor: COLORES_FUENTE.lavados },
                { label: 'Cambio de aceite', data: cambios, backgroundColor: COLORES_FUENTE.cambios },
            ],
        },
        options: {
            ...configBase(),
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
    const canvas = document.getElementById('chart-metodo-pago');
    if (!canvas || !canvas.dataset.pagos) return;

    const { efectivo, yape, izipay } = JSON.parse(canvas.dataset.pagos);

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Efectivo', 'Yape', 'Izipay'],
            datasets: [{
                data: [efectivo, yape, izipay],
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

document.addEventListener('DOMContentLoaded', () => {
    initSeries();
    initMetodoPago();
});
