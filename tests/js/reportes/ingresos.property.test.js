/**
 * tests/js/reportes/ingresos.property.test.js
 *
 * Tests de propiedades (PBT) para las funciones puras de recursos/js/reportes/ingresos.js
 * (gráficas de ingresos del reporte de ingresos y método de pago).
 *
 * Herramientas:
 *   - Vitest     — test runner
 *   - fast-check — generación de datos arbitrarios (Árbitros) para PBT
 *
 * Feature: reportes
 * Propiedades cubiertas:
 *   - Property 8: actividadDominante es consistente (fuente ≥ 50% ⇔ dominante)
 *   - Property 9: etiquetaDia formatea claves ISO sin romper valores límite
 *
 * Valida: Requisitos 6.3
 */

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { actividadDominante, etiquetaDia } from '../../../resources/js/reportes/ingresos.js';

// ─────────────────────────────────────────────────────────────────────────────
// Árbitros reutilizables
// ─────────────────────────────────────────────────────────────────────────────

/** Tripla de ingresos por fuente (valores no negativos, límite excluido). */
const diaArb = fc.record({
    ventas: fc.nat(),
    lavados: fc.nat(),
    cambios: fc.nat(),
});

/** Claves ISO 'YYYY-MM-DD' con fechas reales (2 dígitos en día y mes). */
const fechaKeyArb = fc.date({ min: new Date('2000-01-01'), max: new Date('2035-12-31') })
    .map((d) =>
        `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}-${String(d.getUTCDate()).padStart(2, '0')}`,
    );

// ─────────────────────────────────────────────────────────────────────────────
// Property 8: actividadDominante
// Feature: reportes, Property 8: la actividad dominante es la fuente ≥ 50%
// Valida: Requisitos 6.3
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Reimplementación independiente de la regla: devuelve la fuente cuya
 * participación supere el 50% (estricto), con empates resueltos como en la
 * implementación (la primera con mayor valor; 0.5 exacto cuenta como dominante).
 */
function dominanteEsperado(dia) {
    const total = dia.ventas + dia.lavados + dia.cambios;
    if (total <= 0) return null;

    const claves = ['ventas', 'lavados', 'cambios'];
    let mayor = claves[0];
    let mayorValor = dia[claves[0]] / total;
    for (let i = 1; i < claves.length; i++) {
        const razon = dia[claves[i]] / total;
        if (razon > mayorValor) {
            mayor = claves[i];
            mayorValor = razon;
        }
    }

    return mayorValor >= 0.5 ? mayor : null;
}

describe('actividadDominante — Property 8: consistente con el criterio del 50%', () => {
    it('para cualquier tripla de ingresos, el resultado coincide con la regla (≥ 50% ⇔ dominante)', () => {
        // Feature: reportes, Property 8: actividadDominante es consistente (fuente ≥ 50% ⇔ dominante)
        fc.assert(
            fc.property(diaArb, (dia) => {
                const resultado = actividadDominante(dia);
                const esperado = dominanteEsperado(dia);

                expect(resultado).toBe(esperado);

                if (resultado === null) {
                    expect(esperado).toBeNull();
                } else {
                    expect(dia[resultado] / (dia.ventas + dia.lavados + dia.cambios)).toBeGreaterThanOrEqual(0.5);
                }
            }),
            { numRuns: 100 },
        );
    });

    it('valores límite deterministas: empate al 50% y fuente única', () => {
        // Feature: reportes, Property 8: actividadDominante en valores límite
        expect(actividadDominante({ ventas: 50, lavados: 50, cambios: 0 })).toBe('ventas');
        expect(actividadDominante({ ventas: 0, lavados: 50, cambios: 50 })).toBe('lavados');
        expect(actividadDominante({ ventas: 0, lavados: 0, cambios: 50 })).toBe('cambios');
        expect(actividadDominante({ ventas: 49, lavados: 51, cambios: 0 })).toBe('lavados');
        expect(actividadDominante({ ventas: 0, lavados: 0, cambios: 0 })).toBeNull();
    });

    it('entradas no numéricas devuelven null sin lanzar', () => {
        // Feature: reportes, robustez ante datos malformados (Requisito 6.3)
        expect(actividadDominante(null)).toBeNull();
        expect(actividadDominante({ ventas: NaN, lavados: 10, cambios: 10 })).toBeNull();
        expect(actividadDominante({ ventas: 10, lavados: 'x', cambios: 10 })).toBeNull();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Property 9: etiquetaDia
// Feature: reportes, Property 9: etiquetaDia formatea claves ISO en valores límite
// Valida: Requisitos 6.3, 18
// ─────────────────────────────────────────────────────────────────────────────

describe('etiquetaDia — Property 9: label legible para cualquier clave ISO', () => {
    it('para cualquier clave YYYY-MM-DD devuelve un texto corto no vacío', () => {
        // Feature: reportes, Property 9: etiquetaDia formatea claves ISO (valores límite)
        fc.assert(
            fc.property(fechaKeyArb, (clave) => {
                const etiqueta = etiquetaDia(clave);

                expect(typeof etiqueta).toBe('string');
                expect(etiqueta.length).toBeGreaterThan(0);
                expect(etiqueta.length).toBeLessThanOrEqual(8);
                expect(etiqueta).toMatch(/^\d{2} [a-z]{3}$/);
            }),
            { numRuns: 100 },
        );
    });

    it('valores límite deterministas (meses de un dígito y extremos del año)', () => {
        // Feature: reportes, Property 9: etiquetaDia con valores límite deterministas
        expect(etiquetaDia('2000-01-01')).toContain('ene');
        expect(etiquetaDia('2035-12-31')).toContain('dic');
    });
});