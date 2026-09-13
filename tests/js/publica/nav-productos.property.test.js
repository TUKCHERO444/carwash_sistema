/**
 * tests/js/publica/nav-productos.property.test.js
 *
 * Property-Based Testing para las funciones puras de
 * resources/js/publica/nav-productos-core.js (doble funcionalidad del
 * enlace "Productos" del navbar público: click navega, press-and-hold abre
 * el submenú de categorías).
 *
 * Propiedades cubiertas:
 *   - Propiedad 1: la clasificación binaria (menu/navigate) coincide con la
 *     comparación contra el umbral para cualquier duración válida
 *   - Propiedad 2: en el borde exacto (holdMs === umbral) siempre abre menú
 *   - Propiedad 3: monotonía — una vez que una duración abre el menú, toda
 *     duración mayor también lo abre
 *
 * Requisitos: navbar Productos doble funcionalidad + vista de productos filtrados.
 */

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { LONG_PRESS_MS, getReleaseAction } from '../../../resources/js/publica/nav-productos-core.js';

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 1: clasificación binaria correcta para toda duración válida
// ─────────────────────────────────────────────────────────────────────────────

describe('getReleaseAction — Propiedad 1: clasifica según el umbral', () => {
    it('holdMs >= umbral => "menu"; holdMs < umbral => "navigate"', () => {
        fc.assert(
            fc.property(
                fc.integer({ min: 0, max: 60_000 }),
                (holdMs) => {
                    const esperado = holdMs >= LONG_PRESS_MS ? 'menu' : 'navigate';
                    expect(getReleaseAction(holdMs)).toBe(esperado);
                }
            ),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 2: borde exacto (holdMs === umbral) abre el menú
// ─────────────────────────────────────────────────────────────────────────────

describe('getReleaseAction — Propiedad 2: el borde exacto abre el menú', () => {
    it('holdMs === LONG_PRESS_MS => "menu"', () => {
        expect(getReleaseAction(LONG_PRESS_MS)).toBe('menu');
        expect(getReleaseAction(LONG_PRESS_MS - 1)).toBe('navigate');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 3: monotonía de la acción al crecer la duración
// ─────────────────────────────────────────────────────────────────────────────

describe('getReleaseAction — Propiedad 3: la acción es monótona', () => {
    it('si a abre el menú, toda duración b >= a también lo abre', () => {
        fc.assert(
            fc.property(
                fc.integer({ min: 0, max: 60_000 }),
                fc.integer({ min: 0, max: 60_000 }),
                (a, b) => {
                    fc.pre(a <= b);

                    if (getReleaseAction(a) === 'menu') {
                        expect(getReleaseAction(b)).toBe('menu');
                    }

                    // Contraposición: si b navega (short press), a <= b
                    // también navega (no puede haber abierto el menú).
                    if (getReleaseAction(b) === 'navigate') {
                        expect(getReleaseAction(a)).toBe('navigate');
                    }
                }
            ),
            { numRuns: 100 }
        );
    });
});