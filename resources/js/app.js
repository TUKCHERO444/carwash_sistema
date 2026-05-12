import './bootstrap';
import './stock-modal';

// Toggle para dropdowns y dropups de navegación
document.addEventListener('DOMContentLoaded', () => {
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
