// Feature: guardia-cierre-caja
// Reglas puras de la guardia de salida con caja abierta.

import { describe, it, expect } from 'vitest';
import {
    debeAdvertirSalida,
    esNavegacionInterna,
} from '../../../resources/js/caja/guardia-cierre.js';

describe('debeAdvertirSalida', () => {
    it('advierte solo con caja abierta y sin salida permitida', () => {
        expect(debeAdvertirSalida(true, false)).toBe(true);
        expect(debeAdvertirSalida(true, true)).toBe(false);
        expect(debeAdvertirSalida(false, false)).toBe(false);
        expect(debeAdvertirSalida(false, true)).toBe(false);
    });
});

describe('esNavegacionInterna', () => {
    it('acepta rutas relativas y URLs del mismo origen', () => {
        expect(esNavegacionInterna('/ventas', 'https://panel.test')).toBe(true);
        expect(esNavegacionInterna('https://panel.test/caja', 'https://panel.test')).toBe(true);
    });

    it('rechaza destinos externos y protocolos que no navegan al panel', () => {
        expect(esNavegacionInterna('https://ejemplo.com', 'https://panel.test')).toBe(false);
        expect(esNavegacionInterna('https://panel.test:8443/ventas', 'https://panel.test')).toBe(false);
        expect(esNavegacionInterna('javascript:void(0)', 'https://panel.test')).toBe(false);
        expect(esNavegacionInterna('mailto:admin@panel.test', 'https://panel.test')).toBe(false);
    });

    it('rechaza valores vacíos o inválidos', () => {
        expect(esNavegacionInterna('', 'https://panel.test')).toBe(false);
        expect(esNavegacionInterna('/ventas', '')).toBe(false);
        expect(esNavegacionInterna('http://[::1', 'https://panel.test')).toBe(false);
    });
});
