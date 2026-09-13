/**
 * Menú desplegable "Productos" de la navegación pública.
 *
 * Doble funcionalidad sobre el enlace "Productos" del nav (escritorio):
 *   - Pulsación corta (click): navega a la vista pública de productos.
 *   - Mantener presionado (~450ms): abre el submenú de categorías sin navegar.
 *   - El chevron actúa como toggle accesible (teclado/ratón) para abrir/cerrar.
 *
 * Accesibilidad: aria-haspopup / aria-expanded ágiles, cierre con Escape y al
 * pulsar fuera del submenú. La lógica de decisión vive en nav-productos-core.js
 * (funciones puras probadas con property tests).
 */

import { LONG_PRESS_MS, getReleaseAction } from './nav-productos-core.js';

export function initProductosMenu() {
    const menu = document.querySelector('[data-productos-menu]');

    if (!menu) {
        return;
    }

    const trigger = menu.querySelector('[data-productos-trigger]');
    const chevron = menu.querySelector('[data-productos-chevron]');
    const panel = menu.querySelector('[data-productos-panel]');

    if (!trigger || !panel) {
        return;
    }

    const setOpen = (open) => {
        panel.hidden = !open;
        trigger.setAttribute('aria-expanded', String(open));
        if (chevron) {
            chevron.classList.toggle('rotate-180', open);
        }
    };

    const open = () => setOpen(true);
    const close = () => setOpen(false);

    let pressStartAt = 0;
    let holdTimer = null;
    let held = false;

    const clearHoldTimer = () => {
        if (holdTimer !== null) {
            clearTimeout(holdTimer);
            holdTimer = null;
        }
    };

    trigger.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
        }

        held = false;
        pressStartAt = Date.now();
        clearHoldTimer();

        // Si se mantiene el umbral, el menú se abre sin navegar (el click
        // posterior se cancela con el flag `held`).
        holdTimer = setTimeout(() => {
            held = true;
            open();
        }, LONG_PRESS_MS);
    });

    // Al soltar antes del umbral: decisión pura (navegar o abrir flanco de
    // seguridad si el event loop se retrasó respecto al timeout).
    trigger.addEventListener('pointerup', (event) => {
        if (held) {
            return;
        }

        clearHoldTimer();

        const holdMs = Date.now() - pressStartAt;
        if (getReleaseAction(holdMs) === 'menu') {
            event.preventDefault();
            open();
        }
    });

    trigger.addEventListener('pointercancel', clearHoldTimer);

    // Si el puntero se mueve fuera del bloque (p. ej. hacia el panel) ya no
    // cuenta como press-and-hold, pero no cerramos el submenú abierto.
    menu.addEventListener('pointerleave', clearHoldTimer);

    trigger.addEventListener('click', (event) => {
        if (held) {
            event.preventDefault();
            held = false;
        }
    });

    // Chevron: toggle explícito accesible (teclado/ratón).
    if (chevron) {
        chevron.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            panel.hidden ? open() : close();
        });
    }

    // Cerrar con Escape y devolver el foco al enlace.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            close();
            trigger.focus();
        }
    });

    // Cerrar al pulsar fuera del submenú.
    document.addEventListener('pointerdown', (event) => {
        if (!panel.hidden && !menu.contains(event.target)) {
            close();
        }
    });
}