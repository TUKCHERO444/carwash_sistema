/**
 * Módulo: proveedores/validate.js
 * Responsabilidad: Validación en submit para los formularios de proveedores (create y edit).
 * Sigue el patrón de validation.js — bloquea el envío si el formulario no es válido.
 * El RUC se valida en vivo con data-filter="digits" + data-validate-length="11"
 * (input-filters.js elimina cualquier carácter no numérico y recorta a 11).
 */

import { Validation } from '../utils/validation.js';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-proveedor');
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!Validation.validate(form)) {
                e.preventDefault();
            }
        });
    }
});
