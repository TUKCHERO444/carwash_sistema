/**
 * tests/js/devoluciones/create.property.test.js
 *
 * Tests de propiedades para las funciones puras exportadas desde
 * resources/js/devoluciones/create.js.
 *
 * Propiedades cubiertas (Feature: devoluciones-ventas):
 *   - Propiedad 1: el total devuelto es la suma cantidad × precio redondeada a 2 decimales
 *   - Propiedad 2: cantidades inválidas (negativas, nulas) aportan 0
 */

// Feature: devoluciones-ventas, Property 1: total recalculado cantidad × precio original

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { calcularTotalDevuelto } from '../../../resources/js/devoluciones/create.js';

const lineaArb = fc.record({
    cantidad: fc.integer({ min: 0, max: 50 }),
    precio_unitario: fc.double({ min: 0.01, max: 10_000, noNaN: true }),
});

describe('calcularTotalDevuelto — Propiedad 1: suma cantidad × precio a 2 decimales', () => {
    it('el total coincide con la suma redondeada', () => {
        fc.assert(
            fc.property(fc.array(lineaArb, { minLength: 1, maxLength: 10 }), (lineas) => {
                const esperado = +lineas
                    .reduce((acc, l) => acc + l.cantidad * l.precio_unitario, 0)
                    .toFixed(2);
                expect(calcularTotalDevuelto(lineas)).toBe(esperado);
            }),
            { numRuns: 100 }
        );
    });
});

describe('calcularTotalDevuelto — Propiedad 2: cantidades inválidas aportan 0', () => {
    it('líneas con cantidad negativa, nula o sin precio no alteran el total', () => {
        fc.assert(
            fc.property(fc.array(lineaArb, { minLength: 1, maxLength: 5 }), (base) => {
                const totalBase = calcularTotalDevuelto(base);
                const ruido = [
                    { cantidad: -3, precio_unitario: 99.99 },
                    { cantidad: null, precio_unitario: 50 },
                    { cantidad: 2 },
                ];
                expect(calcularTotalDevuelto([...base, ...ruido])).toBe(
                    +(totalBase + 0).toFixed(2)
                );
            }),
            { numRuns: 100 }
        );
    });
});
