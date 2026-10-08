# Devoluciones y cambios de productos vendidos

## Objetivo

Permitir devoluciones parciales y cambios de productos sobre una venta
existente, sin anularla. La anulación completa (`DELETE /ventas/{venta}`,
borrado físico con restauración total) sigue intacta y diferenciada.

## Alcance

- Devolución parcial: 1..N líneas con cantidades ≤ disponible
  (`vendida − ya devuelta`), motivo obligatorio, sin tocar la venta original.
- Cambio: devolución + nueva venta sustituta vinculada (`venta_nueva_id`) en
  la misma transacción.
- Reincorporación de stock solo vía Kardex (`increment` + `registrarEntrada`
  con fuente `devolucion`, origen `DEV-XXXX`); `inventario` intacto.
- Impacto en caja con egreso(s) compensatorio(s) vía
  `CajaService::registrarEgreso` (mapeo `efectivo→efectivo`, `yape→yape`,
  `izipay→tarjeta`, `mixto→N egresos prorrateados`).
- Caja activa obligatoria (`error_caja`); auditoría `registrar devolución` /
  `registrar cambio` (módulo `ventas`); permiso reutilizado `acceso-ventas`.

## Archivos clave

- Migración `2026_10_04_000001_create_devoluciones_venta_table.php`
  (`devoluciones_venta`, `detalle_devoluciones_venta` + CHECK `devolucion`).
- `app/Models/DevolucionVenta.php`, `DetalleDevolucionVenta.php`
  (+ `Venta::devoluciones()`).
- `app/Http/Controllers/DevolucionVentaController.php`; rutas
  `ventas.devoluciones.*` y `devoluciones.*` (estáticas antes del resource).
- `KardexService::FUENTES += devolucion`; registro en `AuditServiceProvider`,
  `AuditService` y `AccionesAuditoriaController`.
- Vistas `ventas/devoluciones/create`, `devoluciones/index|show`,
  historial en `ventas/show`; JS `resources/js/devoluciones/create.js`.
- `VentaController@destroy` rechaza ventas con devoluciones (FK `restrict`
  + mensaje explícito); sin devoluciones anula como siempre.

## Reglas

- La venta y sus detalles nunca se modifican ni eliminan en una devolución.
- Toda falla (tope, sin caja, sin stock del sustituto) revierte la
  transacción completa.
- Fuente Kardex congelada: al agregar fuentes actualizar
  `KardexService::FUENTES` y la migración del CHECK (test `KardexTest` lo ata).

## Verificación

- `tests/Feature/DevolucionVentaTest.php` (12 tests) +
  `tests/js/devoluciones/create.property.test.js`.
- Spec: `.kiro/specs/devoluciones-ventas/`.
