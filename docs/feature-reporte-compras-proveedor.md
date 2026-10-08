# Reporte: Compras por proveedor

## Objetivo

Explotar los datos del flujo de Compras para analizar abastecimiento por
proveedor y un comparativo general, dentro del panel de Reportes
(`GET /reportes/compras-proveedor`, `reportes.comprasProveedor`).
Sin módulo nuevo, sin entidades nuevas, solo lectura.

## Alcance

- Filtros: `proveedor_id` (vacío = resumen general), `producto_id` y rango
  (por `fecha` de compra).
- Historial: compras cerradas (`recibida` + `anulada`, con estado visible).
  KPIs, agregados y comparativo: solo `recibida` (abastecimiento vigente;
  anuladas revertidas y borradores sin efecto quedan fuera del volumen).
- Con proveedor: ficha, KPIs (total, compras, promedio, última, productos
  distintos), productos con mayor frecuencia (unidades, veces, último costo),
  evolución de precios (serie fecha→costo, mín/máx, variación %) e historial
  paginado (correlativo, fechas de compra/recepción, estado, productos
  × cantidad × costo, subtotal, total).
- CSV en dos modos (comparativo o líneas de detalle); PDF completo.

## Archivos clave

- `app/Services/Reportes/ReporteComprasService.php` (`resumenProveedores`,
  `kpis`, `porProducto`, `evolucionPrecios`, `historial`,
  `historialColeccion`).
- `ReporteController@comprasProveedor`; vistas
  `reportes/compras-proveedor.blade.php` y
  `reportes/pdf/compras-proveedor.blade.php`.
- Tests: `tests/Feature/Reportes/ReporteComprasProveedorTest.php`; ruta en
  `ReporteAccesoTest` y `ReportePdfExportTest`.

## Reglas

- Permiso `acceso-reportes`; reutiliza mecanismos de filtros, impresión y
  exportación del panel.

## Verificación

- Suite `Reportes` en verde. Spec: `.kiro/specs/reportes/`
  (Requisito 21, tarea 12) + `docs/modulo-reportes-informacion.md` §11.
