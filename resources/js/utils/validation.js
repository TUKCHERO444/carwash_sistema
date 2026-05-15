/**
 * resources/js/utils/validation.js
 * 
 * Motor de validación del lado del cliente para evitar recargas de página 
 * y pérdida de datos en formularios.
 */

export const Validation = {
    /**
     * Valida un formulario basándose en atributos HTML5 y clases personalizadas.
     * @param {HTMLFormElement} form 
     * @returns {boolean} True si es válido, False si tiene errores.
     */
    validate(form) {
        let isValid = true;
        let firstError = null;

        // Limpiar errores previos
        this.clearErrors(form);

        // 1. Validar inputs requeridos
        const requiredInputs = form.querySelectorAll('[required]');
        requiredInputs.forEach(input => {
            const value = input.value.trim();
            if (!value) {
                this.showError(input, 'Este campo es obligatorio.');
                isValid = false;
                if (!firstError) firstError = input;
            }
        });

        // 2. Validar checkboxes (ej: trabajadores)
        // Buscamos grupos que tengan al menos un checkbox con data-validate-min="1"
        const checkboxGroups = form.querySelectorAll('[data-validate-group]');
        const groupsChecked = {};

        checkboxGroups.forEach(cb => {
            const groupName = cb.dataset.validateGroup;
            if (!groupsChecked[groupName]) {
                const checked = form.querySelectorAll(`[data-validate-group="${groupName}"]:checked`);
                const min = parseInt(cb.dataset.validateMin || "0", 10);
                
                if (checked.length < min) {
                    const container = document.getElementById(`error-container-${groupName}`) || cb.parentElement;
                    this.showGroupError(container, groupName, `Debe seleccionar al menos ${min} opción(es).`);
                    isValid = false;
                    if (!firstError) firstError = cb;
                }
                groupsChecked[groupName] = true;
            }
        });

        // 3. Validar tablas dinámicas (ej: servicios en ingresos, productos en ventas)
        const dynamicTables = form.querySelectorAll('[data-validate-table]');
        dynamicTables.forEach(table => {
            const tbody = table.querySelector('tbody');
            const minRows = parseInt(table.dataset.validateMinRows || "1", 10);
            // Contamos filas que no sean el mensaje de "No hay items"
            const rowCount = tbody.querySelectorAll('tr:not(.no-items-row)').length;

            if (rowCount < minRows) {
                const errorId = table.dataset.validateErrorId;
                const errorEl = document.getElementById(errorId);
                if (errorEl) {
                    errorEl.textContent = `Debe agregar al menos ${minRows} item(s).`;
                    errorEl.classList.remove('hidden');
                }
                isValid = false;
                if (!firstError) firstError = table;
            }
        });

        if (!isValid && firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (firstError.focus) firstError.focus();
        }

        return isValid;
    },

    /**
     * Muestra un error en un input individual.
     */
    showError(input, message) {
        input.classList.add('input-error');
        
        // Crear elemento de mensaje si no existe
        let errorMsg = input.parentElement.querySelector('.error-message');
        if (!errorMsg) {
            errorMsg = document.createElement('p');
            errorMsg.className = 'error-message';
            input.parentElement.appendChild(errorMsg);
        }
        errorMsg.textContent = message;
        errorMsg.classList.remove('hidden');

        // Quitar error al escribir
        input.addEventListener('input', () => {
            input.classList.remove('input-error');
            errorMsg.classList.add('hidden');
        }, { once: true });
    },

    /**
     * Muestra un error para un grupo de elementos (como checkboxes).
     */
    showGroupError(container, groupName, message) {
        let errorMsg = document.getElementById(`error-msg-${groupName}`);
        if (!errorMsg) {
            errorMsg = document.createElement('p');
            errorMsg.id = `error-msg-${groupName}`;
            errorMsg.className = 'error-message mt-2';
            container.after(errorMsg);
        }
        errorMsg.textContent = message;
        errorMsg.classList.remove('hidden');

        // Escuchar cambios en los checkboxes del grupo para limpiar error
        const cbs = document.querySelectorAll(`[data-validate-group="${groupName}"]`);
        cbs.forEach(cb => {
            cb.addEventListener('change', () => {
                errorMsg.classList.add('hidden');
            }, { once: true });
        });
    },

    /**
     * Limpia todos los errores visuales de un formulario.
     */
    clearErrors(form) {
        form.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
        form.querySelectorAll('.error-message').forEach(el => el.classList.add('hidden'));
        
        // Limpiar errores de tablas
        const tableErrors = form.querySelectorAll('[data-validate-table]');
        tableErrors.forEach(table => {
            const errorId = table.dataset.validateErrorId;
            const errorEl = document.getElementById(errorId);
            if (errorEl) errorEl.classList.add('hidden');
        });
    }
};
