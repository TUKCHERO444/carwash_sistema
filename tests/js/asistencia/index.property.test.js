/**
 * tests/js/asistencia/index.property.test.js
 *
 * Tests de propiedades (PBT) para las funciones puras del calendario de
 * asistencia exportadas desde resources/js/asistencia/index.js.
 *
 * Herramientas:
 *   - Vitest     — test runner
 *   - fast-check — generación de datos arbitrarios (Árbitros) para PBT
 *
 * Feature: asistencia
 * Propiedades cubiertas:
 *   - Property 6: buildMonthGrid cubre exactamente el mes
 *   - Property 7: la celda del día 1 coincide con su día de semana (áncora lunes)
 *   - Property 8: parseFechaKey(fechaKey(...)) es un round-trip reversible
 *
 * Valida: Requisitos 2.2, 4
 */

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import {
    INICIO_SEMANA,
    buildMonthGrid,
    claseDia,
    esFechaPasadaOActual,
    fechaKey,
    parseFechaKey,
} from '../../../resources/js/asistencia/index.js';

// ─────────────────────────────────────────────────────────────────────────────
// Árbitros reutilizables
// ─────────────────────────────────────────────────────────────────────────────

/** Año de calendario razonable para pruebas. */
const yearArb = fc.integer({ min: 2000, max: 2035 });

/** Mes del calendario 1..12. */
const monthArb = fc.integer({ min: 1, max: 12 });

/**
 * Día del mes garantizado válido en cualquier mes (1..28) y una clave opcional
 * para probar el round-trip con días límite.
 */
const dayValidArb = fc.integer({ min: 1, max: 28 });

const fechaArb = fc.record({
    year: yearArb,
    month: monthArb,
    day: dayValidArb,
});

// ─────────────────────────────────────────────────────────────────────────────
// Property 6: La cuadrícula cubre exactamente el mes
// Feature: asistencia, Property 6: La cuadrícula cubre exactamente el mes
// Valida: Requisitos 2.2
// ─────────────────────────────────────────────────────────────────────────────

describe('buildMonthGrid — Property 6: la cuadrícula cubre exactamente el mes', () => {
    it('para cualquier (year, month), devuelve 42 celdas con los días exactos del mes', () => {
        // Feature: asistencia, Property 6: La cuadrícula cubre exactamente el mes (round-trip)
        fc.assert(
            fc.property(fechaArb, ({ year, month }) => {
                const grid = buildMonthGrid(year, month);

                // 6 semanas exactas
                expect(grid.length).toBe(42);

                const dias = grid.filter((celda) => celda !== null);
                const diasEnMes = new Date(year, month, 0).getDate();

                // Exactamente los días del mes, sin duplicados ni omisiones
                expect(dias).toHaveLength(diasEnMes);
                const numeros = dias.map((c) => c.day);
                expect(new Set(numeros).size).toBe(diasEnMes);
                expect(numeros).toEqual(Array.from({ length: diasEnMes }, (_, i) => i + 1));

                // Celdas nulas solo en los bordes (delante y detrás): los N días
                // ocupan N posiciones consecutivas sin huecos.
                const offsetInicio = grid.findIndex((c) => c !== null);
                const noNulos = grid.map((c, i) => (c ? i : null)).filter((i) => i !== null);
                expect(noNulos).toEqual(
                    Array.from({ length: diasEnMes }, (_, i) => offsetInicio + i),
                );

                // Las celdas conservan el año y mes solicitados
                dias.forEach((celda) => {
                    expect(celda.year).toBe(year);
                    expect(celda.month).toBe(month);
                });
            }),
            { numRuns: 100 },
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Property 7: La celda del día 1 coincide con su día de semana (áncora lunes)
// Feature: asistencia, Property 7: La celda del día 1 coincide con su día de semana
// Valida: Requisitos 2.2
// ─────────────────────────────────────────────────────────────────────────────

describe('buildMonthGrid — Property 7: el día 1 cae en la celda de su día de semana', () => {
    it('para cualquier (year, month), la primera celda no nula es el día 1 en la columna correcta', () => {
        // Feature: asistencia, Property 7: La celda del día 1 coincide con su día de semana (áncora lunes)
        fc.assert(
            fc.property(fechaArb, ({ year, month }) => {
                const grid = buildMonthGrid(year, month);

                // Número de celdas vacías al inicio según el día de la semana,
                // calculado de forma independiente (lunes = 0 para comparar con INICIO_SEMANA)
                const dowISO = (new Date(year, month - 1, 1).getDay() + 6) % 7;
                const offsetEsperado = (dowISO - (INICIO_SEMANA - 1) + 7) % 7;

                const primeraNoNula = grid.findIndex((c) => c !== null);
                expect(primeraNoNula).toBe(offsetEsperado);
                expect(grid[primeraNoNula]).toEqual({ year, month, day: 1 });
            }),
            { numRuns: 100 },
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Property 8: parseFechaKey(fechaKey(...)) es un round-trip reversible
// Feature: asistencia, Property 8: FechaKey es un biyectivo reversible
// Valida: Requisitos 2.2, 4
// ─────────────────────────────────────────────────────────────────────────────

describe('fechaKey / parseFechaKey — Property 8: round-trip reversible', () => {
    it('para cualquier (year, month, day) válido, parseFechaKey(fechaKey(...)) devuelve el triple original', () => {
        // Feature: asistencia, Property 8: FechaKey es un biyectivo reversible (round-trip)
        fc.assert(
            fc.property(fechaArb, ({ year, month, day }) => {
                const key = fechaKey(year, month, day);

                // Formato canónico YYYY-MM-DD
                expect(key).toMatch(/^\d{4}-\d{2}-\d{2}$/);

                expect(parseFechaKey(key)).toEqual({ year, month, day });
            }),
            { numRuns: 100 },
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Comportamientos adicionales deterministas
// ─────────────────────────────────────────────────────────────────────────────

describe('claseDia', () => {
    it('clasifica completo/parcial/nulo/sin-datos según los conteos', () => {
        // Feature: asistencia, clase de indicador por día (Requisito 3)
        expect(claseDia(3, 3)).toBe('completo');
        expect(claseDia(1, 3)).toBe('parcial');
        expect(claseDia(0, 3)).toBe('nulo');
        expect(claseDia(0, 0)).toBe('sin-datos');
        expect(claseDia(2, 0)).toBe('sin-datos');
        expect(claseDia(3, 3, 'ignorado')).toBe('completo');
    });
});

describe('esFechaPasadaOActual', () => {
    it('compara claves ISO de forma lexicográfica', () => {
        // Feature: asistencia, consultabilidad de días (Requisito 2.4, 4)
        expect(esFechaPasadaOActual('2026-09-12', '2026-09-12')).toBe(true);
        expect(esFechaPasadaOActual('2026-09-11', '2026-09-12')).toBe(true);
        expect(esFechaPasadaOActual('2026-10-01', '2026-09-12')).toBe(false);
    });
});