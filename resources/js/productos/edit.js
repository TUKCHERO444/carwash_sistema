import { initFotoPreview } from './shared.js';
import { Validation } from '../utils/validation.js';

document.addEventListener('DOMContentLoaded', () => {
    initFotoPreview('foto', 'preview-nueva', 'bloque-nueva');

    const form = document.getElementById('form-producto');
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!Validation.validate(form)) {
                e.preventDefault();
            }
        });
    }
});
