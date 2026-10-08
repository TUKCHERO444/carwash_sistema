# Requirements Document — devoluciones-ventas

## Introduction

Este feature agrega la **gestión formal de devoluciones y cambios de productos vendidos** como evolución del proceso actual de ventas, sin reemplazar ni alterar la anulación completa (`VentaController@destroy`, borrado físico con entrada Kardex compensatoria).

Hoy una venta solo puede anularse por completo (`DELETE /ventas/{venta}`): restaura todo el stock y borra cabecera y detalles. No existe operación parcial: si una venta tiene 3 productos y el cliente devuelve 1, o si un producto defectuoso debe cambiarse por otro, el operador no tiene flujo formal.

La solución introduce dos entidades nuevas — `devoluciones_venta` (cabecera) y `detalle_devoluciones_venta` (líneas) — y un `DevolucionVentaController` que reutiliza `KardexService` (movimientos espejo, sin mutar stock directamente), `CajaService` (único entrypoint de `caja_id` y egresos compensatorios) y `AuditService`/`AuditModelObserver` (trazabilidad). La venta original **nunca se modifica ni se elimina** en una devolución.

Alcance decidido: el **cambio de producto** opera como **devolución + nueva venta vinculada** en la misma transacción (producto devuelto → entrada Kardex; producto nuevo → salida Kardex y nueva `Venta` asociada). El impacto en caja de la devolución se refleja con **egreso(s) compensatorio(s)** vía `CajaService::registrarEgreso`.

---

## Glossary

- **Venta**: cabecera en `ventas` (`correlativo VTA-XXXX`, `total`, `metodo_pago`, `caja_id`). No tiene columna `estado`; anular = borrado físico.
- **DetalleVenta**: línea en `detalle_ventas` (`venta_id` cascade, `producto_id` restrict, `cantidad`, `precio_unitario`, `subtotal`).
- **Devolución**: operación parcial que reincorpora al inventario solo la cantidad devuelta y compensa en caja el importe correspondiente. No toca la venta original.
- **Cambio**: devolución de tipo `cambio` + nueva venta del producto sustituto, vinculadas (`devolucion.venta_nueva_id`).
- **Anulación completa**: `DELETE /ventas/{venta}` existente (restaura todo el stock, borra la venta). Flujo intacto y diferenciado.
- **Cantidad disponible para devolver**: `detalle.cantidad − SUM(detalle_devoluciones.cantidad del mismo detalle_venta_id)`.
- **Kardex espejo**: `KardexService::registrarEntrada/registrarSalida` solo insertan en `movimientos_kardex`; el llamador muta `stock` con `increment/decrement` atómico dentro de la misma transacción.
- **Egreso compensatorio**: `EgresoCaja` con monto positivo que representa la salida de dinero por la devolución (reutiliza `CajaService::registrarEgreso`, no `registrarIngresoCompensatorio`, que es para compras).
- **Motivo**: texto obligatorio (max 500) que justifica la devolución (defecto, talla, arrepentimiento, etc.).
- **Caja activa**: `Caja::abierta()->first()` vía `CajaService::getCajaActiva()`; sin caja no se opera (flash `error_caja` + modal "Caja Cerrada").

---

## Requirements

### Requirement 1: Estructura de base de datos — tabla `devoluciones_venta`

**User Story:** Como desarrollador, quiero una cabecera de devolución trazable a la venta original, la caja y el usuario, para que cada devolución/cambio quede registrada como operación independiente.

#### Acceptance Criteria

1. THE Sistema SHALL definir la tabla `devoluciones_venta` con columnas: `id`, `correlativo` (string único `DEV-XXXX`), `venta_id` (FK a `ventas`, `restrict`), `venta_nueva_id` (FK nullable a `ventas`, `nullOnDelete`, solo para cambios), `caja_id` (FK nullable a `cajas`, `set null`), `tipo` (enum `devolucion,cambio`, default `devolucion`), `motivo` (string max 500, obligatorio), `total_devuelto` (decimal 10,2), `user_id` (FK a `users`, `restrict`), `timestamps`.
2. THE Sistema SHALL definir índice único sobre `correlativo`.
3. WHEN la migración se revierte, THE Sistema SHALL eliminar la tabla sin errores.

### Requirement 2: Estructura de base de datos — tabla `detalle_devoluciones_venta`

**User Story:** Como desarrollador, quiero líneas de devolución vinculadas a la línea original vendida, para impedir devolver más de lo vendido y mantener trazabilidad línea a línea.

#### Acceptance Criteria

1. THE Sistema SHALL definir `detalle_devoluciones_venta` con: `id`, `devolucion_id` (FK cascade), `detalle_venta_id` (FK a `detalle_ventas`, `restrict`), `producto_id` (FK a `productos`, `restrict`), `cantidad` (integer ≥ 1), `precio_unitario` (decimal 10,2), `subtotal` (decimal 10,2), `timestamps`.
2. THE Sistema SHALL validar a nivel de aplicación (no con constraint) que `cantidad ≤ cantidad disponible para devolver` del `detalle_venta_id`.

### Requirement 3: Fuente Kardex `devolucion`

**User Story:** Como auditor, quiero que cada movimiento por devolución/cambio quede en Kardex con fuente propia, para distinguirlo de ventas y anulaciones.

#### Acceptance Criteria

1. THE Sistema SHALL agregar `'devolucion' => 'Devolución'` a `KardexService::FUENTES` (única fuente de verdad).
2. THE Sistema SHALL ampliar el CHECK de PostgreSQL de `movimientos_kardex.fuente` con el valor `devolucion` en la misma migración que crea las tablas (patrón de `2026_09_27_090000_completar_fuentes`).
3. THE Sistema SHALL usar `origen_id = correlativo DEV-XXXX` de la devolución en todos sus movimientos.
4. THE Sistema SHALL registrar la entrada por devolución con `registrarEntrada(..., 'devolucion', correlativo)` y la salida del producto sustituto (cambio) con `registrarSalida(..., 'devolucion', correlativo)`.

### Requirement 4: Consultar venta y seleccionar líneas a devolver

**User Story:** Como operador, quiero abrir una venta existente y elegir qué productos y cantidades devuelvo, para gestionar devoluciones parciales sin tocar los demás productos.

#### Acceptance Criteria

1. WHEN el usuario accede a `GET /ventas/{venta}/devoluciones/create`, THE Sistema SHALL mostrar la venta con sus líneas y, por cada línea, la cantidad vendida, la ya devuelta y la disponible para devolver.
2. THE Formulario SHALL permitir seleccionar un subconjunto de líneas y cantidades parciales (≥ 1 por línea seleccionada).
3. THE Formulario SHALL exigir el `motivo` (requerido, max 500) y el `tipo` (`devolucion` o `cambio`).
4. IF `tipo = cambio`, THEN THE Formulario SHALL exigir además el producto sustituto (`producto_nuevo_id` existe, activo) y su cantidad (≥ 1, ≤ stock disponible), reutilizando el endpoint `ventas.buscar-productos`.

### Requirement 5: Devolución parcial sin alterar la venta original

**User Story:** Como operador, quiero devolver solo parte de una venta, para que los demás productos queden intactos.

#### Acceptance Criteria

1. WHEN se registra una devolución válida, THE Sistema SHALL crear 1 cabecera + N líneas y NO actualizar ni eliminar la `Venta` ni sus `DetalleVenta` originales.
2. THE Sistema SHALL recalcular `total_devuelto = SUM(cantidad × precio_unitario original de la línea)` en el servidor (nunca confiar el total del cliente).
3. THE Sistema SHALL impedir cantidades superiores a la disponible (`vendida − ya devuelta`) por línea con error de validación por línea.

### Requirement 6: Reincorporación de stock vía Kardex (sin stock directo)

**User Story:** Como encargado de inventario, quiero que lo devuelto vuelva al stock solo mediante movimiento Kardex, para mantener trazabilidad.

#### Acceptance Criteria

1. WHEN la devolución se confirma, THE Sistema SHALL ejecutar por cada línea, dentro de una `DB::transaction`: `Producto::where(id)->increment('stock', cantidad)` + `KardexService::registrarEntrada(producto, cantidad, stockAntes, 'devolucion', correlativoDev)`.
2. THE Sistema SHALL dejar el campo `inventario` del producto sin modificar (patrón de ventas: solo `stock`).
3. THE Sistema SHALL bloquear productos con `lockForUpdate` ordenados ASC por `producto_id` dentro de la transacción (patrón `CompraController@anularRecibida`) para evitar deadlocks.

### Requirement 7: Impacto económico en caja según método de pago

**User Story:** Como cajero, quiero que la devolución genere la salida de dinero correspondiente en la caja activa, para que el resumen de caja cuadre.

#### Acceptance Criteria

1. THE Sistema SHALL exigir caja activa antes de operar; IF no hay, THEN redirigir con `with('error_caja', true)` (mismo modal "Caja Cerrada" de ventas/compras).
2. THE Sistema SHALL registrar la compensación con `CajaService::registrarEgreso` (monto positivo = salida), nunca con `registrarIngresoCompensatorio` (reservado a compras).
3. THE Sistema SHALL mapear `tipo_pago` al CHECK vigente de `egresos_caja` (`efectivo,yape,transferencia,tarjeta`): `efectivo → efectivo`, `yape → yape`, `izipay → tarjeta`, `mixto → un egreso por cada componente > 0` (`monto_efectivo → efectivo`, `monto_yape → yape`, `monto_izipay → tarjeta`), prorrateando el `total_devuelto` según el desglose de la venta.
4. THE Descripción del egreso SHALL contener `"Devolución {DEV-XXXX} de {VTA-XXXX} ({motivo})"` más el desglose cuando es mixto.
5. THE Sistema SHALL asociar `caja_id` de la caja activa a la cabecera de devolución vía `CajaService` (único entrypoint).

### Requirement 8: Cambio de producto (devolución + nueva venta)

**User Story:** Como operador, quiero cambiar un producto defectuoso por otro, para registrar entrega → devolución → nueva entrega con su ajuste económico.

#### Acceptance Criteria

1. WHEN `tipo = cambio`, THE Sistema SHALL ejecutar en la misma transacción: (a) devolución del producto original (entrada Kardex + egreso compensatorio según Requirement 7), (b) venta del sustituto (validar `stock ≥ cantidad`, `decrement` + `registrarSalida(..., 'devolucion', DEV-XXXX)` + crear `Venta VTA-YYYY` con `caja_id` activa y `user_id` actual), (c) vincular `devolucion.venta_nueva_id = VTA-YYYY`.
2. THE Sistema SHALL calcular la diferencia `total_nuevo − total_devuelto`: IF positiva, THEN queda como ingreso de la nueva venta en caja; IF negativa, THEN el egreso de la devolución cubre la diferencia (sin egreso adicional).
3. THE Sistema SHALL impedir el cambio si el sustituto no tiene stock suficiente, abortando toda la transacción sin efectos parciales.

### Requirement 9: Diferenciación devolución vs anulación completa

**User Story:** Como usuario, quiero distinguir ambas operaciones, para no confundir una devolución parcial con la eliminación de la venta.

#### Acceptance Criteria

1. THE Sistema SHALL mantener `DELETE /ventas/{venta}` (`destroy`) intacto: borrado físico con restauración total y mensaje `"Venta eliminada y stock restaurado correctamente."`.
2. THE Sistema SHALL exponer la devolución en rutas y vistas separadas (`devoluciones.*`, botón "Devolver/Cambiar" en `ventas.show`), con mensaje propio `"Devolución {DEV-XXXX} registrada correctamente."`.
3. THE Vista `ventas.show` SHALL mostrar el historial de devoluciones de la venta (correlativo, fecha, tipo, motivo, total, usuario) y el disponible por línea.
4. THE Listado `ventas.index` SHALL seguir mostrando las ventas originales (la devolución no las elimina ni las oculta).
5. WHEN se intenta `DELETE /ventas/{venta}` sobre una venta con devoluciones registradas, THE Sistema SHALL rechazar con error explícito (el FK `restrict` de `devoluciones_venta.venta_id` lo impediría de todos modos); la anulación completa solo aplica a ventas sin devoluciones.

### Requirement 10: Auditoría y trazabilidad

**User Story:** Como auditor, quiero saber quién, cuándo, qué y por qué se devolvió, para fiscalizar la operación.

#### Acceptance Criteria

1. THE Sistema SHALL registrar el modelo `DevolucionVenta` en `AuditServiceProvider::$modelos`, en `AuditService::AUDITABLE` y en su mapa `modulos` (`DevolucionVenta::class => 'ventas'`); el detalle queda excluido como `DetalleVenta`.
2. THE Controlador SHALL llamar `anotarAccion('registrar devolución')` o `anotarAccion('registrar cambio')` dentro de la transacción y exponer ambas acciones en `AccionesAuditoriaController` para el filtro UI.
3. THE Fila de auditoría SHALL conservar `motivo`, `total_devuelto`, `venta_id`, líneas/cantidades afectadas y `usuario_id` + `fecha_movimiento` (= `now()`, zona `America/Lima`).

### Requirement 11: Rutas, permisos y reglas transversales

**User Story:** Como desarrollador, quiero que la operación respete las reglas actuales de caja, inventario, Kardex y ventas, para no romper flujos existentes.

#### Acceptance Criteria

1. THE Sistema SHALL registrar las rutas estáticas ANTES de cualquier resource (`/ventas/{venta}/devoluciones/create`, `POST /ventas/{venta}/devoluciones`) bajo el mismo grupo `middleware(['auth','permission:acceso-ventas'])`; no se crea permiso nuevo (reutiliza `acceso-ventas`; `Administrador` conserva bypass por `Gate::before`).
2. THE Sistema SHALL mantener `CajaService` como único entrypoint de `caja_id` y `KardexService` como único escritor de Kardex; prohibido `DB::table('movimientos_kardex')->insert` o `EgresoCaja::create` directos fuera de los servicios.
3. THE Sistema SHALL reutilizar `DataNormalizer` para normalizar el motivo (si aplica) en lugar de duplicar regex.
4. THE Sistema SHALL cumplir convenciones UI (`.bg-surface/.input-main/.label-main`, slate, `paginate(10)`, `overflow-x-auto`, `py-6/py-8`, español, `now()`/Carbon) y JS modular (`resources/js/devoluciones/create.js` en `vite.config.js` + `@vite`, sin `<script>` de comportamiento en Blade).
5. THE Sistema SHALL registrar `DevolucionVenta` en el resumen de caja solo vía sus egresos (sin modificar `CajaService::calcularResumen`, que ya suma `egresos_caja`).

### Requirement 12: Validaciones y errores

**User Story:** Como operador, quiero mensajes claros cuando la devolución no procede, para corregir sin dejar datos parciales.

#### Acceptance Criteria

1. THE Sistema SHALL validar: venta existe; al menos 1 línea con `cantidad ≥ 1`; `cantidad ≤ disponible por línea`; `motivo` requerido max 500; `tipo ∈ {devolucion,cambio}`; si cambio, sustituto válido con stock.
2. IF alguna validación o el stock del sustituto falla, THEN THE Sistema SHALL revertir toda la transacción y redirigir con errores por línea, sin crear devolución, movimientos ni egresos.
3. IF la venta ya fue eliminada (anulada), THEN THE Sistema SHALL responder 404 (no se puede devolver sobre una venta inexistente).
