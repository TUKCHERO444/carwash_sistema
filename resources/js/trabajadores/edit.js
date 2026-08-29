import { initFotoPreview } from '../productos/shared.js';
import { initConsultarDni } from '../utils/consultarDni.js';

document.addEventListener('DOMContentLoaded', () => {
    initFotoPreview('foto', 'preview-nueva', 'bloque-nueva');
    initConsultarDni({ endpoint: '/trabajadores/consultar-dni' });
});
