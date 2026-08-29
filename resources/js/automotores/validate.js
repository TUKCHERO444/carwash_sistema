/**
 * Módulo: automotores/validate.js
 * Responsabilidad: Validación en submit para los formularios de automotores (create y edit)
 * y autocompletado de datos del vehículo desde la API de consulta de placa.
 * Sigue el patrón de validation.js — bloquea el envío si el formulario no es válido.
 */

import { Validation } from '../utils/validation.js';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-automotor');
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!Validation.validate(form)) {
                e.preventDefault();
            }
        });
    }

    initConsultarPlaca();
});

/**
 * Autocompleta los campos del automotor consultando la placa (datos locales o API).
 * Solo rellena los campos que estén vacíos.
 */
function initConsultarPlaca() {
    const btn = document.getElementById('btn-consultar-placa');
    const inputPlaca = document.getElementById('placa');
    if (!btn || !inputPlaca) return;

    btn.addEventListener('click', async () => {
        const placa = inputPlaca.value.trim();
        if (placa.length < 6) return;

        btn.disabled = true;
        const label = document.getElementById('label-consultar-placa');
        const textoOriginal = label ? label.textContent : '';
        if (label) label.textContent = 'Consultando...';

        try {
            const response = await fetch(`/automotores/consultar-placa?placa=${encodeURIComponent(placa)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) return;

            const data = await response.json();
            if (!data.success || !data.data) return;

            const { marca, modelo, serie, color, motor, vin } = data.data;
            autocompletar('marca', marca);
            autocompletar('modelo', modelo);
            autocompletar('serie', serie);
            autocompletar('color', color);
            autocompletar('motor', motor);
            autocompletar('vin', vin);
        } catch (error) {
            console.error('Error consultando placa:', error);
        } finally {
            btn.disabled = false;
            if (label) label.textContent = textoOriginal;
        }
    });
}

function autocompletar(id, valor) {
    const campo = document.getElementById(id);
    if (campo && valor && !campo.value) {
        campo.value = valor;
    }
}