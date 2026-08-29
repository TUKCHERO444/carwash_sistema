/**
 * Módulo: marcas/validate.js
 * Responsabilidad: Validación en submit para los formularios de marcas (create y edit).
 * Sigue el patrón de validation.js — bloquea el envío si el formulario no es válido.
 */

import { Validation } from '../utils/validation.js';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-marca');
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!Validation.validate(form)) {
                e.preventDefault();
            }
        });
    }
});
