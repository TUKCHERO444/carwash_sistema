/**
 * tests/js/publica/animaciones.property.test.js
 *
 * Property-Based Testing para las funciones puras de
 * resources/js/publica/animaciones.js (animaciones de entrada del sitio
 * público: keyframes CSS + IntersectionObserver sin librerías).
 *
 * Propiedades cubiertas:
 *   - Propiedad 1: el retraso escalonado está acotado en [0, maxMs] para
 *     cualquier índice no negativo y cualquier configuración válida
 *   - Propiedad 2: el retraso es monótono no decreciente respecto al índice
 *   - Propiedad 3: respeta un maxMs personalizado (tope override)
 *   - Propiedad 4: en el borde (índice grande) queda capado en maxMs
 *   - Propiedad 5: la activación solo ocurre con observador y sin
 *     movimiento reducido (true ⇔ soporta && !reduce)
 *   - Propiedad 6: solo las variantes up|fade|left|right son válidas
 *
 * Feature: animaciones-publica (docs/design.md §13).
 */

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import {
    OPCIONES_ANIMACION,
    VARIANTES,
    calcularRetraso,
    debeAnimar,
    esVarianteValida,
} from '../../../resources/js/publica/animaciones.js';

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 1: retraso acotado en [0, maxMs]
// ─────────────────────────────────────────────────────────────────────────────
// Feature: animaciones-publica, Property 1: retraso acotado

describe('calcularRetraso — Propiedad 1: acotado en [0, maxMs]', () => {
    it('0 <= retraso <= maxMs para cualquier índice y opciones válidas', () => {
        fc.assert(
            fc.property(
                fc.integer({ min: -10_000, max: 10_000 }),
                fc.integer({ min: 0, max: 60_000 }),
                fc.integer({ min: 0, max: 60_000 }),
                (indice, pasoMs, maxMs) => {
                    const retraso = calcularRetraso(indice, { pasoMs, maxMs });
                    expect(retraso).toBeGreaterThanOrEqual(0);
                    expect(retraso).toBeLessThanOrEqual(maxMs);
                }
            ),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 2: monótona no decreciente respecto al índice
// ─────────────────────────────────────────────────────────────────────────────
// Feature: animaciones-publica, Property 2: retraso monótono

describe('calcularRetraso — Propiedad 2: monótono no decreciente', () => {
    it('si a <= b entonces retraso(a) <= retraso(b)', () => {
        fc.assert(
            fc.property(
                fc.integer({ min: -100, max: 10_000 }),
                fc.integer({ min: -100, max: 10_000 }),
                fc.integer({ min: 0, max: 5_000 }),
                fc.integer({ min: 0, max: 5_000 }),
                (a, b, pasoMs, maxMs) => {
                    fc.pre(a <= b);
                    const opciones = { pasoMs, maxMs };
                    expect(calcularRetraso(a, opciones)).toBeLessThanOrEqual(
                        calcularRetraso(b, opciones)
                    );
                }
            ),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 3: respeta un maxMs personalizado (tope override)
// ─────────────────────────────────────────────────────────────────────────────
// Feature: animaciones-publica, Property 3: tope personalizado

describe('calcularRetraso — Propiedad 3: respeta el maxMs dado', () => {
    it('nunca supera maxMs aun con índice y paso enormes', () => {
        fc.assert(
            fc.property(
                fc.integer({ min: 0, max: 1_000_000 }),
                fc.integer({ min: 1, max: 60_000 }),
                fc.integer({ min: 0, max: 60_000 }),
                (indice, pasoMs, maxMs) => {
                    const retraso = calcularRetraso(indice, { pasoMs, maxMs });
                    expect(retraso).toBeLessThanOrEqual(maxMs);

                    const esperado = Math.min(indice * pasoMs, maxMs);
                    expect(retraso).toBe(esperado);
                }
            ),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 4: en el borde el retraso queda capado en maxMs
// ─────────────────────────────────────────────────────────────────────────────
// Feature: animaciones-publica, Property 4: borde capado

describe('calcularRetraso — Propiedad 4: capa en maxMs', () => {
    it('con índice >= ceil(maxMs/paso) el resultado es exactamente maxMs', () => {
        fc.assert(
            fc.property(
                fc.integer({ min: 1, max: 1_000_000 }),
                fc.integer({ min: 0, max: 60_000 }),
                (pasoMs, maxMs) => {
                    fc.pre(pasoMs > 0);
                    const indice = Math.ceil(maxMs / pasoMs);
                    expect(calcularRetraso(indice, { pasoMs, maxMs })).toBe(maxMs);
                }
            ),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 5: activación ⇔ observador && !movimiento reducido
// ─────────────────────────────────────────────────────────────────────────────
// Feature: animaciones-publica, Property 5: gate de activación

describe('debeAnimar — Propiedad 5: solo con observador y sin reduced-motion', () => {
    it('debeAnimar es true si y solo si soporta === true y prefiereReduccion === false', () => {
        fc.assert(
            fc.property(
                fc.boolean(),
                fc.boolean(),
                (soportaObservador, prefiereReduccion) => {
                    const esperado = soportaObservador && !prefiereReduccion;
                    expect(debeAnimar({ soportaObservador, prefiereReduccion })).toBe(esperado);
                }
            ),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Propiedad 6: solo las variantes up|fade|left|right son válidas
// ─────────────────────────────────────────────────────────────────────────────
// Feature: animaciones-publica, Property 6: variantes válidas

describe('esVarianteValida — Propiedad 6: solo up|fade|left|right', () => {
    it('es válida exactamente para las variantes del sistema', () => {
        for (const variante of VARIANTES) {
            expect(esVarianteValida(variante)).toBe(true);
        }

        fc.assert(
            fc.property(
                fc.string(),
                (texto) => {
                    expect(esVarianteValida(texto)).toBe(VARIANTES.includes(texto));
                }
            ),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Valores por defecto coherentes con publica.css (§13.4) y el tamanio del stagger
// ─────────────────────────────────────────────────────────────────────────────

describe('OPCIONES_ANIMACION — invariantes por defecto', () => {
    it('pasoMs >= 1 y maxMs >= pasoMs', () => {
        expect(OPCIONES_ANIMACION.pasoMs).toBeGreaterThanOrEqual(1);
        expect(OPCIONES_ANIMACION.maxMs).toBeGreaterThanOrEqual(OPCIONES_ANIMACION.pasoMs);
    });

    it('con valores por defecto el retraso nunca supera maxMs', () => {
        fc.assert(
            fc.property(fc.integer({ min: 0, max: 10_000 }), (indice) => {
                expect(calcularRetraso(indice)).toBeLessThanOrEqual(OPCIONES_ANIMACION.maxMs);
            }),
            { numRuns: 100 }
        );
    });
});