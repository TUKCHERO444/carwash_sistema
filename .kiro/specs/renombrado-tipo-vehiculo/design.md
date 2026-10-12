# Documento de Diseño Técnico — Renombrado `vehiculos`→`tipos_vehiculo`, `automotores`→`vehiculos`

## Visión General

Refactor puro de nombres, sin cambios de estructura ni comportamiento. La dificultad es la **colisión de nombres**: el nombre destino `vehiculos` está ocupado por el catálogo. Estrategia: renombrar primero el catálogo para liberar `vehiculos`, luego renombrar el físico. Todo lo demás (controladores, rutas, vistas, JS, seeders, tests, auditoría, permisos, reportes, cuenta) sigue ese orden.

Mapa final:

| Concepto | Antes | Después |
|---|---|---|
| Catálogo (nombre, descripcion, precio) | tabla `vehiculos`, modelo `Vehiculo`, `VehiculoController`, rutas `vehiculos.*` (permiso `acceso-vehiculos`), vistas `vehiculos/*`, JS `vehiculos/create.js`, factory/seeder `Vehiculo*`, FK `lavados.vehiculo_id` | tabla `tipos_vehiculo`, modelo `TipoVehiculo`, `TipoVehiculoController`, rutas `tipos-vehiculo.*` (permiso `acceso-tipos-vehiculo`), vistas `tipos-vehiculo/*`, JS `tipos-vehiculo/create.js`, `TipoVehiculoFactory/Seeder`, FK `lavados.tipo_vehiculo_id` |
| Físico (placa PK, cliente, marca…) | tabla `automotores`, modelo `Automotor`, `AutomotorController`, rutas `automotores.*` (+`automotores.consultarPlaca`), permiso `acceso-automotores`, vistas `automotores/*`, JS `automotores/validate.js`, `AutomotorPolicy/ApiService`, `ClienteAutomotorService`, FKs `*.automotor_id` | tabla `vehiculos`, modelo `Vehiculo`, `VehiculoController`, rutas `vehiculos.*` (+`vehiculos.consultarPlaca`), permiso `acceso-vehiculos`, vistas `vehiculos/*`, JS `vehiculos/validate.js`, `VehiculoPolicy/ApiService`, `ClienteVehiculoService`, FKs `*.vehiculo_id` |

URLs `/vehiculos` y nombre de ruta `vehiculos.*` se **reciclan**: antes catálogo, después físico. URLs `/automotores` y `/tipos-vehiculo` (nueva) no coexisten con las antiguas.

---

## Arquitectura

Sin nuevas capas. Flujo idéntico, solo cambian identificadores:

```
migrate:fresh
  2024_01_01_000002_create_vehiculos_table.php        → crea tipos_vehiculo (editada)
  2026_08_28_000001_create_automotores_table.php      → crea vehiculos (editada)
  2026_08_28_000002_add_automotor_id...               → añade tipo_vehiculo_id + vehiculo_id (editada)
  2026_XX_XX_XXXXXX_rename_vehiculos_automotores.php  → NUEVA: rename con datos (prod)

HTTP
  routes/web.php (permission:acceso-tipos-vehiculo / acceso-vehiculos)
    TipoVehiculoController ─→ tipos-vehiculo.* ─→ TipoVehiculo ←→ tipos_vehiculo
    VehiculoController ─────→ vehiculos.* ──────→ Vehiculo ←→ vehiculos (placa PK)
    LavadoController / CambioAceiteController ─→ Lavado/CambioAceite
        ├── tipoVehiculo(): BelongsTo(TipoVehiculo)  (solo Lavado)
        └── vehiculo(): BelongsTo(Vehiculo, vehiculo_id, placa)
```

Orden de implementación obligatorio (rompe colisiones):

1. Modelos + migración fresca (base verde).
2. Servicios, policy, providers, seeders/factories.
3. Controladores panel + cuenta.
4. Rutas + permisos.
5. Vistas Blade.
6. JS + `vite.config.js`.
7. Reportes + dashboard + auditoría UI.
8. Migración con datos + tests + docs.

---

## Componentes e Interfaces

### Migraciones (fresco vs. datos)

**A. Editar migraciones base** (para `migrate:fresh --seed`; sin cambiar columnas):

- `2024_01_01_000002_create_vehiculos_table.php` → `Schema::create('tipos_vehiculo', ...)` idénticas columnas.
- `2026_05_01_000001_make_vehiculos_descripcion_nullable.php` → `Schema::table('tipos_vehiculo', ...)`.
- `2026_08_28_000001_create_automotores_table.php` → `Schema::create('vehiculos', ...)` idénticas columnas (`placa` PK, `cliente_id` cascade…).
- `2026_08_29_000001_add_vin_to_automotores_table.php` → `Schema::table('vehiculos', ...)`.
- `2024_01_01_000008_create_ingresos_table.php` → `foreignId('tipo_vehiculo_id')->constrained('tipos_vehiculo')->cascade()`.
- `2026_08_28_000002_add_automotor_id_...` → columnas `tipo_vehiculo_id` (si aplica) + `vehiculo_id string(7) nullable` con `foreign('vehiculo_id')->references('placa')->on('vehiculos')->nullOnDelete()`.
- `2026_08_29_000002_rename_ingresos_to_lavados.php`, `2026_08_28_000003_drop_placa_from_clientes_table.php`, `2026_10_02_000002_change_auditable_id...` (comentario `Vehiculo usa placa`) — solo comentarios/nombres.
- Toda validación con `unique:automotores,placa` → `unique:vehiculos,placa`; `exists:vehiculos,id` (catálogo) → `exists:tipos_vehiculo,id`.

**B. NUEVA migración con datos** (prod; reversible):

```php
// database/migrations/2026_10_11_000001_rename_vehiculos_automotores.php
public function up(): void
{
    Schema::disableForeignKeyConstraints();

    // 1. Liberar `vehiculos`: catálogo primero.
    Schema::rename('vehiculos', 'tipos_vehiculo');

    // 2. Físico ocupa el nombre liberado.
    Schema::rename('automotores', 'vehiculos');

    // 3. Columnas en lavados (drop FK → rename → re-add).
    // lavados.vehiculo_id (catálogo) → tipo_vehiculo_id → FK tipos_vehiculo.id cascade
    // lavados.automotor_id (físico)  → vehiculo_id    → FK vehiculos.placa set null

    // 4. cambio_aceites.automotor_id → vehiculo_id → FK vehiculos.placa set null

    // 5. Permisos: update permissions set name='acceso-tipos-vehiculo'
    //    where name='acceso-vehiculos'; update ... set name='acceso-vehiculos'
    //    where name='acceso-automotores'; (role_has_permissions/model_has_permissions
    //    referencian por permission_id, no requieren update adicional).

    Schema::enableForeignKeyConstraints();
}
public function down(): void { /* inverso exacto */ }
```

> Nota SQLite: `Schema::rename` y `renameColumn` con FKs requieren dropear FKs antes y recrearlas después; el flujo `disableForeignKeyConstraints` + drop/re-add está contemplado. Verificar con `migrate:fresh` y `migrate` sobre SQLite en memoria (tests) y `database.sqlite` dev.

### Modelos

```php
// app/Models/TipoVehiculo.php (renombrado desde Vehiculo.php; eliminar el viejo)
class TipoVehiculo extends Model {
    use HasFactory;
    protected $table = 'tipos_vehiculo';
    protected $fillable = ['nombre','descripcion','precio'];
    protected $casts = ['precio' => 'decimal:2'];
    public function lavados(): HasMany {
        return $this->hasMany(Lavado::class, 'tipo_vehiculo_id');
    }
}

// app/Models/Vehiculo.php (reescrito desde Automotor.php; eliminar Automotor.php)
class Vehiculo extends Model {
    protected $table = 'vehiculos';
    protected $primaryKey = 'placa';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['placa','cliente_id','marca','modelo','serie','color','motor','vin'];
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public static function normalizarPlaca(?string $placa): string {
        return strtoupper(str_replace(' ', '', (string) $placa));
    }
}

// Lavado.php
public function tipoVehiculo(): BelongsTo {
    return $this->belongsTo(TipoVehiculo::class, 'tipo_vehiculo_id');
}
public function vehiculo(): BelongsTo {   // antes automotor(); ahora físico
    return $this->belongsTo(Vehiculo::class, 'vehiculo_id', 'placa');
}

// CambioAceite.php
public function vehiculo(): BelongsTo {   // antes automotor()
    return $this->belongsTo(Vehiculo::class, 'vehiculo_id', 'placa');
}

// Cliente.php: automotores() → vehiculos(): HasMany(Vehiculo::class)
// (mantener alias automotores() deprecated solo si algún test externo lo exige; por defecto eliminar).
```

### Controladores, servicios, policy, providers

| Antes | Después | Notas |
|---|---|---|
| `VehiculoController` (catálogo) | `TipoVehiculoController` | `index: TipoVehiculo::paginate(10)`, `store/update` validan igual, `destroy` verifica `lavados()->exists()` (corrige el legacy `ingresos()`), flashes "Tipo de vehículo creado/actualizado/eliminado…" / "…tiene lavados asociados." |
| `AutomotorController` | `VehiculoController` | `unique:vehiculos,placa`, `Vehiculo::normalizarPlaca`, `consultarPlaca` idéntica (local → API), rutas `vehiculos.*` |
| `Cuenta\VehiculoController` | mismo nombre/clase | `use Vehiculo`, `Vehiculo::whereIn(...)`, `show(Request, Vehiculo $vehiculo)` + `Gate::authorize('view', $vehiculo)` (binding `{vehiculo:placa}` ya existe) |
| `AutomotorPolicy` | `VehiculoPolicy` | misma regla ownership `clientes ACTIVO contiene vehiculo->cliente_id` |
| `AutomotorApiService` | `VehiculoApiService` | mismo `buscarPorPlaca`, mismo `config('services.vehicle_api')` (config NO cambia), resiliente null/[] |
| `ClienteAutomotorService` | `ClienteVehiculoService` | `obtenerVehiculo($placa,$clienteId,$conApi)` misma lógica upsert + API |
| `LavadoController` | mismo | `TipoVehiculo::orderBy('nombre')`, `findOrFail(tipo_vehiculo_id)` para precio servidor, validación `tipo_vehiculo_id required exists:tipos_vehiculo,id` + `placa` vía servicio |
| `CambioAceiteController` | mismo | `obtenerVehiculo`, sin `tipo_vehiculo_id` (igual que hoy) |
| `ClienteController` | mismo | `Vehiculo::with('cliente')->where('placa')`, `resumen()` agrupa `vehiculo_id as placa` (clave `vehiculos` ahora literalmente correcta) |
| `ReporteController` + 4 Services | mismos | `buscarVehiculo()` sobre `Vehiculo`, `porTipoVehiculo()` join `tipos_vehiculo`, `porVehiculo()` join `vehiculos`, `vehiculos_unicos/topPlacas` sobre `vehiculo_id` |
| `AuditServiceProvider` + `AuditService` | mismos | `$modelos`: `TipoVehiculo::class, Vehiculo::class` (fuera `Vehiculo`-viejo/`Automotor`); mapa `TipoVehiculo=>'tipos-vehiculo'`, `Vehiculo=>'vehiculos'` |
| `AccionesAuditoriaController` | mismo | filtro `'vehiculos'=>'Vehículos'` (antes `'automotores'=>'Automotores'`), `'tipos-vehiculo'=>'Tipos de vehículo'` |
| `DataNormalizer` | mismo | solo comentarios (`TipoVehiculoController`); `PLACA_PATTERN/normalizarPlaca` intactos |
| `DataCleanCommand` | mismo | `cleanVehiculos()` (antes `cleanAutomotores`) itera `Vehiculo::all()` |

### Rutas (`routes/web.php`)

```php
use App\Http\Controllers\TipoVehiculoController;
use App\Http\Controllers\VehiculoController; // ahora físico

Route::middleware('permission:acceso-tipos-vehiculo')->group(function () {
    Route::resource('tipos-vehiculo', TipoVehiculoController::class)
        ->except(['show'])
        ->parameters(['tipos-vehiculo' => 'tipoVehiculo']);
});

Route::middleware('permission:acceso-vehiculos')->group(function () {
    // Estática ANTES del resource o Laravel la bindea como {vehiculo}.
    Route::get('/vehiculos/consultar-placa', [VehiculoController::class, 'consultarPlaca'])
        ->name('vehiculos.consultarPlaca');
    Route::resource('vehiculos', VehiculoController::class)
        ->except(['show'])
        ->parameters(['vehiculos' => 'vehiculo']);
});
```

- `Route::get('/consulta-placa-api', [VehiculoController::class,'consultarPlaca'])->name('tickets.consultarPlacaApi')` (mismo URI/nombre, nueva clase).
- `cuenta.vehiculos.*` (`/cuenta/vehiculos`, `/cuenta/vehiculos/{vehiculo:placa}`) y `reportes.vehiculo` (`/reportes/vehiculo`) **no cambian** de URI/nombre; solo cambian de modelo interno a `Vehiculo`.
- Regla routing-gotcha: estáticas (`consultar-placa`, `buscar-por-placa`, `confirmados`, `buscar-servicios`) siempre ANTES de resources (ya documentado en `web.php`).

### Vistas Blade

- Renombrar carpetas: `views/vehiculos/*` (catálogo) → `views/tipos-vehiculo/*`; `views/automotores/*` → `views/vehiculos/*`. Actualizar `route('tipos-vehiculo.*')` / `route('vehiculos.*')`, variables `$tiposVehiculo/$tipoVehiculo` vs `$vehiculos/$vehiculo`, títulos ("Tipos de vehículo" / "Vehículos"), `@vite(['resources/js/tipos-vehiculo/create.js'])` / `@vite(['resources/js/vehiculos/validate.js'])`.
- `lavados/create|edit|confirmar`: `label/select#tipo_vehiculo_id name=tipo_vehiculo_id`, `@foreach($tiposVehiculo as $tipo) <option value=id data-precio=precio>nombre — S/…`, `@error('tipo_vehiculo_id')`; bloque placa intacto (`input#placa`, `#placa-opciones`).
- Lecturas: `$lavado->tipoVehiculo->nombre` (+precio), `$lavado->vehiculo->placa ?? 'N/A'` (antes `->vehiculo->nombre` / `->automotor->placa`); igual en `cambio-aceite/*`, `reportes/*`, `cuenta/vehiculos/*`.
- `layouts/app.blade.php`: `$gestionAdministrativaActive = request()->routeIs('tipos-vehiculo.*')`, `@can('acceso-tipos-vehiculo')` → "Tipos de vehículo"; `@can('acceso-vehiculos')` → "Vehículos" (desktop + móvil). Sin cambios de clases dark-mode, paginación `paginate(10)`, `py-6/py-8`, `overflow-x-auto`.
- Sin `<script>` inline de comportamiento (solo `window.* = @json(...)` antes de `@vite`).

### Frontend JS + Vite

- `resources/js/vehiculos/create.js` → `resources/js/tipos-vehiculo/create.js` (misma validación, nuevo path).
- `resources/js/automotores/validate.js` → `resources/js/vehiculos/validate.js` (`initConsultarPlaca` fetch `/vehiculos/consultar-placa`).
- `vite.config.js` inputs: reemplazar `'resources/js/vehiculos/create.js'` por `'resources/js/tipos-vehiculo/create.js'` y `'resources/js/automotores/validate.js'` por `'resources/js/vehiculos/validate.js'` (resto intacto; `buscador-placa.js` se importa, no necesita input propio).
- `js/lavados/shared|create|edit|confirmar`: `recalcularTotales(items, tipoVehiculoSelectId='tipo_vehiculo_id')`, `getElementById('tipo_vehiculo_id')`, `dataset.precio`; `initBuscadorPlaca()` con endpoints `/clientes/buscar-por-placa` + `/consulta-placa-api` (nombres de ruta sin cambio).
- `js/buscador-placa.js`: `normalizarPlaca/placaValida/estadoBotones/datosResumenLocal` idénticos (solo URLs y comentarios `Vehiculo::normalizarPlaca`).
- `js/clientes/index.js`: `renderVehiculosHTML` ahora literalmente correcto (misma firma `{placa, lavados}[]`).

### Seeders / Factories / Permisos

- Eliminar `VehiculoFactory` (catálogo viejo) → crear `TipoVehiculoFactory` (mismo `nombre/precio`); sin `AutomotorFactory` (igual que hoy) o crear `VehiculoFactory` solo si tests lo exigen.
- `VehiculoSeeder` → `TipoVehiculoSeeder` (mismos nombres/precios, `firstOrCreate` por `nombre`); `AutomotorSeeder` → `VehiculoSeeder` (mismas placas); `LavadoSeeder/CambioAceiteSeeder/KardexSeeder` con `tipo_vehiculo_id/vehiculo_id`; `DatabaseSeeder` nuevo orden; `PermissionSeeder`: `acceso-tipos-vehiculo` + `acceso-vehiculos` (fuera `acceso-vehiculos`-viejo-sentido y `acceso-automotores`); `AuthSeeder`: reasignar (Vendedor conserva ambos si ya tenía `acceso-vehiculos`).

---

## Propiedades de Corrección

- **P1. Equivalencia del catálogo.** *Para cualquier* nombre/descripción/precio válido, crear/editar `TipoVehiculo` persiste exactamente lo que hoy persiste `Vehiculo`-catálogo y redirige igual (solo cambia el nombre de ruta). **Valida Req 1.**
- **P2. Equivalencia del físico.** *Para cualquier* placa/cliente/marca/… válida, crear/editar `Vehiculo` persiste exactamente lo que hoy persiste `Automotor` (misma normalización `ABC123`, misma unicidad) y `consultarPlaca` devuelve local-antes-que-API igual que hoy. **Valida Req 2.**
- **P3. Integridad FK preservada.** *Para cualquier* lavado/cambio con `tipo_vehiculo_id` + `vehiculo_id`, los joins resuelven los mismos `nombre/precio` y `placa/marca` que hoy con `vehiculo_id` + `automotor_id`; eliminar `TipoVehiculo` con lavados se bloquea; SET NULL en físico se conserva. **Valida Req 3.**
- **P4. Sin referencias residuales.** *Para todo* archivo PHP/Blade/JS, ninguna cadena `automotores`, `Automotor`, `automotor_id`, `tipos-vehiculo`-ausente o `vehiculo_id`-ambiguo con significado viejo sobrevive (grep de verificación). **Valida Req 4–5.**
- **P5. Migración con datos preserva cardinalidades.** *Para cualquier* snapshot con N tipos, M vehículos, L lavados, C cambios, tras `up()` los conteos son idénticos y cada lavado/cambio apunta al mismo par lógico (mismo `nombre` + misma `placa`); `down()` revierte. **Valida Req 6.**

## Manejo de Errores

- Validaciones: mismas reglas, solo cambia el parámetro de tabla (`unique:vehiculos,placa`, `exists:tipos_vehiculo,id`); mensajes y `old()` intactos.
- `TipoVehiculo@destroy` bloqueado por `lavados()->exists()` → redirect + flash error (corrige legacy `ingresos()`).
- `Vehiculo@destroy` (físico): mismo comportamiento actual (`delete()` directo; FKs hacen SET NULL en tickets) — sin cambio.
- 404 por Route Model Binding (`{tipoVehiculo}` por `id`, `{vehiculo}` por `placa`) y 403 por `permission:` idénticos a hoy.
- `consultarPlaca` / `buscarPorPlaca` / API vehicular: resilientes (devuelven `[]`/null, nunca bloquean forms) — sin cambio.

## Estrategia de Testing

- **Existentes renombrados** (misma lógica, nuevos nombres): `AutomotorCrudTest → VehiculoCrudTest`, `AutomotorConsultaPlacaTest → VehiculoConsultaPlacaTest`, `CuentaVehiculosTest` (interno `Vehiculo`), `ReporteVehiculoTest` (placa), `Lavado\StoreTest`, `OrdenAtencionTest`, `ReporteLavados/Operativo/Clientes/PdfExportTest`, `buscar-placa.property.test.js`, `clientes/index.property.test.js`. Verificación: `composer run test` + `npm run test` en verde.
- **Nuevos property tests** (100+ iteraciones, `fast-check` en JS, data providers en PHP, etiqueta `// Feature: renombrado-tipo-vehiculo, Property N:`):
  - P1/P2: crear/editar con entradas aleatorias válidas e inválidas preserva/ rechaza igual.
  - P3: lavados aleatorios con ambos FKs resuelven nombre+placa correctos; borrado bloqueado/SET NULL.
  - P5: seeder aleatorio → `up()`/`down()` preserva conteos y pares (nombre, placa).
- **Grep de cierre**: `grep -rn "Automotor\|automotores\|automotor_id" app routes resources database tests config` vacío (salvo este spec y notas históricas); `grep -rn "vehiculo_id" ` solo con significado físico nuevo + `tipo_vehiculo_id` para catálogo.
