/**
 * tests/js/clientes/index.property.test.js
 *
 * Tests de propiedades para las funciones puras exportadas desde
 * resources/js/clientes/index.js.
 *
 * Propiedades cubiertas (Feature: frecuencia-lavados-cliente):
 *   - Propiedad 1: cada vehículo renderiza su placa y su conteo
 *   - Propiedad 2: lista vacía muestra el mensaje de ausencia
 */

// Feature: frecuencia-lavados-cliente, Property 1 y 2

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { renderVehiculosHTML } from '../../../resources/js/clientes/index.js';

const vehiculoArb = fc.record({
    placa: fc.string({ minLength: 6, maxLength: 7 }),
    lavados: fc.integer({ min: 1, max: 200 }),
});

describe('renderVehiculosHTML — Propiedad 1: placa y conteo por vehículo', () => {
    it('cada vehículo aparece con su placa y su número de lavados', () => {
        fc.assert(
            fc.property(fc.array(vehiculoArb, { minLength: 1, maxLength: 8 }), (vehiculos) => {
                const html = renderVehiculosHTML(vehiculos);
                for (const v of vehiculos) {
                    expect(html).toContain(v.placa);
                    expect(html).toContain(`${v.lavados}`);
                }
            }),
            { numRuns: 100 }
        );
    });
});

describe('renderVehiculosHTML — Propiedad 2: lista vacía', () => {
    it('sin vehículos muestra el mensaje de ausencia', () => {
        expect(renderVehiculosHTML([])).toContain('Sin vehículos registrados.');
    });
});
