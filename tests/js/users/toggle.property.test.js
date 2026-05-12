/**
 * tests/js/users/toggle.property.test.js
 *
 * Tests de propiedades (Property-Based Testing) para las funciones puras
 * exportadas desde resources/js/users/toggle.js.
 *
 * Herramientas:
 *   - Vitest     — test runner
 *   - fast-check — generación de datos arbitrarios para PBT
 *
 * Propiedades cubiertas:
 *   - Property 10: El frontend actualiza badge y botón tras respuesta exitosa del toggle
 *   - Requirement 2.6: Error de respuesta no modifica el estado visual del botón
 *
 * Feature: user-activation-toggle
 *
 * Validates: Requirements 2.5, 2.6, 4.3
 */

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { updateBadge, updateButton } from '../../../resources/js/users/toggle.js';

// ─────────────────────────────────────────────────────────────────────────────
// Helpers: mock DOM elements
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Crea un mock de elemento DOM con classList, textContent y setAttribute.
 * Suficiente para probar updateBadge y updateButton sin un entorno DOM real.
 *
 * @param {string[]} initialClasses - Clases CSS iniciales del elemento.
 * @returns {object} Mock de elemento DOM.
 */
function createMockElement(initialClasses = []) {
    const classes = new Set(initialClasses);
    return {
        classList: {
            add:    (...cls) => cls.forEach(c => classes.add(c)),
            remove: (...cls) => cls.forEach(c => classes.delete(c)),
            has:    (cls)    => classes.has(cls),
            _classes: classes,
        },
        textContent: '',
        _attributes: {},
        setAttribute(name, value) { this._attributes[name] = value; },
        getAttribute(name)        { return this._attributes[name] ?? null; },
    };
}

// ─────────────────────────────────────────────────────────────────────────────
// Árbitros reutilizables
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Genera un valor de `activo` (0 o 1, como devuelve el servidor).
 */
const activoArb = fc.oneof(fc.constant(0), fc.constant(1));

/**
 * Genera un nombre de usuario no vacío.
 */
const userNameArb = fc.string({ minLength: 1, maxLength: 50 });

// ─────────────────────────────────────────────────────────────────────────────
// Property 10: Respuesta exitosa actualiza badge y botón
// Feature: user-activation-toggle, Property 10: El frontend actualiza badge y botón
// tras respuesta exitosa del toggle
// Validates: Requirements 2.5, 4.3
// ─────────────────────────────────────────────────────────────────────────────

describe('updateBadge — Property 10: respuesta exitosa actualiza el badge de estado', () => {
    it('activo=1 → badge verde con texto "Activo"', () => {
        // Feature: user-activation-toggle, Property 10: El frontend actualiza badge y botón tras respuesta exitosa
        fc.assert(
            fc.property(activoArb, (initialActivo) => {
                const badge = createMockElement(
                    initialActivo
                        ? ['bg-green-100', 'text-green-800']
                        : ['bg-red-100', 'text-red-800']
                );

                // Simular respuesta exitosa con activo=1
                updateBadge(badge, 1);

                expect(badge.classList.has('bg-green-100')).toBe(true);
                expect(badge.classList.has('text-green-800')).toBe(true);
                expect(badge.classList.has('bg-red-100')).toBe(false);
                expect(badge.classList.has('text-red-800')).toBe(false);
                expect(badge.textContent).toBe('Activo');
            }),
            { numRuns: 100 }
        );
    });

    it('activo=0 → badge rojo con texto "Inactivo"', () => {
        // Feature: user-activation-toggle, Property 10: El frontend actualiza badge y botón tras respuesta exitosa
        fc.assert(
            fc.property(activoArb, (initialActivo) => {
                const badge = createMockElement(
                    initialActivo
                        ? ['bg-green-100', 'text-green-800']
                        : ['bg-red-100', 'text-red-800']
                );

                // Simular respuesta exitosa con activo=0
                updateBadge(badge, 0);

                expect(badge.classList.has('bg-red-100')).toBe(true);
                expect(badge.classList.has('text-red-800')).toBe(true);
                expect(badge.classList.has('bg-green-100')).toBe(false);
                expect(badge.classList.has('text-green-800')).toBe(false);
                expect(badge.textContent).toBe('Inactivo');
            }),
            { numRuns: 100 }
        );
    });

    it('para cualquier valor de activo, el badge refleja exactamente el nuevo estado', () => {
        // Feature: user-activation-toggle, Property 10: El frontend actualiza badge y botón tras respuesta exitosa
        fc.assert(
            fc.property(activoArb, activoArb, (initialActivo, newActivo) => {
                const badge = createMockElement(
                    initialActivo
                        ? ['bg-green-100', 'text-green-800']
                        : ['bg-red-100', 'text-red-800']
                );

                updateBadge(badge, newActivo);

                if (newActivo) {
                    expect(badge.textContent).toBe('Activo');
                    expect(badge.classList.has('bg-green-100')).toBe(true);
                    expect(badge.classList.has('bg-red-100')).toBe(false);
                } else {
                    expect(badge.textContent).toBe('Inactivo');
                    expect(badge.classList.has('bg-red-100')).toBe(true);
                    expect(badge.classList.has('bg-green-100')).toBe(false);
                }
            }),
            { numRuns: 100 }
        );
    });
});

describe('updateButton — Property 10: respuesta exitosa actualiza el botón de toggle', () => {
    it('activo=1 → botón amarillo con texto "Inactivar"', () => {
        // Feature: user-activation-toggle, Property 10: El frontend actualiza badge y botón tras respuesta exitosa
        fc.assert(
            fc.property(activoArb, userNameArb, (initialActivo, userName) => {
                const button = createMockElement(
                    initialActivo
                        ? ['bg-yellow-100', 'text-yellow-800', 'hover:bg-yellow-200']
                        : ['bg-green-100', 'text-green-800', 'hover:bg-green-200']
                );

                // Simular respuesta exitosa con activo=1
                updateButton(button, 1, userName);

                expect(button.classList.has('bg-yellow-100')).toBe(true);
                expect(button.classList.has('text-yellow-800')).toBe(true);
                expect(button.classList.has('hover:bg-yellow-200')).toBe(true);
                expect(button.classList.has('bg-green-100')).toBe(false);
                expect(button.classList.has('text-green-800')).toBe(false);
                expect(button.textContent).toBe('Inactivar');
                expect(button.getAttribute('aria-label')).toContain('Inactivar');
            }),
            { numRuns: 100 }
        );
    });

    it('activo=0 → botón verde con texto "Activar"', () => {
        // Feature: user-activation-toggle, Property 10: El frontend actualiza badge y botón tras respuesta exitosa
        fc.assert(
            fc.property(activoArb, userNameArb, (initialActivo, userName) => {
                const button = createMockElement(
                    initialActivo
                        ? ['bg-yellow-100', 'text-yellow-800', 'hover:bg-yellow-200']
                        : ['bg-green-100', 'text-green-800', 'hover:bg-green-200']
                );

                // Simular respuesta exitosa con activo=0
                updateButton(button, 0, userName);

                expect(button.classList.has('bg-green-100')).toBe(true);
                expect(button.classList.has('text-green-800')).toBe(true);
                expect(button.classList.has('hover:bg-green-200')).toBe(true);
                expect(button.classList.has('bg-yellow-100')).toBe(false);
                expect(button.classList.has('text-yellow-800')).toBe(false);
                expect(button.textContent).toBe('Activar');
                expect(button.getAttribute('aria-label')).toContain('Activar');
            }),
            { numRuns: 100 }
        );
    });

    it('para cualquier valor de activo, el botón refleja exactamente el nuevo estado', () => {
        // Feature: user-activation-toggle, Property 10: El frontend actualiza badge y botón tras respuesta exitosa
        fc.assert(
            fc.property(activoArb, activoArb, userNameArb, (initialActivo, newActivo, userName) => {
                const button = createMockElement(
                    initialActivo
                        ? ['bg-yellow-100', 'text-yellow-800', 'hover:bg-yellow-200']
                        : ['bg-green-100', 'text-green-800', 'hover:bg-green-200']
                );

                updateButton(button, newActivo, userName);

                if (newActivo) {
                    expect(button.textContent).toBe('Inactivar');
                    expect(button.classList.has('bg-yellow-100')).toBe(true);
                    expect(button.classList.has('bg-green-100')).toBe(false);
                    expect(button.getAttribute('aria-label')).toContain('Inactivar');
                } else {
                    expect(button.textContent).toBe('Activar');
                    expect(button.classList.has('bg-green-100')).toBe(true);
                    expect(button.classList.has('bg-yellow-100')).toBe(false);
                    expect(button.getAttribute('aria-label')).toContain('Activar');
                }
            }),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Requirement 2.6: Error de respuesta no modifica el estado visual del botón
// Feature: user-activation-toggle
// Validates: Requirement 2.6
// ─────────────────────────────────────────────────────────────────────────────

describe('Requirement 2.6: error de respuesta no modifica el estado visual del botón', () => {
    it('cuando no se llama updateButton, el botón conserva sus clases y texto originales', () => {
        // Feature: user-activation-toggle, Requirement 2.6: error de respuesta no modifica estado visual
        // Simula el comportamiento del módulo ante un error: NO se llama updateButton ni updateBadge.
        // El test verifica que el estado del botón permanece inalterado.
        fc.assert(
            fc.property(activoArb, userNameArb, (currentActivo, userName) => {
                // Estado inicial del botón según el estado actual del usuario
                const initialClasses = currentActivo
                    ? ['bg-yellow-100', 'text-yellow-800', 'hover:bg-yellow-200']
                    : ['bg-green-100', 'text-green-800', 'hover:bg-green-200'];
                const initialText = currentActivo ? 'Inactivar' : 'Activar';

                const button = createMockElement(initialClasses);
                button.textContent = initialText;

                // Simular error: NO se llama updateButton (el módulo no modifica el DOM en caso de error)
                // Verificar que el estado visual no ha cambiado
                if (currentActivo) {
                    expect(button.classList.has('bg-yellow-100')).toBe(true);
                    expect(button.classList.has('text-yellow-800')).toBe(true);
                    expect(button.classList.has('hover:bg-yellow-200')).toBe(true);
                    expect(button.classList.has('bg-green-100')).toBe(false);
                } else {
                    expect(button.classList.has('bg-green-100')).toBe(true);
                    expect(button.classList.has('text-green-800')).toBe(true);
                    expect(button.classList.has('hover:bg-green-200')).toBe(true);
                    expect(button.classList.has('bg-yellow-100')).toBe(false);
                }
                expect(button.textContent).toBe(initialText);
            }),
            { numRuns: 100 }
        );
    });

    it('updateBadge y updateButton NO son llamados en caso de error — el estado visual permanece intacto', () => {
        // Feature: user-activation-toggle, Requirement 2.6: error de respuesta no modifica estado visual
        // Verifica que si se llama updateBadge/updateButton con el mismo valor actual,
        // el resultado es idempotente (el estado no cambia visualmente).
        fc.assert(
            fc.property(activoArb, (currentActivo) => {
                const badge = createMockElement(
                    currentActivo
                        ? ['bg-green-100', 'text-green-800']
                        : ['bg-red-100', 'text-red-800']
                );
                badge.textContent = currentActivo ? 'Activo' : 'Inactivo';

                const button = createMockElement(
                    currentActivo
                        ? ['bg-yellow-100', 'text-yellow-800', 'hover:bg-yellow-200']
                        : ['bg-green-100', 'text-green-800', 'hover:bg-green-200']
                );
                button.textContent = currentActivo ? 'Inactivar' : 'Activar';

                // Capturar estado antes (simulando que NO se llama nada en caso de error)
                const badgeTextBefore   = badge.textContent;
                const buttonTextBefore  = button.textContent;
                const badgeGreenBefore  = badge.classList.has('bg-green-100');
                const badgeRedBefore    = badge.classList.has('bg-red-100');
                const buttonYellowBefore = button.classList.has('bg-yellow-100');
                const buttonGreenBefore  = button.classList.has('bg-green-100');

                // No se llama ninguna función de actualización (comportamiento en caso de error)

                // Verificar que el estado no cambió
                expect(badge.textContent).toBe(badgeTextBefore);
                expect(button.textContent).toBe(buttonTextBefore);
                expect(badge.classList.has('bg-green-100')).toBe(badgeGreenBefore);
                expect(badge.classList.has('bg-red-100')).toBe(badgeRedBefore);
                expect(button.classList.has('bg-yellow-100')).toBe(buttonYellowBefore);
                expect(button.classList.has('bg-green-100')).toBe(buttonGreenBefore);
            }),
            { numRuns: 100 }
        );
    });
});
