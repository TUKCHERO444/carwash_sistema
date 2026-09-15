/**
 * Módulo: marcas/validate.js
 * Responsabilidad: Validación en submit para los formularios de marcas (create y edit).
 * Sigue el patrón de validation.js — bloquea el envío si el formulario no es válido.
 */

import { Validation } from '../utils/validation.js';
import { initFotoPreview } from '../productos/shared.js';

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('bloque-preview')) {
        initFotoPreview('foto', 'preview-foto', 'bloque-preview');
    } else if (document.getElementById('bloque-nueva')) {
        initFotoPreview('foto', 'preview-nueva', 'bloque-nueva');
    }

    const form = document.getElementById('form-marca');
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!Validation.validate(form)) {
                e.preventDefault();
            }
        });
    }
});
