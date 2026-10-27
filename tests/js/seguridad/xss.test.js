// Feature: seguridad, VUL-04: XSS almacenado en inventario y compras
// Verifica que los valores de la base de datos se escapan antes de entrar en
// templates que se asignan a innerHTML.

import { describe, expect, it } from 'vitest';
import * as fc from 'fast-check';
import { escapeHtml } from '../../../resources/js/utils/escape-html.js';
import { renderTablaHTML as renderVentas } from '../../../resources/js/ventas/create.js';
import { renderTablaHTML as renderCambioAceite } from '../../../resources/js/cambio-aceite/shared.js';
import { renderTablaHTML as renderAjustes } from '../../../resources/js/ajustes/create.js';
import { renderTablaHTML as renderComprasCreate } from '../../../resources/js/compras/create.js';
import { renderTablaHTML as renderComprasEdit } from '../../../resources/js/compras/edit.js';

const XSS_IMG = '<img src=x onerror=alert(1)>';
const XSS_SCRIPT = '"><script>alert(1)</script>';

describe('VUL-04 - escapeHtml', () => {
    it('neutraliza los metacaracteres HTML', () => {
        expect(escapeHtml(XSS_IMG)).toBe('&lt;img src=x onerror=alert(1)&gt;');
        expect(escapeHtml(XSS_SCRIPT)).toBe('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;');
        expect(escapeHtml('Producto`onclick=alert(1)')).toContain('&#096;');
    });

    it('para cualquier texto, la salida no contiene etiquetas ni comillas sin escapar', () => {
        fc.assert(
            fc.property(fc.string(), (texto) => {
                const escapado = escapeHtml(texto);

                expect(escapado).not.toContain('<');
                expect(escapado).not.toContain('>');
                expect(escapado).not.toContain('"');
                expect(escapado).not.toContain("'");
                expect(escapado).not.toContain('`');
            }),
            { numRuns: 200 }
        );
    });
});

describe('VUL-04 - tablas dinámicas escapan nombres almacenados', () => {
    it('ventas no inserta el payload como HTML', () => {
        const html = renderVentas([{
            nombre: XSS_IMG,
            cantidad: 1,
            precio_unitario: 10,
            subtotal: 10,
            stock: 5,
            stock_bajo: false,
        }]);

        expect(html).not.toContain('<img');
        expect(html).toContain('&lt;img src=x onerror=alert(1)&gt;');
    });

    it('cambio de aceite no inserta el payload como HTML', () => {
        const html = renderCambioAceite([{
            nombre: XSS_SCRIPT,
            cantidad: 1,
            precio: 10,
            total: 10,
            stock: 5,
        }]);

        expect(html).not.toContain('<script>');
        expect(html).toContain('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;');
    });

    it('ajustes escapa los nombres de las opciones de producto', () => {
        const html = renderAjustes(
            [{ producto_id: 1, nombre: XSS_IMG, stock: 5, cantidad: 1, tipo_linea: 'entrada' }],
            [{ id: 1, nombre: XSS_IMG, stock: 5 }],
            'entrada'
        );

        expect(html).not.toContain('<img');
        expect(html).toContain('&lt;img src=x onerror=alert(1)&gt;');
    });

    it('compras (create y edit) escapa el nombre del producto en el atributo value', () => {
        const items = [{
            producto_id: 1,
            nombre: XSS_SCRIPT,
            cantidad: 1,
            costo_unitario: 10,
            subtotal: 10,
        }];
        const productos = [{ id: 1, nombre: XSS_SCRIPT }];

        for (const html of [renderComprasCreate(items, productos), renderComprasEdit(items, productos)]) {
            expect(html).not.toContain('<script>');
            expect(html).toContain('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;');
        }
    });
});
