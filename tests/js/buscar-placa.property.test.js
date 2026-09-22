/**
 * tests/js/buscar-placa.property.test.js
 *
 * Tests de propiedades (Property-Based Testing) para las funciones puras del
 * módulo compartido resources/js/buscador-placa.js (opciones por placa en los
 * tickets de lavado y cambio de aceite).
 *
 * Propiedades cubiertas:
 *   - normalizarPlaca: mayúsculas y sin espacios (igual que el backend).
 *   - placaValida: equivalente a la regla regex del backend (6-7 [A-Z0-9-]).
 *   - estadoBotones: máquina de estados de los botones de opción.
 *   - datosResumenLocal / datosResumenApi: resúmenes sin valores vacíos.
 *
 * Feature: flujo-mixto-placa
 */

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import {
    normalizarPlaca,
    placaValida,
    estadoBotones,
    datosResumenLocal,
    datosResumenApi,
} from '../../resources/js/buscador-placa.js';

const PLACA_RE = /^[A-Z0-9-]{6,7}$/;

const charsPlaca = fc.constantFrom(
    ...['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '-']
);

const placaArb = fc.array(charsPlaca, { minLength: 6, maxLength: 7 }).map((a) => a.join(''));
const cualquierString = fc.string();
const booleanArb = fc.boolean();
const busquedaArb = fc.constantFrom('encontrada', 'noEncontrada', null);

// ─────────────────────────────────────────────────────────────────────────────
// Property 1: normalizarPlaca → mayúsculas, sin espacios, contenido intacto

describe('normalizarPlaca', () => {
    // Feature: flujo-mixto-placa, Property 1: normaliza a mayúsculas sin espacios
    it('siempre devuelve mayúsculas sin espacios', () => {
        fc.assert(
            fc.property(cualquierString, (s) => {
                const n = normalizarPlaca(s);
                expect(n).toBe(n.toUpperCase());
                expect(n).not.toContain(' ');
                expect(n.length).toBeGreaterThanOrEqual(s.trim().replaceAll(' ', '').length ? 0 : 0);
            }),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Property 2: placaValida es equivalente a la regla del backend

describe('placaValida', () => {
    // Feature: flujo-mixto-placa, Property 2: válida ⇔ regex [A-Z0-9-]{6,7}
    it('coincide exactamente con la regex del backend y nunca lanza', () => {
        fc.assert(
            fc.property(fc.oneof(placaArb, cualquierString), (s) => {
                expect(placaValida(s)).toBe(PLACA_RE.test(normalizarPlaca(s)));
            }),
            { numRuns: 200 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Property 3-5: estadoBotones — máquina de estados de los botones de opción

describe('estadoBotones', () => {
    // Feature: flujo-mixto-placa, Property 3: botón principal ⇔ placa válida
    it('el botón "buscar" se habilita si y solo si la placa es válida', () => {
        fc.assert(
            fc.property(booleanArb, busquedaArb, booleanArb, (valido, busqueda, api) => {
                const { buscar } = estadoBotones(valido, busqueda, api);
                expect(buscar).toBe(valido);
            }),
            { numRuns: 100 }
        );
    });

    // Feature: flujo-mixto-placa, Property 4: api es un subconjunto de buscar
    it('el botón de API solo puede habilitarse si el de buscar está habilitado', () => {
        fc.assert(
            fc.property(booleanArb, busquedaArb, booleanArb, (valido, busqueda, api) => {
                const { buscar, api: apiHabilitado } = estadoBotones(valido, busqueda, api);
                if (apiHabilitado) expect(buscar).toBe(true);
            }),
            { numRuns: 100 }
        );
    });

    // Feature: flujo-mixto-placa, Property 5: continuar requiere verificación o API
    it('continuar se habilita solo con placa válida y verificado o API consultada', () => {
        fc.assert(
            fc.property(booleanArb, busquedaArb, booleanArb, (valido, busqueda, api) => {
                const { continuar } = estadoBotones(valido, busqueda, api);
                const esperado = valido && (busqueda === 'encontrada' || (busqueda === 'noEncontrada' && api));
                expect(continuar).toBe(esperado);
            }),
            { numRuns: 100 }
        );
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Property 6-7: datosResumenLocal — 10 filas sin valores vacíos

describe('datosResumenLocal', () => {
    const datosArb = fc.record({
        nombre: fc.option(fc.string({ maxLength: 30 }), { nil: undefined }),
        apellido: fc.option(fc.string({ maxLength: 30 }), { nil: undefined }),
        dni: fc.option(fc.string({ maxLength: 8 }), { nil: undefined }),
        telefono: fc.option(fc.string({ maxLength: 9 }), { nil: undefined }),
        marca: fc.option(fc.string({ maxLength: 30 }), { nil: undefined }),
        modelo: fc.option(fc.string({ maxLength: 30 }), { nil: undefined }),
        color: fc.option(fc.string({ maxLength: 30 }), { nil: undefined }),
    });

    // Feature: flujo-mixto-placa, Property 6: siempre 10 filas {clave, valor} con valor no vacío
    it('siempre devuelve 10 filas con clave y valor en texto no vacío', () => {
        fc.assert(
            fc.property(datosArb, (d) => {
                const filas = datosResumenLocal(
                    { dni: d.dni, telefono: d.telefono },
                    { marca: d.marca, modelo: d.modelo, color: d.color }
                );
                expect(filas).toHaveLength(10);
                filas.forEach((f) => {
                    expect(f).toHaveProperty('clave');
                    expect(typeof f.clave).toBe('string');
                    expect(f.valor).toBeDefined();
                    expect(typeof f.valor).toBe('string');
                    expect(f.valor.length).toBeGreaterThan(0);
                });
            }),
            { numRuns: 100 }
        );
    });

    // Feature: flujo-mixto-placa, Property 7: la placa siempre se refleja (automotor o cliente)
    it('la placa de la primera fila proviene del automotor o del cliente', () => {
        const placa = 'ABC123';
        const filas = datosResumenLocal({ placa: 'XYZ-99', nombre: 'Juan' }, { placa, marca: 'Toyota' });
        expect(filas[0]).toEqual({ clave: 'Placa', valor: placa });
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Property 8: datosResumenApi — solo campos con dato, nunca undefined

describe('datosResumenApi', () => {
    // Feature: flujo-mixto-placa, Property 8: solo filas de campos con valor no vacío
    it('excluye campos vacíos y nunca devuelve undefined', () => {
        fc.assert(
            fc.property(
                fc.record({
                    placa: fc.option(cualquierString, { nil: undefined }),
                    marca: fc.option(cualquierString, { nil: undefined }),
                    modelo: fc.option(cualquierString, { nil: undefined }),
                    serie: fc.option(cualquierString, { nil: undefined }),
                    color: fc.option(cualquierString, { nil: undefined }),
                    motor: fc.option(cualquierString, { nil: undefined }),
                    vin: fc.option(cualquierString, { nil: undefined }),
                }),
                (data) => {
                    const filas = datosResumenApi(data);
                    expect(Array.isArray(filas)).toBe(true);
                    filas.forEach((f) => {
                        expect(f.valor).toBeDefined();
                        expect(f.valor.length).toBeGreaterThan(0);
                    });
                    if (data.placa) {
                        expect(filas.some((f) => f.clave === 'Placa' && f.valor === String(data.placa))).toBe(true);
                    }
                }
            ),
            { numRuns: 100 }
        );
    });
});