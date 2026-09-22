/**
 * Módulo: servicios/create.js
 * Responsabilidad: Preview en vivo de la imagen al crear un servicio.
 */

import { initFotoPreview } from '../productos/shared.js';

document.addEventListener('DOMContentLoaded', () => {
    initFotoPreview('imagen', 'preview-foto', 'bloque-preview');
});