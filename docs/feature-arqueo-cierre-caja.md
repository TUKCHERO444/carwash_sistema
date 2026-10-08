# Arqueo de caja antes del cierre

## Objetivo

Extender el cierre de caja con un arqueo obligatorio: comparar el efectivo
esperado por el sistema con el contado físicamente y registrar la
diferencia, sin reemplazar el flujo (apertura → operaciones → balance →
cierre) ni duplicar la consolidación.

## Alcance

- Migración `add_arqueo_to_cajas_table`: `efectivo_esperado`,
  `efectivo_contado`, `diferencia`, `arqueo_user_id` (restrict),
  `fecha_arqueo`, `observacion_arqueo` (todo nullable; legadas sin arqueo).
- `CajaService::calcularEfectivoEsperado()`: `monto_inicial + ingresos en
  efectivo − egresos en efectivo` (reutiliza `calcularResumen`; Yape/Izipay
  y otros no son físico). `calcularResumen` y `cerrarCaja` intactos.
- `CajaService::cerrarConArqueo()`: transacción con lock; esperado fresco
  (nunca del cliente), `diferencia = contado − esperado`; sobrante/faltante
  no bloquean, quedan registrados; cerrada rechaza re-cierre.
- `CajaController@cerrar` exige `efectivo_contado`; con diferencia exige
  observación; mantiene `anotarAccion('cerrar caja')` (auditoría con
  antes/después). Modal de cierre = formulario de arqueo con preview
  (`resources/js/caja/cerrar.js`).
- Historial: columna Diferencia; detalle: sección Arqueo (o nota de régimen
  anterior).

## Archivos clave

- `Caja`, `CajaService`, `CajaController`, vistas `caja/panel|detalle|
  historial`, `resources/js/caja/cerrar.js`.
- Tests: `tests/Feature/CajaArqueoTest.php` (7 tests) +
  `tests/js/caja/cerrar.property.test.js` (2 props × 100 runs).

## Reglas

- Sin arqueo no hay cierre; el arqueo de una cerrada es inmutable.

## Verificación

- Tests en verde. Spec: `.kiro/specs/sistema-caja/` (Requisito 10,
  Propiedades 15–17, tarea 12).
