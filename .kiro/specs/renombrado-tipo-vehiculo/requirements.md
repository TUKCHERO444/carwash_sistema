# Documento de Requisitos — Renombrado `vehiculos` → `tipos_vehiculo`, `automotores` → `vehiculos`

## Introducción

La entidad actual `vehiculos` (atributos `nombre, descripcion, precio`) no representa vehículos concretos: es un **catálogo de tipos/categorías de servicio según vehículo** (auto, camioneta, SUV) con precio estandarizado. La entidad actual `automotor` (`placa, cliente_id, marca, modelo, serie, color, motor, vin`) sí representa el **vehículo físico real** del cliente.

Este spec planifica el refactor **solo de nombres** (migraciones, backend y frontend), **sin alterar la estructura real** (mismas columnas, tipos, nulables, PKs, FKs, validaciones y comportamiento). Al finalizar, `TipoVehiculo` será el catálogo y `Vehiculo` será el vehículo físico. El diagnóstico del usuario es correcto y el cambio procede.

Decisión de nombre propuesta (requiere visto bueno): **`tipo_vehiculo` / `TipoVehiculo`** para el catálogo (descartado `carroceria`, ver Glosario). `vehiculo` / `Vehiculo` para el vehículo físico (recicla el nombre actual de la tabla del catálogo tras liberarlo).

## Glosario

- **TipoVehiculo (nuevo)**: actual `Vehiculo`/`vehiculos`. Catálogo de categoría de servicio (ej. "Auto", "Camioneta", "SUV") con `nombre, descripcion, precio`. Tabla destino `tipos_vehiculo`. Solo lo consume `lavados` vía FK.
- **Vehiculo (nuevo)**: actual `Automotor`/`automotores`. Vehículo físico del cliente con PK `placa` (string 7), `cliente_id`, `marca, modelo, serie, color, motor, vin`. Tabla destino `vehiculos` (nombre reciclado).
- **Carrocería (descartado)**: alternativa evaluada. Se descarta porque en Perú connota solo forma de carrocería y no el concepto de "categoría con precio de servicio"; el código ya usa "tipo de vehículo" (`vehiculos-crud/requirements.md` glosario, `ReporteOperativoService::porTipoVehiculo`). `TipoVehiculo` preserva la semántica de precio.
- **FK catálogo**: actual `lavados.vehiculo_id → vehiculos.id`. Destino `lavados.tipo_vehiculo_id → tipos_vehiculo.id` (mismo tipo, misma cascada).
- **FK físico**: actual `lavados.automotor_id → automotores.placa` y `cambio_aceites.automotor_id → automotores.placa` (SET NULL). Destino `lavados.vehiculo_id → vehiculos.placa` y `cambio_aceites.vehiculo_id → vehiculos.placa`.
- **Sistema**: aplicación Laravel 12 del carwash (Blade + Tailwind 4, SQLite dev, `America/Lima`).
- **Alcance**: solo renombrado. Ninguna columna cambia de tipo/nulable, ninguna validación cambia de regla lógica (solo cambian nombres de tabla en `unique:`/`exists:` y nombres de clase/ruta/vista/variable).

---

## Requisitos

### Requisito 1: Catálogo `vehiculos` pasa a `tipos_vehiculo`

**User Story:** Como Administrador, quiero que el catálogo de categorías con precio se llame Tipo de vehículo en todo el sistema, para no confundirlo con vehículos reales.

#### Criterios de Aceptación

1. WHEN se ejecuta `migrate:fresh`, THE Sistema SHALL crear la tabla `tipos_vehiculo` con las mismas columnas que hoy tiene `vehiculos` (`id, nombre(100), descripcion nullable text, precio decimal(10,2), timestamps`) y SHALL NOT crear ninguna tabla `vehiculos` para el catálogo.
2. THE Sistema SHALL exponer el modelo `App\Models\TipoVehiculo` con `fillable nombre, descripcion, precio`, cast `precio decimal:2` y relación `lavados(): HasMany(Lavado::class, 'tipo_vehiculo_id')`.
3. WHEN el Administrador accede al CRUD del catálogo, THE Sistema SHALL servirlo bajo URLs `/tipos-vehiculo` con nombres `tipos-vehiculo.index|create|store|edit|update|destroy` y vistas `tipos-vehiculo/*`, con los mismos campos, reglas y mensajes (solo cambia "Vehículo" → "Tipo de vehículo" donde refiera al catálogo).
4. WHILE exista el permiso antiguo `acceso-vehiculos` para el catálogo, THE Sistema SHALL migrarlo a `acceso-tipos-vehiculo` (seeder + migración de datos `permissions`/`role_has_permissions`) sin dejar rutas bajo el nombre antiguo.

### Requisito 2: Físico `automotores` pasa a `vehiculos`

**User Story:** Como operador, quiero que el vehículo físico (placa, marca, modelo) se llame Vehículo en todo el sistema, para usar el término natural del negocio.

#### Criterios de Aceptación

1. WHEN se ejecuta `migrate:fresh`, THE Sistema SHALL crear la tabla `vehiculos` con las mismas columnas que hoy tiene `automotores` (`placa string(7) PK, cliente_id FK cascade, marca/modelo/serie/color/motor/vin nulables, timestamps`) y SHALL NOT crear ninguna tabla `automotores`.
2. THE Sistema SHALL exponer el modelo `App\Models\Vehiculo` con PK `placa` string sin autoincremento, `fillable placa, cliente_id, marca, modelo, serie, color, motor, vin`, relación `cliente(): BelongsTo(Cliente::class)` y `normalizarPlaca()`.
3. WHEN el operador accede al CRUD del físico, THE Sistema SHALL servirlo bajo URLs `/vehiculos` (nombre reciclado) con nombres `vehiculos.index|create|store|edit|update|destroy` y `vehiculos.consultarPlaca` (`GET /vehiculos/consultar-placa`, declarada ANTES del resource), con idénticos campos y reglas (solo cambian `unique:automotores,placa` → `unique:vehiculos,placa`).
4. WHILE exista el permiso `acceso-automotores`, THE Sistema SHALL migrarlo a `acceso-vehiculos` (el nombre reciclado ahora significa físico) sin romper asignaciones de roles existentes.

### Requisito 3: FKs y relaciones preservan integridad con nuevos nombres

**User Story:** Como sistema, quiero que lavados y cambios de aceite sigan vinculados igual que hoy, para no perder integridad referencial.

#### Criterios de Aceptación

1. THE Sistema SHALL mantener en `lavados` ambas FKs con idénticos tipos y acciones: `tipo_vehiculo_id → tipos_vehiculo.id` (CASCADE, como hoy `vehiculo_id`) y `vehiculo_id → vehiculos.placa` (SET NULL, como hoy `automotor_id`).
2. THE Sistema SHALL mantener en `cambio_aceites` la FK `vehiculo_id → vehiculos.placa` (SET NULL, como hoy `automotor_id`).
3. THE Sistema SHALL exponer `Lavado::tipoVehiculo(): BelongsTo(TipoVehiculo::class, 'tipo_vehiculo_id')`, `Lavado::vehiculo(): BelongsTo(Vehiculo::class, 'vehiculo_id', 'placa')`, `CambioAceite::vehiculo(): BelongsTo(Vehiculo::class, 'vehiculo_id', 'placa')`, `Cliente::vehiculos(): HasMany(Vehiculo::class)` (renombrado desde `automotores()` solo si se aprueba; si no, se mantiene alias) y `TipoVehiculo::lavados()`.
4. IF un lavado/cambio referencia una placa inexistente, THEN el comportamiento SHALL ser idéntico al actual (SET NULL / validación), sin nuevas restricciones.

### Requisito 4: Consumidores backend actualizados sin cambio de comportamiento

**User Story:** Como desarrollador, quiero que todos los consumidores usen los nuevos nombres, para que no quede ninguna referencia a `Automotor`/`automotores` ni al viejo `Vehiculo`-catálogo.

#### Criterios de Aceptación

1. THE Sistema SHALL renombrar `AutomotorController → VehiculoController` (físico), `VehiculoController → TipoVehiculoController` (catálogo), `Cuenta\VehiculoController` pasa a usar `Vehiculo`, `AutomotorPolicy → VehiculoPolicy`, `AutomotorApiService → VehiculoApiService`, `ClienteAutomotorService → ClienteVehiculoService` (o alias), manteniendo firmas y lógica.
2. THE Sistema SHALL actualizar `LavadoController`, `CambioAceiteController` (validaciones `tipo_vehiculo_id exists:tipos_vehiculo,id`, `placa` + `obtenerVehiculo`), `ClienteController@buscarPorPlaca|resumen`, `ReporteController` + `ReporteVehiculoService|ReporteLavadosService|ReporteOperativoService|ReporteClientesService`, `AuditServiceProvider` + `AuditService` (mapas `TipoVehiculo→tipos-vehiculo`, `Vehiculo→vehiculos`), `DataNormalizer` (comentarios), `DataCleanCommand`, `AccionesAuditoriaController` (filtro `vehiculos`), `DashboardController`/vistas (`vehiculos_unicos` ahora literalmente vehículos).
3. THE Sistema SHALL actualizar seeders/factories: `VehiculoSeeder → TipoVehiculoSeeder` (mismos datos), `VehiculoFactory → TipoVehiculoFactory`, `AutomotorSeeder → VehiculoSeeder` (mismas placas), `LavadoSeeder/CambioAceiteSeeder/KardexSeeder` con nuevas claves, `PermissionSeeder` + `AuthSeeder` con nuevos permisos, `DatabaseSeeder` con nuevo orden.
4. WHEN se ejecuta `composer run test`, THEN todas las suites SHALL pasar con los nuevos nombres y SHALL NOT quedar ningún `use App\Models\Automotor`, tabla `automotores`, ruta `automotores.*` ni `vehiculo_id` con significado antiguo.

### Requisito 5: Frontend (Blade + JS + Vite) actualizado sin cambio visual ni de flujo

**User Story:** Como usuario, quiero ver "Tipos de vehículo" y "Vehículos" con las mismas pantallas y flujos, para no reaprender nada.

#### Criterios de Aceptación

1. THE Sistema SHALL mover `views/vehiculos/*` (catálogo) → `views/tipos-vehiculo/*` y `views/automotores/*` → `views/vehiculos/*`, actualizando `route()`, variables (`$tiposVehiculo/$tipoVehiculo`, `$vehiculos/$vehiculo` físico), textos y `@vite`, sin alterar layout, clases dark-mode (`bg-surface`, `input-main`, ...), paginación (10) ni `py-6/py-8` y `overflow-x-auto`.
2. THE Sistema SHALL actualizar selects `lavados/create|edit|confirmar` (`select#tipo_vehiculo_id`, `data-precio`) y lecturas `lavados/show|ticket|index|confirmados`, `reportes/*`, `cuenta/vehiculos/*`, `layouts/app.blade.php` (`routeIs('tipos-vehiculo.*')`, `routeIs('vehiculos.*')`, `@can('acceso-tipos-vehiculo')`, `@can('acceso-vehiculos')`) y `clientes/index.blade.php` (`#lavados-vehiculos` ahora literal).
3. THE Sistema SHALL mover `js/vehiculos/create.js → js/tipos-vehiculo/create.js` y `js/automotores/validate.js → js/vehiculos/validate.js`, actualizar `vite.config.js` inputs, `js/lavados/shared|create|edit|confirmar` (`tipo_vehiculo_id`), `js/buscador-placa.js` (endpoints `/vehiculos/consultar-placa`, `/clientes/buscar-por-placa`, `/consulta-placa-api`) y `js/clientes/index.js` (`renderVehiculosHTML` ahora literal) sin cambiar validaciones ni UX.
4. THE Sistema SHALL NOT contener ningún bloque `<script>` inline de comportamiento en Blade (solo `window.* = @json(...)` antes de `@vite`).

### Requisito 6: Sin ruptura de datos ni de compatibilidad externa

**User Story:** Como administrador del despliegue, quiero una migración con renombrados que preserve datos, para ejecutar en producción sin pérdida.

#### Criterios de Aceptación

1. THE Sistema SHALL proveer UNA migración de renombrado que con datos existentes haga `vehiculos → tipos_vehiculo`, `automotores → vehiculos`, `lavados.vehiculo_id → tipo_vehiculo_id`, `lavados.automotor_id → vehiculo_id`, `cambio_aceites.automotor_id → vehiculo_id` (recreando FKs/índices idénticos) y migre `permissions`/`role_has_permissions`/`model_has_permissions` según Requisitos 1.4 y 2.4, con `down()` reversible.
2. WHILE la migración se ejecuta en SQLite y MySQL, THE Sistema SHALL desactivar/reactivar FKs (`Schema::disableForeignKeyConstraints()`) y SHALL preservar PK `placa`, cascadas y SET NULL.
3. THE Sistema SHALL NOT exigir alias permanentes de rutas antiguas (`/automotores`, `automotores.*`); se permite (opcional) redirección 301 solo si el equipo lo aprueba, documentada como fuera de alcance por defecto.
4. THE Sistema SHALL documentar en el spec que `config/services.php vehicle_api` y `VEHICLE_API_TOKEN` NO cambian (solo cambia el nombre de la clase que los consume).
