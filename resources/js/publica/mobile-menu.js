/**
 * Menú móvil del sitio público.
 *
 * Controla el menú desplegable de la cabecera (botón hamburguesa →
 * panel de navegación). No depende del DOM del panel admin.
 */

export function initMobileMenu() {
    const toggle = document.querySelector('[data-mobile-menu-toggle]');
    const menu = document.querySelector('[data-mobile-menu]');

    if (!toggle || !menu) {
        return;
    }

    const iconsOpen = toggle.querySelector('[data-icon="open"]');
    const iconsClose = toggle.querySelector('[data-icon="close"]');

    function setOpen(open) {
        menu.hidden = !open;
        menu.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Cerrar menú de navegación' : 'Abrir menú de navegación');

        if (iconsOpen) iconsOpen.classList.toggle('hidden', open);
        if (iconsClose) iconsClose.classList.toggle('hidden', !open);
    }

    toggle.addEventListener('click', () => {
        setOpen(menu.hidden);
    });

    // Cerrar al elegir una opción del menú
    menu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });

    // Cerrar con Escape
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !menu.hidden) {
            setOpen(false);
            toggle.focus();
        }
    });
}