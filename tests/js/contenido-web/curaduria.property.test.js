/**
 * tests/js/contenido-web/curaduria.property.test.js
 *
 * Tests de propiedades (Property-Based Testing) del reparto 2-1-2 del mosaico
 * de categorías de productos (resources/js/contenido-web/reparto.js).
 *
 * Feature: contenido-web
 *
 * Validates: Requisitos 6.2, 6.3, 6.5 (reparto de columnas sin perder/duplicar)
 */

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { repartir } from '../../../resources/js/contenido-web/reparto.js';

/**
 * Representación del multiset de una lista (JSON ordenado) para comparar
 * sin importar el orden.
 */
function multiset(lista) {
    return JSON.stringify([...lista].sort((a, b) => a - b));
}

// ─────────────────────────────────────────────────────────────────────────────
// Property: el reparto 2-1-2 preserva el multiset de categorías
// Feature: contenido-web, Property: reparto 2-1-2 preserva el multiset
// ─────────────────────────────────────────────────────────────────────────────

describe('repartir — Property: multiset preservado tras el reparto 2-1-2', () => {
    it('Para cualquier lista de categorías, el aplanado es exactamente el mismo multiset', () => {
        // Feature: contenido-web, Property: reparto 2-1-2 preserva el multiset
        fc.assert(
            fc.property(fc.array(fc.integer()), (items) => {
                const { izquierda, centro, derecha } = repartir(items);
                const plana = [...izquierda, ...centro, ...derecha];

                expect(multiset(plana)).toBe(multiset(items));
            }),
            { numRuns: 100 }
        );
    });

    it('Para cualquier lista, la entrada no es mutada', () => {
        // Feature: contenido-web, Property: reparto no muta la entrada
        fc.assert(
            fc.property(fc.array(fc.integer()), (items) => {
                const original = [...items];

                repartir(items);

                expect(items).toEqual(original);
            }),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Property: en grupos completos de 5 el reparto es exactamente 2-1-2
// Feature: contenido-web, Property: grupos de 5 se reparten 2-1-2
// ─────────────────────────────────────────────────────────────────────────────

describe('repartir — Property: cada grupo completo de 5 se reparte 2-1-2', () => {
    it('Para cualquier lista de 5 elementos, cae 2 en izquierda, 1 en centro y 2 en derecha', () => {
        // Feature: contenido-web, Property: grupos de 5 se reparten 2-1-2
        fc.assert(
            fc.property(fc.array(fc.integer(), { minLength: 5, maxLength: 5 }), (grupo) => {
                const { izquierda, centro, derecha } = repartir(grupo);

                expect(izquierda).toHaveLength(2);
                expect(centro).toHaveLength(1);
                expect(derecha).toHaveLength(2);
            }),
            { numRuns: 100 }
        );
    });
});