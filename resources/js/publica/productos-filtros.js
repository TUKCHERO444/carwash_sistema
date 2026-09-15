/**
 * Módulo: publica/productos-filtros.js
 * Responsabilidad: Filtros combinables de la vista de productos por categoría.
 * Envía el formulario GET con búsqueda dinámica (debounce) y auto-submit al
 * cambiar cualquiera de los selects de orden, conservando los demás filtros.
 */

const TIEMPO_ESPERA = 350;

function debounce(fn, ms) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), ms);
    };
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('filtros-productos');
    if (!form) return;

    const enviar = () => {
        if (form.checkValidity()) {
            form.requestSubmit();
        }
    };

    form.querySelectorAll('[data-auto-submit]').forEach((el) => {
        el.addEventListener('change', enviar);
    });

    const inputBusqueda = form.querySelector('[data-auto-search]');
    if (inputBusqueda) {
        inputBusqueda.addEventListener('input', debounce(enviar, TIEMPO_ESPERA));
    }
});