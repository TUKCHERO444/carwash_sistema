# Plan de implementaciÃ³n: devoluciones-ventas

## DescripciÃ³n general

GestiÃ³n formal de **devoluciones parciales** y **cambios** (devoluciÃ³n + nueva venta vinculada) como operaciÃ³n independiente de la anulaciÃ³n total. No se toca `VentaController@destroy`, `CajaService::calcularResumen` ni `PermissionSeeder`. Se agregan 2 tablas, 1 valor a `KardexService::FUENTES` + CHECK PG, 2 modelos, 1 controlador + 4 rutas + 3 vistas + 1 mÃ³dulo JS, y registro en auditorÃ­a.

Convenciones obligatorias: espaÃ±ol total; `paginate(10)`; `th py-6 / td py-8`; wrapper `overflow-x-auto`; clases `.bg-surface/.input-main/.label-main/.text-primary/.text-secondary/.border-main/.divide-main` (slate, nunca negro puro); `now()`/Carbon (`America/Lima`); rutas estÃ¡ticas antes del resource; JS en `resources/js/devoluciones/create.js` + `input` en `vite.config.js` + `@vite` (sin `<script>` de comportamiento en Blade); `vendor/bin/pint`; tests con `RefreshDatabase` y permiso `acceso-ventas` creado antes de `givePermissionTo`.

---

## Tareas

- [ ] 1. MigraciÃ³n `create_devoluciones_venta_table`
  - Crear `devoluciones_venta` (correlativo unique `DEV-XXXX`, `venta_id` restrict, `venta_nueva_id` nullable nullOnDelete, `caja_id` nullable set null, `tipo` devolucion/cambio, `motivo` 500, `total_devuelto` decimal 10,2, `user_id` restrict, timestamps) y `detalle_devoluciones_venta` (`devolucion_id` cascade, `detalle_venta_id` restrict, `producto_id` restrict, `cantidad`, `precio_unitario`, `subtotal`, timestamps).
  - En el mismo `up()`: ampliar CHECK PG de `movimientos_kardex.fuente` con `'devolucion'` (patrÃ³n migraciÃ³n `2026_09_27_090000`); `down()` revierte CHECK y dropea tablas.
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 3.2_

  - [ ]* 1.1 Test de smoke â€” tablas y CHECK existen tras migrar
    - Verificar columnas de ambas tablas y que `KardexService::fuentesValidas()` incluye `devolucion`.
    - **Property 3: Stock espejo con fuente devolucion**
    - **Validates: Requirements 1.1, 3.1, 3.2**

- [x] 2. Modelos `DevolucionVenta` y `DetalleDevolucionVenta`
  - Crear `app/Models/DevolucionVenta.php` (fillable/casts/relaciones Â§Design-2) y `app/Models/DetalleDevolucionVenta.php` (table `detalle_devoluciones_venta`); agregar a `Venta`: `devoluciones(): HasMany` + helper `disponiblePorDetalle()`.
  - _Requirements: 5.1, 8.1_

  - [ ]* 2.1 Test de propiedades â€” relaciones y venta intacta
    - Crear venta + devoluciÃ³n y verificar relaciones; verificar venta/detalles idÃ©nticos tras devolver.
    - **Property 1: La venta original nunca se modifica ni se elimina**
    - **Validates: Requirements 5.1, 9.4**

- [x] 3. Fuente Kardex `devolucion`
  - Agregar `'devolucion' => 'DevoluciÃ³n'` a `KardexService::FUENTES` y actualizar docblocks de `registrarEntrada/registrarSalida`. Sin mÃ©todos nuevos.
  - _Requirements: 3.1, 3.3, 3.4_

- [ ] 4. Registro en auditorÃ­a
  - `AuditServiceProvider::$modelos` += `DevolucionVenta`; `AuditService::AUDITABLE` + `modulos['ventas']`; `DetalleDevolucionVenta` queda fuera (como `DetalleVenta`); `AccionesAuditoriaController` += `'registrar devoluciÃ³n'`, `'registrar cambio'`.
  - _Requirements: 10.1, 10.2_

  - [ ]* 4.1 Test â€” auditorÃ­a completa
    - Registrar devoluciÃ³n y cambio; verificar fila `registros_auditoria` (mÃ³dulo, acciÃ³n, actor, motivo, totales).
    - **Property 8: AuditorÃ­a completa**
    - **Validates: Requirements 10.1, 10.2, 10.3**

- [ ] 5. `DevolucionVentaController@create` + vista de selecciÃ³n
  - `create(Venta)`: load `detalles.producto + devoluciones.detalles`; calcular `vendida/devuelta/disponible` por lÃ­nea; vista `ventas/devoluciones/create.blade.php` (init `window.venta = @json` + `@vite`, modal `error_caja`, formulario lÃ­neas + motivo + tipo + sustituto vÃ­a `ventas.buscar-productos`).
  - Crear `resources/js/devoluciones/create.js` (tope `disponible`, total informativo, validaciÃ³n cliente) y aÃ±adirlo al `input` de `vite.config.js`.
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 11.4_

- [ ] 6. `DevolucionVentaController@store` (devoluciÃ³n simple)
  - Exigir caja (`error_caja` si no hay); validar Â§Design-5; transacciÃ³n con locks ASC: verificar pertenencia y tope por lÃ­nea, recalcular `total_devuelto` en servidor, crear `DEV-XXXX` + lÃ­neas, `increment` + `registrarEntrada('devolucion', DEV)`, egreso(s) prorrateados segÃºn mapeo Â§Design-4 con descripciÃ³n `DEV/VTA`, `anotarAccion('registrar devoluciÃ³n')`.
  - _Requirements: 5.1, 5.2, 5.3, 6.1, 6.2, 6.3, 7.1, 7.2, 7.3, 7.4, 7.5, 12.1, 12.2_

  - [ ]* 6.1 Test de propiedades â€” tope por lÃ­nea
    - 100+ iter: `q > disponible` â†’ 302 sin persistencia; `q = disponible` â†’ Ã©xito.
    - **Property 2: Tope por lÃ­nea**
    - **Validates: Requirements 5.3, 12.1, 12.2**

  - [ ]* 6.2 Test de propiedades â€” kardex espejo e inventario intacto
    - Verificar `stock +q`, 1 fila `entrada/devolucion/DEV` por lÃ­nea con `antes/despues`, `inventario` igual.
    - **Property 3: Stock espejo con fuente devolucion**
    - **Validates: Requirements 6.1, 6.2**

  - [ ]* 6.3 Test de propiedades â€” egresos segÃºn mÃ©todo de pago
    - Matriz `efectivo/yape/izipay/mixto`: suma = T, `tipo_pago` mapeado, descripciÃ³n con correlativos.
    - **Property 4: Egreso compensatorio segÃºn mÃ©todo de pago**
    - **Validates: Requirements 7.2, 7.3, 7.4**

  - [ ]* 6.4 Test de propiedades â€” sin caja no hay operaciÃ³n
    - Sin caja abierta â†’ `assertSessionHas('error_caja')` + cero filas nuevas.
    - **Property 9: Sin caja no hay operaciÃ³n**
    - **Validates: Requirements 7.1**

- [x] 7. Cambio de producto (misma transacciÃ³n)
  - Si `tipo=cambio`: lock + validar sustituto activo con stock; crear `VTA-YYYY` (correlativo `max(id)+1`, `caja_id`, `user_id`); `decrement` + `registrarSalida('devolucion', DEV)`; `venta_nueva_id = VTA-YYYY`; `anotarAccion('registrar cambio')`; diferencia `total_nuevo âˆ’ total_devuelto` documentada en `show`.
  - _Requirements: 8.1, 8.2, 8.3_

  - [ ]* 7.1 Test de propiedades â€” cambio vinculado y atomicidad
    - `tipo=cambio` â†’ vÃ­nculo + 2 movimientos mismo `DEV`; sustituto sin stock â†’ cero persistencia en las 5 tablas.
    - **Property 5: Atomicidad total â€” Property 6: Cambio = devoluciÃ³n + venta vinculada**
    - **Validates: Requirements 8.1, 8.3, 12.2**

- [x] 8. Rutas `devoluciones.*` (estÃ¡ticas antes del resource, grupo `acceso-ventas`, sin permiso nuevo)
  - `GET /ventas/{venta}/devoluciones/create`, `POST /ventas/{venta}/devoluciones`, `GET /devoluciones`, `GET /devoluciones/{devolucion}`. Sin `edit/update/destroy`.
  - _Requirements: 11.1_

- [x] 9. Vistas `devoluciones.index|show` + integraciÃ³n en `ventas.show/index`
  - `index` (`paginate(10)`, columnas correlativo/venta/tipo/motivo/total/usuario/fecha, `py-6/py-8`, `overflow-x-auto`); `show` (trazabilidad completa + venta nueva si cambio); `ventas.show`: botÃ³n "Devolver / Cambiar" + secciÃ³n historial y disponibles por lÃ­nea; `ventas.index` sin cambios (la venta sigue listada).
  - _Requirements: 9.2, 9.3, 9.4_

  - [ ]* 9.1 Test â€” diferenciaciÃ³n devoluciÃ³n vs anulaciÃ³n
    - `DELETE` existente borra con su mensaje; devoluciÃ³n conserva la venta y usa mensaje propio.
    - **Property 7: AnulaciÃ³n intacta y diferenciada**
    - **Validates: Requirements 9.1, 9.2, 9.4**

- [ ] 10. Checkpoint final â€” Pint + `composer run test` (+ `npm run test` si se tocÃ³ JS)
  - Verificar que `destroy` de ventas, `KardexTest`, caja y reportes siguen en verde; confirmar mensajes en espaÃ±ol y zona `America/Lima` con tiempos relativos.

---

## Notas

- Las tareas `*` son property/smoke tests (100+ iteraciones, tag `// Feature: devoluciones-ventas, Property N:`) y pueden diferirse para un MVP, salvo 6.1 (tope) y 6.4 (caja), que son crÃ­ticos.
- Prohibido: `EgresoCaja::create` o `DB::table('movimientos_kardex')->insert` directos en el controlador; `update` a `ventas/detalle_ventas`; `date()` crudo; permiso nuevo; tocar `destroy`, `calcularResumen` o `inventario`.
- Caso borde mixto: redondear cada egreso a 2 decimales y ajustar la diferencia en el Ãºltimo (la suma debe ser exactamente `total_devuelto`).

