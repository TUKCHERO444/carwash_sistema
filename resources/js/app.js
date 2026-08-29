import './bootstrap';
import './modal';
import './stock-modal';
import './image-viewer';
import './confirmations';
import { initInputFilters } from './utils/input-filters.js';


// Toggle para dropdowns y dropups de navegación
document.addEventListener('DOMContentLoaded', () => {
    // Filtrado en vivo global para inputs/textarea con [data-filter]
    initInputFilters();

    document.querySelectorAll('[data-dropdown-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const key = button.dataset.dropdownToggle;
            const menu = document.querySelector(`[data-dropdown-menu="${key}"]`);
            const chevron = button.querySelector('[data-chevron]');

            if (!menu) return;

            const isOpen = !menu.classList.contains('hidden');
            menu.classList.toggle('hidden', isOpen);
            chevron?.classList.toggle('rotate-180', !isOpen);
        });
    });

    // Cerrar dropdowns al hacer clic fuera
    document.addEventListener('click', (e) => {
        document.querySelectorAll('[data-dropdown]').forEach(container => {
            if (!container.contains(e.target)) {
                const key = container.dataset.dropdown;
                const menu = document.querySelector(`[data-dropdown-menu="${key}"]`);
                const chevron = container.querySelector('[data-chevron]');
                // Solo cerrar si no está en estado activo (ruta activa)
                if (menu && !menu.dataset.persistent) {
                    menu.classList.add('hidden');
                    chevron?.classList.remove('rotate-180');
                }
            }
        });
    });
});
