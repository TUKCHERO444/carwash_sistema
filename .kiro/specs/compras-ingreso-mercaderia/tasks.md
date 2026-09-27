# Tareas — Compras e Ingreso de Mercadería

**Feature:** `compras-ingreso-mercaderia`
**Alcance:** Fases 0 a 3
**Documento de trabajo:** `docs/plan-compras-ingreso-mercaderia.md`

Leyenda: `[ ]` pendiente · `[~]` en curso · `[x]` completada

---

## Fase 0 — Preparación

- [x] **0.1** Spec de la feature: `requirements.md`, `design.md`, `tasks.md`
  - Requisitos 1 a 71, decisiones D1 a D8, invariantes y límites de alcance.
- [x] **0.2** Tests de caracterización, escritos en rojo antes de tocar la validación
  - `tests/Feature/ProductoDesacoplamientoInventarioTest.php`, 15 casos.
  - [x] `update_ignora_stock_e_inventario_enviados` (requisito 4 y 5).
  - [x] `update_no_exige_stock_ni_inventario`.
  - [x] `update_no_deja_alterar_el_stock_a_cero`.
  - [x] `vista_edicion_no_expone_campos_de_cantidad`.
  - [x] `store_acepta_precio_compra_cero` (requisito 8).
  - [x] `store_acepta_precio_compra_ausente`.
  - [x] `store_acepta_precio_compra_vacio`.
  - [x] `store_sin_precio_compra_solo_exige_precio_venta_positivo` (requisito 10).
  - [x] `store_sin_precio_compra_rechaza_precio_venta_no_positivo`.
  - [x] `store_conserva_margen_cuando_hay_precio_compra` (requisito 9).
  - [x] `store_acepta_venta_igual_al_costo`.
  - [x] `store_rechaza_precio_compra_negativo`.
  - [x] `update_acepta_precio_compra_cero`.
  - [x] `update_rechaza_precio_venta_inferior_cuando_hay_costo`.
  - [x] `precio_compra_ausente_se_persiste_como_cero_y_no_como_nulo` (D2).
- [x] **0.3** `ProductoController::update` — `stock` e `inventario` fuera de las reglas
      de validación y fuera de `$data` (requisito 4). Un POST con esos campos los
      ignora sin error y sin escribir en base de datos.
- [x] **0.4** `resources/views/productos/edit.blade.php` — eliminados los bloques de
      `stock` e `inventario`; en su lugar, panel de solo lectura con la existencia
      actual, el porcentaje consumido del ciclo y la nota de que la existencia cambia
      con las operaciones de inventario (requisito 7).
- [x] **0.5** `precio_compra` opcional — método privado `reglasPrecio()` compartido por
      `store` y `update`, con la regla `gte:precio_compra` aplicada solo si el costo
      recibido es mayor que 0 (requisitos 8 a 11). El formulario de edición marca el
      campo como opcional y explica que se completa con la última compra.
- [x] **0.6** `ProductoFactory` — `stock` e `inventario` en 0 por defecto, con el
      margen entre precio de compra y de venta preservado. Se agregaron los estados
      `conExistencias($stock, $inventario)` y `conMargenPerdido()`.
- [x] **0.7** `database/migrations/2026_09_25_000002_widen_egresos_caja_tipo_pago_table.php`
      amplía el enum a `efectivo, yape, transferencia, tarjeta` (requisito 46).
      Cubierto por `tests/Feature/EgresoCajaTipoPagoTest.php`, 6 casos que incluyen el
      rechazo de un valor fuera del enum.

## Fase 1 — El producto deja de inventar existencias

- [x] **1.1** `resources/views/productos/create.blade.php` — eliminado el bloque
      `inventario` (líneas 171-192) y sustituido por un aviso que explica que el
      producto se crea sin existencias y que la reposición pasa por una compra
      registrada en el Kardex (requisito 6). El `precio_compra` del alta también pasa
      a opcional, con su nota de uso.
- [x] **1.2** `ProductoController::store` — eliminada la regla `inventario` y sus tres
      mensajes; se persiste con `stock = 0` e `inventario = 0`; eliminado el bloque
      que generaba el correlativo `INV-XXXX` y la entrada de Kardex (requisitos 1 a 3).
      Se conservan la subida de foto a Cloudinary, el incremento de
      `contador_productos` de la categoría y el mensaje de éxito.
- [x] **1.3** `tests/Feature/ProductoInventarioTest.php` reescrito: 7 casos que
      invierten la semántica anterior. Afirma que el alta sin existencias es válida,
      que un `inventario` enviado se ignora, que no se genera Kardex, y que el
      formulario no expone cantidades pero sí explica la reposición.
- [x] **1.4** `tests/Feature/KardexTest.php` — el caso que afirmaba que el alta
      generaba una entrada `INV-0001` por 6 unidades pasa a afirmar lo contrario, más
      un segundo caso que confirma que un `inventario` enviado tampoco genera
      movimiento (requisito 3).
- [x] **1.5** Payloads de alta y edición sin los campos de cantidad, que ya son
      inertes: `ProductoCloudinaryTest` (alta y edición) y `ProductoPrecioTest`
      (3 casos de alta y 1 de edición). Se conservaron intactos los que construyen
      productos con `Producto::create()` directo, porque simulan productos ya
      repuestos: `ProductoStockAlertaTest`, `StockUpdateModalTest` y
      `PublicaProductoDetalleTest`.
- [!] **1.6** `ProductoSeeder` — **diferida a la Fase 3.9, y no es opcional.**
  Se implementationó y se revirtió tras verificar que rompía la demostración:
  con los 30 productos en cero existencias, las 60 ventas de `VentaSeeder`
  consumían mercadería que nunca se recibió, y `KardexSeeder` construía 30
  entradas `INV-` con cantidad 0 porque leía `productos.inventario`.

  **Los seeders son una excepción consciente a la regla de "el producto nace en
  cero"** hasta que la Fase 3.9 los sustituya por compras recibidas. El requisito 1
  describe lo que hace un **usuario** por interfaz, no lo que hace un seeder. La
  excepción está documentada en el propio `ProductoSeeder`.

  Verificado con `migrate:fresh --seed` sobre una base SQLite temporal: con el
  cambio, 30 productos en stock 0 frente a 60 ventas; restaurado, 30 productos con
  stock y 224 movimientos de Kardex, ninguno con cantidad 0.

## Fase 2 — Módulo de compras

- [x] **2.1** Migraciones `compras` y `detalle_compras` (requisitos 13 a 19).
  Aplicadas en MySQL; `correlativo` nullable + unique para borradores sin numerar.
- [x] **2.2** Modelos `Compra`, `DetalleCompra`, y `Proveedor::compras()`.
      `->parameters(['compras' => 'compra'])` en el resource.
- [x] **2.3** `CompraController` — `index` con `paginate(10)` y filtros, `create`,
      `store`, `edit`, `update`, `destroy` (solo borrador), `anular` (solo borrador).
      `recibir` se implementa en Fase 3 (requisitos 20 a 28).
- [x] **2.4** Tabla de transiciones `TRANSICIONES` y rechazo con mensaje explícito
      (requisito 28).
- [x] **2.5** Vistas `index`, `create`, `edit`, `show` con dark mode, wrapper
      `overflow-x-auto`, cabeceras `py-6`, celdas `py-8`, badges de estado y estado
      vacío (requisitos 55 a 58).
- [x] **2.6** `resources/js/compras/create.js` y `edit.js` con líneas dinámicas
      (select producto, cantidad, costo, subtotal, total). Registrados en
      `vite.config.js` y cargados con `@vite([...])` (requisito 59).
- [x] **2.7** Permiso `acceso-compras` en `PermissionSeeder`, rutas con
      `permission:acceso-compras`, y enlace en "Gestión Administrativa" en
      `layouts/app.blade.php` con `compras.*` en `$gestionAdministrativaActive`
      (requisitos 52 a 54).
- [x] **2.8** Auditoría: `Compra::class` en `$modelos` del provider,
      `DetalleCompra::class` en `AuditService::EXCLUIDOS`, etiquetas de acción
      `recibir compra` y `anular compra` en `AccionesAuditoriaController`
      (requisitos 49 a 51).
- [x] **2.9** `CompraFactory`, `DetalleCompraFactory` y `CompraSeeder`
      idempotente, registrado en `DatabaseSeeder` tras `ProveedorSeeder` y
      `ProductoSeeder`. Crea 5 compras demo (2 borradores, 2 recibidas, 1 anulada)
      con 10 líneas de detalle.

## Fase 3 — Integración con inventario y caja

- [~] **3.1** Spike de la migración de Kardex: verificar que `->change()` funciona
      sobre SQLite in-memory antes de escribir la migración.

  **Riesgo parcialmente descartado en la Fase 0.** La migración
  `2026_09_25_000002_widen_egresos_caja_tipo_pago_table.php` ya aplicó un
  `->enum(...)->change()` sobre una columna ENUM con clave foránea, y corrió sin
  error tanto en SQLite in-memory (suite de tests) como en MySQL. La restricción
  del enum se conserva: `EgresoCajaTipoPagoTest::rechaza_un_tipo_de_pago_inexistente`
  sigue lanzando `QueryException` con `bitcoin`, lo que confirma que la columna se
  reconstruyó y no se degradó a texto libre.

  Queda por confirmar un caso que el de egresos no cubrió: `movimientos_kardex` tiene
  **dos** claves foráneas (`productos` y `users`) más un índice propio, y
  `productos` a su vez es referenciada por `detalle_ventas`, `detalle_cambio_aceites`
  y `movimientos_kardex`. Antes de escribir la migración se repite el spike con esa
  tabla concreta. Si falla, la alternativa sigue siendo condicional por driver o
  conservar el ENUM y resolver el alcance en el modelo.
- [ ] **3.2** Migración de `movimientos_kardex.fuente` a `string(30)`, añadiendo
      `compra` sin reetiquetar los movimientos históricos `inventario` (requisito 42).
- [ ] **3.3** `KardexService` — constantes de fuente, registro de costo unitario, y
      método de entrada por compra (requisitos 43 a 45).
- [ ] **3.4** Correlativo `CMP-####` calculado dentro de la transacción de recepción
      (requisito 31).
- [ ] **3.5** `CompraController::recibir` — transacción única con el orden de la
      sección 6.2 del diseño (requisitos 29 a 41).
- [ ] **3.6** Bloqueo de productos en orden ascendente de `producto_id` para evitar
      interbloqueos.
- [ ] **3.7** Advertencia de margen en la respuesta (requisito 41).
- [ ] **3.8** Etiqueta de deprecación en el modal de reposición de
      `resources/views/productos/index.blade.php` (líneas 254-289) (requisito 61).
- [ ] **3.9** Reordenar `DatabaseSeeder` y reescribir `KardexSeeder` para que la
      cadena de demostración no produzca stock negativo (requisito 63 y 64).
- [ ] **3.10** Unificar el origen del cambio de aceite en `KardexSeeder.php:67`, que
      usa `automotor_id` mientras producción usa la placa.

## Orden de despliegue de migraciones

1. `egresos_caja.tipo_pago` ampliado.
2. `compras` y `detalle_compras`.
3. `movimientos_kardex.fuente` a `string`.

Ninguna elimina datos.

---

## Pruebas de propiedades

- [ ] **P1** Para cualquier cantidad entera ≥ 1 y costo decimal > 0, el subtotal es
      exactamente su producto (requisito 21).
- [ ] **P2** Para cualquier conjunto de líneas, el total es exactamente la suma de
      los subtotales (requisito 22).
- [ ] **P3** Recibir dos veces la misma compra produce error de estado y no duplica
      existencias ni Kardex (requisito 40).
- [ ] **P4** Ante un fallo en cualquier paso de la recepción, el `stock`, el
      `inventario`, el Kardex, el `precio_compra`, el egreso de caja y el estado
      quedan intactos (requisito 39).

Las de JS van en `tests/js/compras/*.property.test.js` con `fast-check`, etiquetadas
`// Feature: compras-ingreso-mercaderia, Property N`.

---

## Verificación

Estado tras la Fase 0:

```
php artisan test          → 415 passed, 1 failed (23 806 assertions)
vendor\bin\pint --test    → passed
```

El único fallo es `ServicioWebPropertiesTest > property 5 inicio respects section
toggle and first three`, preexistente y ajeno a esta feature. La línea base era 394
verdes con ese mismo fallo, así que la Fase 0 agregó 21 pruebas y no introdujo ninguna
regresión.

Comandos para las fases siguientes:

```
php artisan test --filter=ProductoInventarioTest
php artisan test --filter=ProductoPrecioTest
php artisan test --filter=KardexTest
php artisan test --filter=CompraTest
php artisan test --filter=CompraRecepcionTest
npm run test
vendor\bin\pint --test
composer run test
```

---

## Deuda que Hito 1 deja abierta

- `productos.stock_minimo` sigue sin existir; la alerta del 75 % se reinicia en cada
  ingreso y puede ocultar productos de alta rotación.
- El Kardex muestra su fuente en forma técnica.
- La web pública no valida stock: un producto activo sin existencias sigue visible.
- La fila de Kardex no guarda costo hasta la Fase 6.
- `movimientos_kardex.origen_id` sigue siendo texto libre sin clave foránea al
  documento de origen.
- `detalle_ventas` carece de restricción de unicidad y permite líneas duplicadas.
