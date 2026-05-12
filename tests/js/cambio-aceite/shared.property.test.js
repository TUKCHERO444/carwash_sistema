/**
 * tests/js/cambio-aceite/shared.property.test.js
 *
 * Tests de propiedades (Property-Based Testing) para las funciones puras
 * exportadas desde resources/js/cambio-aceite/shared.js.
 *
 * Herramientas:
 *   - Vitest  — test runner
 *   - fast-check — generación de datos arbitrarios para PBT
 *
 * Propiedades cubiertas:
 *   - Propiedad 1: calcularTotal con porcentaje 0 devuelve la suma exacta de los totales de línea
 *   - Propiedad 2: calcularTotal con descuento nunca supera el precio base (suma sin descuento)
 *   - Propiedad 3: calcularTotal con porcentaje 100 devuelve 0
 *   - Propiedad 4: calcularTotal clampea porcentajes fuera de [0, 100]
 *
 * Requisitos: 1.7
 */

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { calcularTotal } from '../../../resources/js/cambio-aceite/shared.js';

// ─────────────────────────────────────────────────────────────────────────────
// Árbitros reutilizables
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Genera un item de producto con `total` positivo (hasta 2 decimales).
 */
const itemArb = fc.record({
    total: fc.double({ min: 0.01, max: 10_000, noNaN: true }),
});

/**
 * Genera un array de 1 a 20 items de producto.
 */
const itemsArb = fc.array(itemArb, { minLength: 1, maxLength: 20 });

/**
 * Genera un porcentaje de descuento válido en [0, 100].
 */
const porcentajeArb = fc.double({ min: 0, max: 100, noNaN: true });

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 1: sin descuento, el total es la suma de los totales de línea
// ─────────────────────────────────────────────────────────────────────────────

describe('calcularTotal — Propiedad 1: sin descuento devuelve la suma de líneas', () => {
    it('calcularTotal(items, 0) ≈ sum(items.map(i => i.total)) con tolerancia de redondeo 0.005', () => {
        fc.assert(
            fc.property(itemsArb, (items) => {
                const sumaEsperada = items.reduce((acc, i) => acc + i.total, 0);
                const resultado    = calcularTotal(items, 0);

                // toFixed(2) puede redondear hasta 0.005 por encima o por debajo
                expect(Math.abs(resultado - sumaEsperada)).toBeLessThanOrEqual(0.005);
            }),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 2: con descuento, el total nunca supera el precio base redondeado
// ─────────────────────────────────────────────────────────────────────────────

describe('calcularTotal — Propiedad 2: el total con descuento nunca supera el precio base', () => {
    it('calcularTotal(items, pct) <= calcularTotal(items, 0) + 0.005', () => {
        fc.assert(
            fc.property(itemsArb, porcentajeArb, (items, pct) => {
                // Comparamos contra el precio base ya redondeado (pct=0) para
                // que ambos valores pasen por el mismo toFixed(2) y la comparación
                // sea justa ante el redondeo de punto flotante.
                const precioBase = calcularTotal(items, 0);
                const resultado  = calcularTotal(items, pct);

                expect(resultado).toBeLessThanOrEqual(precioBase + 0.005);
            }),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 3: descuento del 100 % siempre da 0
// ─────────────────────────────────────────────────────────────────────────────

describe('calcularTotal — Propiedad 3: descuento del 100 % devuelve 0', () => {
    it('calcularTotal(items, 100) === 0', () => {
        fc.assert(
            fc.property(itemsArb, (items) => {
                const resultado = calcularTotal(items, 100);
                expect(resultado).toBe(0);
            }),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 4: porcentajes fuera de [0, 100] se clampean correctamente
// ─────────────────────────────────────────────────────────────────────────────

describe('calcularTotal — Propiedad 4: porcentajes fuera de rango se clampean', () => {
    it('porcentaje negativo se trata como 0 (sin descuento)', () => {
        fc.assert(
            fc.property(
                itemsArb,
                fc.double({ min: -1000, max: -0.01, noNaN: true }),
                (items, pctNegativo) => {
                    const sinDescuento = calcularTotal(items, 0);
                    const conNegativo  = calcularTotal(items, pctNegativo);
                    expect(Math.abs(conNegativo - sinDescuento)).toBeLessThanOrEqual(0.001);
                }
            ),
            { numRuns: 100 }
        );
    });

    it('porcentaje > 100 se trata como 100 (descuento total)', () => {
        fc.assert(
            fc.property(
                itemsArb,
                fc.double({ min: 100.01, max: 10_000, noNaN: true }),
                (items, pctExcesivo) => {
                    const resultado = calcularTotal(items, pctExcesivo);
                    expect(resultado).toBe(0);
                }
            ),
            { numRuns: 100 }
        );
    });
});
