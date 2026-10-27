// VUL-11: política de contraseñas del panel administrativo.
// La validación definitiva está en el servidor; estas pruebas cubren el
// evaluador usado por el formulario para mostrar requisitos y fortaleza.

import { describe, expect, it } from 'vitest';
import * as fc from 'fast-check';
import {
    evaluarPassword,
    PASSWORD_MAX_LENGTH,
    PASSWORD_MIN_LENGTH,
} from '../../../resources/js/users/password-policy.js';

describe('VUL-11 - evaluarPassword', () => {
    it('acepta una contraseña que cumple todos los requisitos', () => {
        expect(evaluarPassword('M4r!posaAzul27')).toEqual({
            length: true,
            uppercase: true,
            lowercase: true,
            number: true,
            symbol: true,
        });
    });

    it('exige el mínimo y el máximo de longitud', () => {
        expect(evaluarPassword('Aa1!'.repeat(2)).length).toBe(false);
        expect(evaluarPassword('Aa1!'.repeat(PASSWORD_MIN_LENGTH / 4)).length).toBe(true);
        expect(evaluarPassword('Aa1!'.repeat(PASSWORD_MAX_LENGTH / 4 + 1)).length).toBe(false);
    });

    it('detecta cada categoría por separado', () => {
        expect(evaluarPassword('abcdef12345!')).toMatchObject({ uppercase: false, lowercase: true });
        expect(evaluarPassword('ABCDEF12345!')).toMatchObject({ uppercase: true, lowercase: false });
        expect(evaluarPassword('Abcdefghijk!')).toMatchObject({ number: false, symbol: true });
        expect(evaluarPassword('Abcdef123456')).toMatchObject({ number: true, symbol: false });
    });

    it('no considera el espacio como símbolo permitido', () => {
        expect(evaluarPassword('Abcdef12345 ')).toMatchObject({ number: true, symbol: false });
    });

    it('para cualquier texto, una contraseña válida respeta la longitud configurada', () => {
        fc.assert(
            fc.property(fc.string(), (texto) => {
                const resultado = evaluarPassword(texto);

                if (Object.values(resultado).every(Boolean)) {
                    expect(texto.length).toBeGreaterThanOrEqual(PASSWORD_MIN_LENGTH);
                    expect(texto.length).toBeLessThanOrEqual(PASSWORD_MAX_LENGTH);
                }
            }),
            { numRuns: 200 }
        );
    });
});
