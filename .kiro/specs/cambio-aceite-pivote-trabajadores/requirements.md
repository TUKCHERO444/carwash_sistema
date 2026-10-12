# Documento de Requisitos — Cambio-aceite: trabajadores solo por pivote

## Introducción

La tabla `cambio_aceites` tiene la columna `trabajador_id` (N:1 directa) y además existe la tabla intermedia `cambio_aceite_trabajadores` (N:N con `trabajadores`). Si un cambio puede ser realizado por varios trabajadores, la relación canónica es la N:N por pivote; `trabajador_id` es un remanente de la etapa en que solo se asignaba un trabajador. El diagnóstico del usuario es correcto: se elimina `trabajador_id` de `cambio_aceites` y la asignación vive únicamente en el pivote. Sin cambios de comportamiento: los formularios ya exigen `trabajadores_ids` (array, min 1) y ya hacen `sync()` al pivote.

## Glosario

- **Pivote**: tabla `cambio_aceite_trabajadores` (`cambio_aceite_id`, `trabajador_id`, unique compuesto, timestamps). Única vía de asignación trabajador↔cambio.
- **Remanente**: columna `cambio_aceites.trabajador_id` (FK cascade). Se elimina con migración (base + datos).
- **Sistema**: Laravel 12, hora `America/Lima`, SQLite dev / MySQL prod, tests en SQLite memoria.

---

## Requisitos

### Requisito 1: Eliminar `trabajador_id` de `cambio_aceites`

**User Story:** Como operador, quiero asignar uno o varios trabajadores solo por el selector múltiple, para que no existan dos fuentes de verdad.

#### Criterios de Aceptación

1. WHEN se ejecuta `migrate:fresh`, THE Sistema SHALL crear `cambio_aceites` SIN la columna `trabajador_id` y SHALL mantener el pivote `cambio_aceite_trabajadores` sin cambios.
2. THE Sistema SHALL proveer UNA migración de datos que elimine la FK y la columna `trabajador_id` (idempotente, reversible solo como nullable para `down()`), preservando todas las filas.
3. THE Sistema SHALL NOT exponer `trabajador_id` en `CambioAceite::$fillable` ni como relación `trabajador()`.

### Requisito 2: Backend usa solo el pivote

**User Story:** Como operador, quiero que crear, editar, confirmar y actualizar ticket asignen trabajadores igual que hoy, para no reaprender nada.

#### Criterios de Aceptación

1. THE Sistema SHALL mantener la validación `trabajadores_ids required|array|min:1` (+ `exists`) en `store/update/procesarConfirmacion/actualizarTicket` y el `sync($request->trabajadores_ids)` posterior a cada `create/update`, SIN escribir `trabajador_id`.
2. THE Sistema SHALL cambiar `Trabajador::cambioAceites()` de `HasMany` (por `trabajador_id`) a `BelongsToMany` por el pivote, y `CambioAceite::trabajadores()` sigue como fuente única.
3. THE Sistema SHALL cambiar `confirmados()` a `with(['cliente','trabajadores'])` y el filtro de `ReporteCambioAceiteService::base()` a solo `whereHas('trabajadores')`.
4. THE Sistema SHALL cambiar el fallback de `ReporteVehiculoService::historial()` a solo la lista del pivote (`pluck('nombre_completo')`), sin referencia al singular.

### Requisito 3: Listados muestran trabajadores del pivote

**User Story:** Como operador, quiero ver en pendientes/confirmados quiénes atendieron, aunque sean varios.

#### Criterios de Aceptación

1. WHEN se listan `cambio-aceite.index|pendientes|confirmados`, THE Sistema SHALL mostrar `trabajadores->pluck('nombre')->join(', ') ?: '—'` en lugar de `$cambioAceite->trabajador->nombre`.
2. THE Sistema SHALL NOT romper `show|ticket` (ya usan el pivote).

### Requisito 4: Seeders y factories sin `trabajador_id`

**User Story:** Como desarrollador, quiero datos de prueba coherentes con el nuevo esquema.

#### Criterios de Aceptación

1. THE Sistema SHALL crear `CambioAceite` en `CambioAceiteSeeder` y `CambioAceiteFactory` SIN `trabajador_id`, y asignar 1–2 trabajadores activos por pivote (`attach/sync`).
2. WHEN se ejecuta `migrate:fresh --seed`, THEN cada cambio confirmado de prueba SHALL tener ≥1 trabajador en el pivote.
3. WHEN se ejecuta la suite, THEN ningún test SHALL crear `cambio_aceites` con `trabajador_id` ni leer `$cambio->trabajador`.
