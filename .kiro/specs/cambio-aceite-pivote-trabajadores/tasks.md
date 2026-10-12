# Plan de Implementación — Cambio-aceite: trabajadores solo por pivote

## Visión General

Eliminar `trabajador_id` de `cambio_aceites`; el pivote `cambio_aceite_trabajadores` queda como única vía. Sin cambios de UX ni validaciones.

## Tareas

- [x] 1. Migraciones (base + datos)
  - Quitar `trabajador_id` de `2024_01_01_000006_create_cambio_aceites_table.php`.
  - Crear `2026_10_11_000002_drop_trabajador_id_from_cambio_aceites.php` (idempotente, `down()` nullable).
  - _Requisitos: 1.1, 1.2, 1.3_

- [x] 2. Modelos
  - `CambioAceite`: quitar de `$fillable`, eliminar `trabajador()`.
  - `Trabajador::cambioAceites()` HasMany → BelongsToMany por pivote (mantener nombre del método).
  - _Requisitos: 1.3, 2.2_

- [x] 3. Controlador + listados
  - Quitar 4× `'trabajador_id' => $request->trabajadores_ids[0]`; `confirmados()` con `trabajadores`.
  - Vistas `index` + `confirmados`: `pluck('nombre')->join(', ') ?: '—'`.
  - _Requisitos: 2.1, 3.1, 3.2_

- [x] 4. Reportes
  - `ReporteCambioAceiteService::base()` solo `whereHas('trabajadores')`.
  - `ReporteVehiculoService::historial()` sin fallback singular.
  - _Requisitos: 2.3, 2.4_

- [x] 5. Seeders y factories
  - `CambioAceiteFactory`: sin `trabajador_id` + `afterCreating` adjunta 1 trabajador.
  - `CambioAceiteSeeder`: sin `trabajador_id` + `attach` 1–2 activos.
  - _Requisitos: 4.1, 4.2_

- [x] 6. Tests
  - Quitar `'trabajador_id'` de `CambioAceite::create()` en tests + `attach/sync` explícito donde el conteo importe.
  - _Requisitos: 4.3_

- [x] 7. Docs + verificación
  - `docs/diagrama-entidad-relacion.md` (filas 287/653), nota en specs antiguos si se tocan.
  - `migrate:fresh --seed`, subconjunto de tests, grep de cierre, Pint.
  - _Requisitos: 1–4_
