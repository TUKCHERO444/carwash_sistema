// Feature: compras-ingreso-mercaderia
// Property tests for compras create.js pure functions
// Uses fast-check for property-based testing (100+ iterations)

import { describe, it, expect, test } from 'vitest';
import * as fc from 'fast-check';
import { calcularTotal, calcularSubtotal, sincronizarHiddens } from '../../../resources/js/compras/create.js';

describe('compras/create.js - Property tests', () => {
    // Property 1: calcularTotal returns exact sum of subtotals
    // For any array of items with numeric subtotal, total === sum of subtotals
    test('Property 1: calcularTotal es suma exacta de subtotales', () => {
        fc.assert(
            fc.property(fc.array(fc.record({
                subtotal: fc.float({ min: 0, max: 1e6, noNaN: true, noDefaultInfinity: true }),
            }), { minLength: 1, maxLength: 50 }), (items) => {
                const total = calcularTotal(items);
                const expected = items.reduce((acc, i) => acc + i.subtotal, 0);
                // Both rounded to 2 decimals
                expect(Math.abs(total - Math.round(expected * 100) / 100)).toBeLessThan(0.001);
            }),
            { numRuns: 200 }
        );
    });

    // Property 2: calcularTotal with empty array returns 0
    test('Property 2: calcularTotal con array vacío retorna 0', () => {
        expect(calcularTotal([])).toBe(0);
    });

    // Property 3: calcularSubtotal = cantidad × costo_unitario exacto
    // For any positive integers/decimals, subtotal === round(cantidad * costo, 2)
    test('Property 3: calcularSubtotal es producto exacto redondeado', () => {
        fc.assert(
            fc.property(
                fc.integer({ min: 1, max: 10000 }),
                fc.float({ min: 0, max: 1e6, noNaN: true, noDefaultInfinity: true }),
                (cantidad, costo) => {
                    const subtotal = calcularSubtotal(cantidad, costo);
                    const expected = Math.round(cantidad * costo * 100) / 100;
                    expect(Math.abs(subtotal - expected)).toBeLessThan(0.001);
                }
            ),
            { numRuns: 200 }
        );
    });

    // Property 4: calcularSubtotal with zero cantidad returns 0
    test('Property 4: calcularSubtotal con cantidad 0 retorna 0', () => {
        fc.assert(
            fc.property(fc.float({ min: 0, max: 1e6 }), (costo) => {
                expect(calcularSubtotal(0, costo)).toBe(0);
            }),
            { numRuns: 100 }
        );
    });

    // Property 5: calcularSubtotal with zero costo returns 0
    test('Property 5: calcularSubtotal con costo 0 retorna 0', () => {
        fc.assert(
            fc.property(fc.integer({ min: 1, max: 10000 }), (cantidad) => {
                expect(calcularSubtotal(cantidad, 0)).toBe(0);
            }),
            { numRuns: 100 }
        );
    });

    // Property 6: sincronizarHiddens - skipped in Node env (requires DOM)
    // test('Property 6: sincronizarHiddens crea inputs ocultos correctos', () => { ... });

    // Property 7: sincronizarHiddens removes previous inputs - skipped in Node env (requires DOM)
    // test('Property 7: sincronizarHiddens elimina inputs previos', () => { ... });

    // Property 8: total is invariant under reordering of items
    test('Property 8: total es invariante bajo reordenación', () => {
        fc.assert(
            fc.property(fc.array(fc.record({
                subtotal: fc.float({ min: 0, max: 1e6, noNaN: true }),
            }), { minLength: 2, maxLength: 10 }), (items) => {
                const total1 = calcularTotal(items);
                const shuffled = [...items].sort(() => Math.random() - 0.5);
                const total2 = calcularTotal(shuffled);
                expect(total1).toBe(total2);
            }),
            { numRuns: 200 }
        );
    });

    // Property 9: subtotal is always >= 0
    test('Property 9: subtotal nunca es negativo', () => {
        fc.assert(
            fc.property(
                fc.integer({ min: 0, max: 10000 }),
                fc.float({ min: 0, max: 1e6, noNaN: true }),
                (cantidad, costo) => {
                    expect(calcularSubtotal(cantidad, costo)).toBeGreaterThanOrEqual(0);
                }
            ),
            { numRuns: 200 }
        );
    });

    // Property 10: total is always >= 0
    test('Property 10: total nunca es negativo', () => {
        fc.assert(
            fc.property(fc.array(fc.record({
                subtotal: fc.float({ min: 0, max: 1e6, noNaN: true }),
            }), { minLength: 0, maxLength: 20 }), (items) => {
                expect(calcularTotal(items)).toBeGreaterThanOrEqual(0);
            }),
            { numRuns: 200 }
        );
    });
});