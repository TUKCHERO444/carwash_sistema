/**
 * tests/js/servicios/toggle-status.property.test.js
 *
 * Tests de propiedades (Property-Based Testing) para las funciones puras
 * del módulo compartido resources/js/toggle-status.js.
 *
 * Propiedades cubiertas:
 *   - updateBadge asigna clases/texto consistentes con `activo`.
 *   - updateButton alterna el texto entre "Activar"/"Inactivar" y el
 *     aria-label correspondiente al `tipo` del registro.
 *
 * Feature: servicios-web
 *
 * Validates: Requirements 8.2, 8.3
 */

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { updateBadge, updateButton } from '../../../resources/js/toggle-status.js';

// ─────────────────────────────────────────────────────────────────────────────
// Helpers: mock DOM elements
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Crea un mock de elemento DOM con classList, textContent, setAttribute y
 * getAttribute. Suficiente para probar updateBadge/updateButton sin un DOM real.
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

const activoArb = fc.boolean();
const nombreArb = fc.string({ minLength: 1, maxLength: 50 });
const tipoArb = fc.constantFrom('servicio', 'producto', 'trabajador', 'usuario');

// ─────────────────────────────────────────────────────────────────────────────
// Property: updateBadge es consistente con activo
// Feature: servicios-web, Property 1: updateBadge asigna clases/texto consistentes
// ─────────────────────────────────────────────────────────────────────────────

describe('updateBadge — Property: clases y texto consistentes con activo', () => {
    it('Para cualquier activo, el badge queda verde con "Activo" o rojo con "Inactivo"', () => {
        // Feature: servicios-web, Property 1: updateBadge asigna clases/texto consistentes
        fc.assert(
            fc.property(activoArb, (activo) => {
                const badge = createMockElement(['bg-red-100', 'text-red-700']);

                updateBadge(badge, activo);

                expect(badge.textContent).toBe(activo ? 'Activo' : 'Inactivo');
                expect(badge.classList.has(activo ? 'bg-green-100' : 'bg-red-100')).toBe(true);
                expect(badge.classList.has(activo ? 'bg-red-100' : 'bg-green-100')).toBe(false);
                expect(badge.classList.has(activo ? 'text-green-800' : 'text-red-700')).toBe(true);
                expect(badge.classList.has(activo ? 'text-red-700' : 'text-green-800')).toBe(false);
            })
        );
    });

    it('Activo=1 aplica el set verde completo y Activo=0 el rojo completo', () => {
        const verde = ['bg-green-100', 'dark:bg-green-900/30', 'text-green-800', 'dark:text-green-400'];
        const rojo = ['bg-red-100', 'dark:bg-red-900/30', 'text-red-700', 'dark:text-red-400'];

        const badgeActivo = createMockElement();
        updateBadge(badgeActivo, true);
        verde.forEach(cls => expect(badgeActivo.classList.has(cls)).toBe(true));

        const badgeInactivo = createMockElement();
        updateBadge(badgeInactivo, false);
        rojo.forEach(cls => expect(badgeInactivo.classList.has(cls)).toBe(true));
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Property: updateButton alterna texto y aria-label según activo/tipo
// Feature: servicios-web, Property 2: updateButton alterna "Activar"/"Inactivar"
// y aria-label correspondiente
// ─────────────────────────────────────────────────────────────────────────────

describe('updateButton — Property: texto y aria-label consistentes con activo y tipo', () => {
    it('Para cualquier activo/nombre/tipo, el botón queda coherente', () => {
        // Feature: servicios-web, Property 2: updateButton alterna texto y aria-label
        fc.assert(
            fc.property(activoArb, nombreArb, tipoArb, (activo, nombre, tipo) => {
                const button = createMockElement();

                updateButton(button, activo, nombre, tipo);

                const accion = activo ? 'Inactivar' : 'Activar';
                expect(button.textContent).toBe(accion);
                expect(button.getAttribute('aria-label')).toBe(`${accion} ${tipo} ${nombre}`.trim());

                expect(button.classList.has(activo ? 'bg-yellow-100' : 'bg-green-100')).toBe(true);
            })
        );
    });

    it('El tipo por defecto es "producto" (compatibilidad con listados existentes)', () => {
        const button = createMockElement();

        updateButton(button, false, 'Test', undefined);

        expect(button.getAttribute('aria-label')).toBe('Activar producto Test');
    });
});