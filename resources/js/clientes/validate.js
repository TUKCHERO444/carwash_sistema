/**
 * Módulo: clientes/validate.js
 * Responsabilidad: Validación en submit para los formularios de clientes (create y edit).
 * Sigue el patrón de validation.js — bloquea el envío si el formulario no es válido.
 */

import { Validation } from '../utils/validation.js';
import { initConsultarDni } from '../utils/consultarDni.js';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-cliente');
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!Validation.validate(form)) {
                e.preventDefault();
            }
        });
    }

    initConsultarDni({ endpoint: '/clientes/consultar-dni' });
});
