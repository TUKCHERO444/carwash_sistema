# Reporte: Historial de atención por vehículo

## Objetivo

Trazabilidad cronológica de lavados y cambios de aceite por placa, dentro
del panel de Reportes (`GET /reportes/vehiculo`, `reportes.vehiculo`).
Sin módulo nuevo, sin cambios operativos, solo lectura.

## Alcance

- Búsqueda por placa (`regex 6-7 alfanum + guion`, normalizada con
  `Automotor::normalizarPlaca`) + filtro de tipo + rango estándar.
- Universo: solo `confirmado` (`automotor_id = placa`; lavados por `fecha`,
  cambios por `created_at`). Ventas excluidas (sin vínculo a automotor).
- Ficha (placa, cliente, marca/modelo, color), KPIs (operaciones,
  lavados/cambios, total, ticket promedio, primera/última atención, días
  promedio entre atenciones, último cambio con productos), agregados
  (servicios utilizados, productos de cambios) e historial unificado
  paginado (fecha, tipo, cliente, servicios/productos, trabajadores,
  importe, estado).
- Sin placa o placa inexistente: mensaje de búsqueda/no encontrado (sin 404).
- CSV y PDF con detalle completo; export sin placa válida → 422 JSON.
- Reutiliza `DateRangeFiltro`, `filtrosExtras`, `nombreArchivo`, CSV/PDF,
  parciales `filtros`/`acciones`, `print.js` y tarjeta en el índice.

## Archivos clave

- `app/Services/Reportes/ReporteVehiculoService.php` (`buscarAutomotor`,
  `kpis`, `porServicio`, `porProducto`, `historial`, `historialPaginado`).
- `ReporteController@vehiculo`; vistas `reportes/vehiculo.blade.php` y
  `reportes/pdf/vehiculo.blade.php`.
- Tests: `tests/Feature/Reportes/ReporteVehiculoTest.php`; ruta incluida en
  `ReporteAccesoTest` y `ReportePdfExportTest`.

## Reglas

- Permiso `acceso-reportes` (sin permiso nuevo); fechas con `now()` Lima.
- El historial por `placa` es estable aunque el vehículo cambie de dueño
  (el upsert de `obtenerAutomotor` reasigna `cliente_id`).

## Verificación

- Suite `Reportes` en verde. Spec: `.kiro/specs/reportes/`
  (Requisito 20, tarea 11) + `docs/modulo-reportes-informacion.md` §10.
