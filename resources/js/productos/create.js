import { initFotoPreview } from './shared.js';
import { Validation } from '../utils/validation.js';

document.addEventListener('DOMContentLoaded', () => {
    initFotoPreview('foto', 'preview-foto', 'bloque-preview');

    const form = document.getElementById('form-producto');
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!Validation.validate(form)) {
                e.preventDefault();
            }
        });
    }
});
