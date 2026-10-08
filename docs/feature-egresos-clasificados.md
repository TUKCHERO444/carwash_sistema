# Egresos clasificados por categoría de gasto

## Objetivo

Evolucionar el egreso manual (`Egreso manual → Egreso clasificado`) para
saber cuánto salió, por qué concepto, en qué se usó y quién lo registró.
Sin módulo de gastos, sin planillas, sin gestión de activos.

## Alcance

- Migración `add_clasificacion_to_egresos_caja_table`: `categoria` (30,
  nullable) + `trabajador_id` (restrict, nullable). Históricos quedan
  “Sin clasificar”.
- Catálogo único `EgresoCaja::CATEGORIAS` (insumos, mantenimiento,
  servicios básicos, transporte/movilidad, pago a trabajador,
  compra/abastecimiento, otros) + `categoriasManuales()` (excluye compra,
  reservada al sistema) + `etiquetaCategoria()`. Ampliar = editar una
  constante.
- `CajaController@registrarEgreso`: categoría obligatoria del catálogo
  manual, trabajador opcional, descripción obligatoria (cubre “Otros”);
  aviso de que la mercadería va en Compras. `CajaService::registrarEgreso`
  con passthrough (mismo impacto financiero, usuario, fecha, caja).
- `CompraController@recibir` clasifica su egreso como
  `compra_abastecimiento` (mismo egreso del flujo, sin duplicar).
- Categoría visible en panel, detalle e historial; reporte de caja suma
  tabla por categoría (HTML + PDF) y columna en el detalle.
- Sin rutas de edición/eliminación (trazabilidad existente); auditoría
  `registrar egreso` sin cambios.

## Archivos clave

- `EgresoCaja`, `CajaService`, `CajaController`, `CompraController`,
  `ReporteCajaService::egresosPorCategoria`, vistas `caja/*` y
  `reportes/caja*` (+ PDF).
- Tests: `tests/Feature/EgresoClasificadoTest.php` (5 tests).

## Reglas

- Sin caja activa no hay egreso; monto positivo; sin inventario ni Kardex
  desde gasto operativo; la mercadería siempre por Compras.

## Verificación

- Tests en verde. Spec: `.kiro/specs/sistema-caja/` (Requisito 11,
  tarea 13) + `docs/modulo-reportes-informacion.md` §7.
