export function imprimirReporte() {
    window.print();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-imprimir-reporte]').forEach((boton) => {
        boton.addEventListener('click', () => imprimirReporte());
    });
});