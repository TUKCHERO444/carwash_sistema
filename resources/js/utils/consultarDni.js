/**
 * Módulo: utils/consultarDni.js
 * Responsabilidad: Autocompletado de nombre y apellidos consultando el DNI
 * (datos locales o API de api.json.pe). Reutilizado por los módulos de
 * clientes y trabajadores. Sigue el patrón de automotores/validate.js —
 * solo rellena los campos vacíos y nunca bloquea el formulario.
 */

/**
 * Configura el botón "Consultar DNI" de un formulario.
 * @param {Object} config
 * @param {string} config.endpoint  URL del endpoint interno (ej. '/clientes/consultar-dni')
 * @param {string} [config.btnId='btn-consultar-dni']  ID del botón
 * @param {string} [config.dniId='dni']  ID del input DNI
 * @param {string} [config.labelId='label-consultar-dni']  ID del span del botón
 */
export function initConsultarDni(config) {
    const { endpoint, btnId = 'btn-consultar-dni', dniId = 'dni', labelId = 'label-consultar-dni' } = config;
    const btn = document.getElementById(btnId);
    const inputDni = document.getElementById(dniId);
    if (!btn || !inputDni) return;

    btn.addEventListener('click', async () => {
        const dni = inputDni.value.trim();
        if (dni.length !== 8) return;

        btn.disabled = true;
        const label = document.getElementById(labelId);
        const textoOriginal = label ? label.textContent : '';
        if (label) label.textContent = 'Consultando...';

        try {
            const response = await fetch(`${endpoint}?dni=${encodeURIComponent(dni)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) return;

            const data = await response.json();
            if (!data.success || !data.data) return;

            const { nombres, apellido_paterno, apellido_materno } = data.data;
            autocompletar('nombre', nombres);
            autocompletar('apellido_paterno', apellido_paterno);
            autocompletar('apellido_materno', apellido_materno);
        } catch (error) {
            console.error('Error consultando DNI:', error);
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