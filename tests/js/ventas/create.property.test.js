/**
 * tests/js/ventas/create.property.test.js
 *
 * Tests de propiedades (Property-Based Testing) para las funciones puras
 * exportadas desde resources/js/ventas/create.js.
 *
 * Herramientas:
 *   - Vitest      — test runner
 *   - fast-check  — generación de datos arbitrarios para PBT
 *
 * Propiedades cubiertas (Feature: ventas-panel-alerta-stock-bajo):
 *   - Propiedad 1: renderTablaHTML renderiza el badge "Stock bajo" únicamente
 *                  cuando el item está en alerta de stock
 *   - Propiedad 2: el badge "Stock bajo" aparece a la izquierda del input de
 *                  cantidad dentro de la fila
 *   - Propiedad 3: el input de cantidad conserva min/max/value correctos
 *                  independientemente del estado de alerta
 *
 * Requisitos: ventas (panel de venta) — vendedor informado del stock bajo
 */

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { renderTablaHTML } from '../../../resources/js/ventas/create.js';

// ─────────────────────────────────────────────────────────────────────────────
// Árbitros reutilizables
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Genera un item de producto con el estado de alerta incluido.
 */
const itemArb = fc.record({
    nombre: fc.string({ maxLength: 40 }),
    cantidad: fc.integer({ min: 1, max: 100 }),
    precio_unitario: fc.double({ min: 0.01, max: 10_000, noNaN: true }),
    subtotal: fc.double({ min: 0.01, max: 10_000, noNaN: true }),
    stock: fc.integer({ min: 1, max: 500 }),
    stock_bajo: fc.boolean(),
});

/**
 * Genera un array de 1 a 10 items.
 */
const itemsArb = fc.array(itemArb, { minLength: 1, maxLength: 10 });

function badgeEsperado(item) {
    return item.stock_bajo;
}

function filaDelItem(html, item) {
    return html;
}

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 1: el badge aparece si y solo si el item está en alerta
// ─────────────────────────────────────────────────────────────────────────────

describe('renderTablaHTML — Propiedad 1: badge "Stock bajo" iff stock_bajo', () => {
    it('la fila renderiza data-stock-badge (y el texto Stock bajo) exactamente cuando stock_bajo es true', () => {
        fc.assert(
            fc.property(itemArb, (item) => {
                const html = renderTablaHTML([item]);

                expect(html.includes('data-stock-badge')).toBe(badgeEsperado(item));
                expect(html.includes('Stock bajo')).toBe(badgeEsperado(item));
            }),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 2: el badge precede al input de cantidad (queda a la izquierda)
// ─────────────────────────────────────────────────────────────────────────────

describe('renderTablaHTML — Propiedad 2: el badge queda a la izquierda del input', () => {
    it('cuando stock_bajo es true, data-stock-badge aparece antes que el input type="number"', () => {
        fc.assert(
            fc.property(itemArb, (item) => {
                fc.pre(item.stock_bajo);

                const html   = filaDelItem(renderTablaHTML([item]), item);
                const badge  = html.indexOf('data-stock-badge');
                const input  = html.indexOf('type="number"');

                expect(badge).toBeGreaterThanOrEqual(0);
                expect(input).toBeGreaterThan(badge);
            }),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 3: el input conserva min/max/value correctos en ambos estados
// ─────────────────────────────────────────────────────────────────────────────

describe('renderTablaHTML — Propiedad 3: el input de cantidad conserva min/max/value', () => {
    it('el input type="number" incluye min="1", max=stock y value=cantidad con o sin badge', () => {
        fc.assert(
            fc.property(itemArb, (item) => {
                const html = renderTablaHTML([item]);

                expect(html).toContain(`min="1"`);
                expect(html).toContain(`max="${item.stock}"`);
                expect(html).toContain(`value="${item.cantidad}"`);
            }),
            { numRuns: 100 }
        );
    });
});