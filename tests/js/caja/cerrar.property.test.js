/**
 * tests/js/caja/cerrar.property.test.js
 *
 * Tests de propiedades para las funciones puras exportadas desde
 * resources/js/caja/cerrar.js.
 *
 * Propiedades cubiertas (Feature: arqueo-cierre-caja):
 *   - Propiedad 1: diferencia = contado − esperado a 2 decimales
 *   - Propiedad 2: etiqueta Sobrante / Faltante / Coincide
 */

// Feature: arqueo-cierre-caja, Property 1 y 2

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { calcularDiferencia, etiquetaDiferencia } from '../../../resources/js/caja/cerrar.js';

describe('calcularDiferencia — Propiedad 1: contado − esperado a 2 decimales', () => {
    it('la diferencia coincide con la resta redondeada', () => {
        fc.assert(
            fc.property(
                fc.double({ min: 0, max: 100_000, noNaN: true }),
                fc.double({ min: 0, max: 100_000, noNaN: true }),
                (contado, esperado) => {
                    expect(calcularDiferencia(contado, esperado)).toBe(+(contado - esperado).toFixed(2));
                }
            ),
            { numRuns: 100 }
        );
    });
});

describe('etiquetaDiferencia — Propiedad 2: Sobrante / Faltante / Coincide', () => {
    it('signo positivo es sobrante, negativo faltante y cero coincide', () => {
        fc.assert(
            fc.property(fc.double({ min: -1000, max: 1000, noNaN: true }), (d) => {
                const redondeada = +d.toFixed(2);
                const esperada = redondeada > 0 ? 'Sobrante' : (redondeada < 0 ? 'Faltante' : 'Coincide');
                expect(etiquetaDiferencia(redondeada)).toBe(esperada);
            }),
            { numRuns: 100 }
        );
    });
});
