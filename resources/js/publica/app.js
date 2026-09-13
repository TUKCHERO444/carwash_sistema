/**
 * Entry point del sitio público.
 * Solo lógica global de la página pública (cabecera, pie, etc.).
 * Los módulos específicos de cada vista se cargan por separado con @vite.
 */
import { initMobileMenu } from './mobile-menu.js';
import { initProductosMenu } from './nav-productos.js';

document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initProductosMenu();
});