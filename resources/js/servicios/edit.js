/**
 * Módulo: servicios/edit.js
 * Responsabilidad: Preview en vivo de la nueva imagen al editar un servicio.
 */

import { initFotoPreview } from '../productos/shared.js';

document.addEventListener('DOMContentLoaded', () => {
    initFotoPreview('imagen', 'preview-nueva', 'bloque-nueva');
});