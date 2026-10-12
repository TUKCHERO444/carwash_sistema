# Documento de Diseño Técnico — Cambio-aceite: trabajadores solo por pivote

## Visión General

Eliminación pura de columna redundante. El pivote `cambio_aceite_trabajadores` ya existe, ya tiene unique compuesto, ya se escribe en los 4 flujos del controlador (`store/update/procesarConfirmacion/actualizarTicket` hacen `sync`) y ya lo leen `show/ticket`, reportes `porTrabajador` y `ReporteOperativoService`. Solo hay que soltar el remanente N:1 y sus lecturas/escrituras residuales.

## Arquitectura

Sin nuevas capas. Cambios por archivo:

### Migraciones

- Base `2024_01_01_000006_create_cambio_aceites_table.php`: quitar la línea `$table->foreignId('trabajador_id')->constrained(...)`.
- Nueva `2026_10_11_000002_drop_trabajador_id_from_cambio_aceites.php` (prod, idempotente):
  ```php
  up(): if (!hasColumn) return; Schema::table → dropForeign(['trabajador_id']) en try/catch → dropColumn('trabajador_id')
  down(): if (hasColumn) return; $table->foreignId('trabajador_id')->nullable()->constrained('trabajadores')->nullOnDelete()
  ```
  (`down()` como nullable: no se puede reconstruir el dato histórico; solo restaura la estructura.)

### Modelos

```php
// CambioAceite: quitar 'trabajador_id' de $fillable; ELIMINAR trabajador(): BelongsTo.
// Mantener trabajadores(): BelongsToMany(Trabajador::class, 'cambio_aceite_trabajadores')->withTimestamps()

// Trabajador: cambioAceites() HasMany → BelongsToMany:
public function cambiosAceite(): BelongsToMany {
    return $this->belongsToMany(CambioAceite::class, 'cambio_aceite_trabajadores')->withTimestamps();
}
// Mantener alias cambioAceites() delegando a cambiosAceite() si hay llamadas externas;
// por defecto renombrar y actualizar llamadas (solo docs/specs la citan).
```

### Controlador `CambioAceiteController`

- Quitar las 4 líneas `'trabajador_id' => $request->trabajadores_ids[0],` (store, update, procesarConfirmacion, actualizarTicket). El `sync()` siguiente ya persiste.
- `confirmados()`: `with(['cliente','trabajador'])` → `with(['cliente','trabajadores'])`.

### Reportes

- `ReporteCambioAceiteService::base()`: filtro solo `whereHas('trabajadores', ...)` (quitar `where('trabajador_id',...)` + `orWhereHas`).
- `ReporteVehiculoService::historial()` (rama cambios): `'trabajadores' => $cambio->trabajadores->pluck('nombre_completo')->implode(' + ') ?: '—'` (quitar fallback `$cambio->trabajador?->...`).

### Vistas

- `cambio-aceite/index.blade.php:94`, `confirmados.blade.php:97`: `{{ $cambioAceite->trabajador->nombre }}` → `{{ $cambioAceite->trabajadores->pluck('nombre')->join(', ') ?: '—' }}`.
- `pendientes/show/ticket/confirmar/edit/create`: sin cambios (ya usan pivote o no muestran trabajador).

### Seeders / Factories

- `CambioAceiteFactory`: quitar `'trabajador_id' => Trabajador::factory()`; añadir `configure()` con `afterCreating(fn ($c) => $c->trabajadores()->attach(Trabajador::factory()->create()->id))` para que los tests históricos que no hacen attach sigan teniendo 1 trabajador.
- `CambioAceiteSeeder`: quitar `'trabajador_id' => ...`; tras `create()`, `$cambio->trabajadores()->attach($faker->randomElements($trabajadoresActivos, rand(1,2)))`. Si no hay trabajadores activos, crear uno de reserva (igual que hoy se asume que existen por orden de seeders).

### Tests

- Reemplazo mecánico en `tests/Feature/CambioAceite/*`, `OrdenAtencionTest`, `KardexTest`, `DashboardServiceTest`, `Reporte*`: quitar `'trabajador_id' => ...` de `CambioAceite::create()` y añadir `->trabajadores()->attach($trabajador->id)` (o `sync`) tras crear. Los que usan `CambioAceite::factory()` quedan cubiertos por `afterCreating`, salvo que ya hagan attach (no duplicar de más: `attach` adicional es inocuo por unique? No — duplicaría y violaría unique; verificar: si el test ya hace attach del mismo trabajador, el afterCreating previo puede colisionar. Regla: en tests que hacen attach explícito con el MISMO trabajador del factory aleatorio no hay colisión porque el factory crea uno aleatorio distinto; pero si el test fija trabajador y el factory ya adjuntó otro, el cambio tendrá 2 trabajadores — aceptable salvo conteos exactos `porTrabajador`. Revisar `ReporteCambioAceiteTest` y `ReporteOperativoTest` que cuentan operaciones por trabajador: usar attach explícito y NO depender del factory aleatorio — se ajustan creando con factory y luego `sync([$fijo])` en vez de `attach`.)
- `ReporteCambioAceiteTest::test_filtro_por_trabajador`-equivalentes siguen pasando porque el filtro pivote cubre.

## Propiedades de Corrección

- **P1. Sin columna.** *Para toda* fila creada por cualquier flujo, `cambio_aceites` no tiene atributo `trabajador_id` (schema + `fillable`) y la asignación vive en el pivote. **Valida Req 1.**
- **P2. Equivalencia de asignación.** *Para cualquier* `trabajadores_ids` válido (1..N), el `sync()` resultante es idéntico antes/después del fix. **Valida Req 2.1.**
- **P3. Conteo por trabajador.** *Para cualquier* cambio con N trabajadores, `porTrabajador`/`porTrabajador(operativo)` lo cuenta N veces (una por participante), igual que hoy. **Valida Req 2.3.**

## Manejo de Errores

- Validación `trabajadores_ids` intacta (mensajes "Debe asignar al menos un trabajador…").
- Vistas de listado con `?: '—'` si el pivote está vacío (datos legados migrados sin pivote histórico: el `down()` no restaura asignaciones; la migración `up()` no puede inferirlas — se documenta; los seeders nuevos siempre asignan).
- `down()` de la migración de datos restaura la columna como nullable/`nullOnDelete` (no los valores).

## Estrategia de Testing

- `composer run test` subconjunto: `CambioAceite/*`, `OrdenAtencion`, `Kardex`, `Reportes(Operativo|CambioAceite|Vehiculo|Clientes|Ingresos)`, `DashboardService`.
- `migrate:fresh --seed` + `data:clean` (no toca cambios).
- Grep de cierre: `trabajador_id` solo en `asistencias`, `lavado_trabajadores`, `cambio_aceite_trabajadores`, `egresos_caja`, filtros `?trabajador_id=` y pivotes; cero en `cambio_aceites.*`, `CambioAceite::$fillable`, `CambioAceiteController::create/update`.
