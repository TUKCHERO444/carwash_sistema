/**
 * Módulo: publica/animaciones.js
 * Responsabilidad: animaciones de entrada (lazy) del sitio público.
 *
 * Sistema ligero sin dependencias: keyframes CSS (publica.css) disparados por
 * IntersectionObserver. Cada elemento [data-cw-anim] se revela UNA sola vez al
 * entrar en el viewport (progresivo conforme el usuario navega/desplaza) y al
 * terminar la animación se limpia (remueve el atributo) para devolver el
 * elemento a su estado CSS nativo (p. ej. no pisar `hover:-translate-y-1`).
 *
 * Atributos:
 *   - data-cw-anim="up|fade|left|right" : variante de entrada.
 *   - data-cw-group                     : contenedor; asigna un stagger
 *     (--cw-anim-delay) a sus hijos [data-cw-anim] según el índice.
 *
 * Accesibilidad / fallbacks (docs/design.md §13):
 *   - El gate `html.anim-listo` lo añade un script de bootstrap en el <head>
 *     (anim-script.blade.php) SOLO si hay IntersectionObserver y el usuario no
 *     pidió movimiento reducido. Sin el gate el contenido queda visible.
 *   - Si aquí se detecta reduced-motion o sin IntersectionObserver, se retira
 *     el gate y no se anima nada (estado final inmediato).
 *   - El cronómetro de limpieza por elemento garantiza que nada quede oculto
 *     aunque `animationend` no llegara a dispararse.
 */

export const VARIANTES = ['up', 'fade', 'left', 'right'];

export const OPCIONES_ANIMACION = Object.freeze({
    pasoMs: 70,
    maxMs: 420,
});

export const MARGEN_LIMPIEZA_MS = 300;

/**
 * Retraso escalonado para un índice dentro de un grupo, acotado a maxMs.
 * Pura (no toca el DOM): testeable con property-based testing.
 */
export function calcularRetraso(indice, opciones = {}) {
    const { pasoMs, maxMs } = { ...OPCIONES_ANIMACION, ...opciones };
    const base = Math.max(0, Number(indice) || 0) * Math.max(0, pasoMs);
    return Math.min(base, Math.max(0, maxMs));
}

/**
 * Gate de activación: solo hay animación si el navegador soporta
 * IntersectionObserver y el usuario no pidió movimiento reducido.
 */
export function debeAnimar(estado = {}) {
    const { soportaObservador = true, prefiereReduccion = false } = estado;
    return soportaObservador && !prefiereReduccion;
}

/** ¿La variante pasada existe en el sistema? */
export function esVarianteValida(variante) {
    return VARIANTES.includes(variante);
}

function matchMediaReducedMotion() {
    return (
        typeof window.matchMedia === 'function' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

export function initAnimaciones() {
    const soportaObservador = 'IntersectionObserver' in window;
    const prefiereReduccion = matchMediaReducedMotion();

    if (!debeAnimar({ soportaObservador, prefiereReduccion })) {
        document.documentElement.classList.remove('anim-listo');
        return;
    }

    document.documentElement.setAttribute('data-anim-iniciado', '');

    const elementos = Array.from(document.querySelectorAll('[data-cw-anim]'));
    if (elementos.length === 0) {
        return;
    }

    let observador;

    // Stagger: a cada hijo [data-cw-anim] de un [data-cw-group] se le asigna
    // un --cw-anim-delay según su índice dentro del grupo (document order).
    document.querySelectorAll('[data-cw-group]').forEach((grupo) => {
        Array.from(grupo.querySelectorAll('[data-cw-anim]')).forEach((elemento, indice) => {
            elemento.style.setProperty('--cw-anim-delay', `${calcularRetraso(indice)}ms`);
        });
    });

    const limpiarElemento = (elemento, temporizador) => {
        if (temporizador) {
            clearTimeout(temporizador);
        }
        elemento.classList.remove('is-visible');
        elemento.style.removeProperty('--cw-anim-delay');
        elemento.removeAttribute('data-cw-anim');
    };

    const limpiar = (elemento) => {
        const temporizador = temporizadores.get(elemento);
        temporizadores.delete(elemento);
        limpiarElemento(elemento, temporizador);
        elemento.removeEventListener('animationend', alTerminarAnimacion);
    };

    const alTerminarAnimacion = (evento) => {
        if (evento.target === evento.currentTarget) {
            limpiar(evento.currentTarget);
        }
    };

    const temporizadores = new WeakMap();

    const revelar = (elemento) => {
        observador.unobserve(elemento);
        elemento.classList.add('is-visible');

        const delay = parseFloat(elemento.style.getPropertyValue('--cw-anim-delay')) || 0;
        const tope = OPCIONES_ANIMACION.maxMs + delay + MARGEN_LIMPIEZA_MS;

        // Fallback: si `animationend` no disparara, igual se limpia a tope
        // de tiempo y el elemento vuelve a su CSS nativo (nada queda oculto).
        const temporizador = setTimeout(() => limpiar(elemento), tope);
        temporizadores.set(elemento, temporizador);
        elemento.addEventListener('animationend', alTerminarAnimacion);
    };

    observador = new IntersectionObserver(
        (entradas) => {
            entradas.forEach((entrada) => {
                if (entrada.isIntersecting) {
                    revelar(entrada.target);
                }
            });
        },
        { rootMargin: '0px 0px -60px 0px', threshold: 0.05 }
    );

    elementos.forEach((elemento) => observador.observe(elemento));
}