/**
 * tests/js/reportes/operativo.property.test.js
 *
 * Tests de propiedades (PBT) para las funciones puras de
 * resources/js/reportes/operativo.js (gráficas del reporte operativo).
 *
 * Feature: dashboard-reportes-operativo
 * Propiedades cubiertas:
 *   - Property 10: etiquetaDia formatea claves ISO sin romper valores límite
 *   - Property 11: etiquetaHora siempre devuelve "HH:00" con dos dígitos
 *
 * Valida: horas pico y serie diaria del reporte operativo.
 */

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import { etiquetaDia, etiquetaHora } from '../../../resources/js/reportes/operativo.js';

/** Claves ISO 'YYYY-MM-DD' con fechas reales. */
const fechaKeyArb = fc.date({ min: new Date('2000-01-01'), max: new Date('2035-12-31') })
    .map((d) =>
        `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}-${String(d.getUTCDate()).padStart(2, '0')}`,
    );

describe('etiquetaDia — Property 10: label legible para cualquier clave ISO', () => {
    it('para cualquier clave YYYY-MM-DD devuelve un texto corto no vacío', () => {
        // Feature: dashboard-reportes-operativo, Property 10: etiquetaDia formatea claves ISO
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
});

describe('etiquetaHora — Property 11: formato HH:00 para las 24 horas', () => {
    it('para cualquier hora 0-23 devuelve "HH:00" con dos dígitos', () => {
        // Feature: dashboard-reportes-operativo, Property 11: etiquetaHora con formato HH:00
        fc.assert(
            fc.property(fc.integer({ min: 0, max: 23 }), (hora) => {
                expect(etiquetaHora(hora)).toBe(`${String(hora).padStart(2, '0')}:00`);
            }),
            { numRuns: 100 },
        );
    });

    it('valores límite deterministas', () => {
        // Feature: dashboard-reportes-operativo, Property 11: valores límite
        expect(etiquetaHora(0)).toBe('00:00');
        expect(etiquetaHora(9)).toBe('09:00');
        expect(etiquetaHora(23)).toBe('23:00');
    });
});
