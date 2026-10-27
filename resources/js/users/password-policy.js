/**
 * Módulo: users/password-policy.js
 * Responsabilidad: evaluar la política de contraseñas del panel administrativo
 * y reflejarla en los formularios de creación/edición de usuarios.
 */

export const PASSWORD_MIN_LENGTH = 12;
export const PASSWORD_MAX_LENGTH = 64;

const SIMBOLOS_REGEX = /[!@#$%^&*()\-_=+\[\]{};:,.?\/]/;

/**
 * Evalúa una contraseña contra la política del panel administrativo.
 * @param {string} password
 * @returns {{length: boolean, uppercase: boolean, lowercase: boolean, number: boolean, symbol: boolean}}
 */
export function evaluarPassword(password = '') {
    const valor = String(password ?? '');

    return {
        length: valor.length >= PASSWORD_MIN_LENGTH && valor.length <= PASSWORD_MAX_LENGTH,
        uppercase: /\p{Lu}/u.test(valor),
        lowercase: /\p{Ll}/u.test(valor),
        number: /\p{N}/u.test(valor),
        symbol: SIMBOLOS_REGEX.test(valor),
    };
}

/**
 * Inicializa la lista de requisitos y el indicador de fortaleza.
 * @param {Document|Element} root
 */
export function initPasswordPolicy(root = document) {
    const input = root.querySelector('[data-password-policy] input[name="password"]');

    if (!input) return;

    const policy = input.closest('[data-password-policy]');
    const requisitos = policy.querySelectorAll('[data-password-requisito]');
    const barra = policy.querySelector('[data-password-fuerza-barra]');
    const texto = policy.querySelector('[data-password-fuerza-texto]');

    const actualizar = () => {
        const checks = evaluarPassword(input.value);

        requisitos.forEach((elemento) => {
            const cumple = Boolean(checks[elemento.dataset.passwordRequisito]);

            elemento.classList.toggle('text-green-600', cumple);
            elemento.classList.toggle('dark:text-green-400', cumple);
            elemento.classList.toggle('text-secondary', !cumple);
        });

        if (!barra || !texto) return;

        if (input.value.length === 0) {
            barra.style.width = '0%';
            barra.className = 'h-full rounded-full transition-all bg-slate-400';
            texto.textContent = 'Ingresa una contraseña para evaluar su fortaleza.';
            texto.className = 'mt-1 text-xs text-secondary';
            return;
        }

        const cumplidos = Object.values(checks).filter(Boolean).length;

        if (cumplidos <= 2) {
            barra.style.width = '33%';
            barra.className = 'h-full rounded-full transition-all bg-red-500';
            texto.textContent = 'Contraseña débil.';
            texto.className = 'mt-1 text-xs text-red-600 dark:text-red-400';
        } else if (cumplidos <= 4) {
            barra.style.width = '66%';
            barra.className = 'h-full rounded-full transition-all bg-yellow-500';
            texto.textContent = 'Contraseña media. Aún faltan requisitos.';
            texto.className = 'mt-1 text-xs text-yellow-600 dark:text-yellow-400';
        } else {
            barra.style.width = '100%';
            barra.className = 'h-full rounded-full transition-all bg-green-500';
            texto.textContent = 'Contraseña fuerte.';
            texto.className = 'mt-1 text-xs text-green-600 dark:text-green-400';
        }
    };

    input.addEventListener('input', actualizar);
    actualizar();
}
