# Plan de Implementación — Renombrado `vehiculos`→`tipos_vehiculo`, `automotores`→`vehiculos`

## Visión General

Refactor solo de nombres, sin cambios de estructura. Orden estricto por colisión del nombre `vehiculos`: primero liberar (catálogo → `tipos_vehiculo`), luego ocupar (físico → `vehiculos`). Cada fase termina con tests en verde. Nada de esto está implementado: este plan espera visto bueno.

## Tareas

- [x] 1. Migrar base de datos (fresco + datos)
  - Editar migraciones base según `design.md` §Migraciones A (mismas columnas, nuevos nombres de tabla/columna/FK).
  - Crear `2026_10_11_000001_rename_vehiculos_automotores.php` (rename + FKs + permisos, `down()` reversible, `disableForeignKeyConstraints`).
  - Verificar `php artisan migrate:fresh --seed` y `migrate` sobre SQLite dev.
  - _Requisitos: 1.1, 2.1, 3.1, 3.2, 6.1, 6.2_

- [x] 2. Renombrar modelos y relaciones
  - Crear `app/Models/TipoVehiculo.php` (`table tipos_vehiculo`, `lavados(tipo_vehiculo_id)`), eliminar `app/Models/Vehiculo.php` viejo.
  - Crear `app/Models/Vehiculo.php` físico (placa PK, `normalizarPlaca`), eliminar `app/Models/Automotor.php`.
  - Actualizar `Lavado` (`tipoVehiculo()`, `vehiculo(vehiculo_id,placa)`), `CambioAceite` (`vehiculo()`), `Cliente` (`vehiculos()`).
  - _Requisitos: 1.2, 2.2, 3.3, 3.4_

- [x] 3. Renombrar servicios, policy, providers y normalización
  - `AutomotorApiService → VehiculoApiService`, `ClienteAutomotorService → ClienteVehiculoService` (firmas idénticas, mismo `services.vehicle_api`).
  - `AutomotorPolicy → VehiculoPolicy` (misma ownership).
  - `AuditServiceProvider` + `AuditService` (mapas `tipos-vehiculo`/`vehiculos`), `AccionesAuditoriaController` (filtros), `DataNormalizer` (comentarios), `DataCleanCommand` (`cleanVehiculos`).
  - _Requisitos: 4.1, 4.2_

- [x] 4. Seeders, factories y permisos
  - `VehiculoFactory → TipoVehiculoFactory`, `VehiculoSeeder → TipoVehiculoSeeder` (mismos datos), `AutomotorSeeder → VehiculoSeeder`, `LavadoSeeder/CambioAceiteSeeder/KardexSeeder` (nuevas claves), `DatabaseSeeder` (nuevo orden).
  - `PermissionSeeder`: `acceso-tipos-vehiculo` + `acceso-vehiculos` (fuera `acceso-automotores`); `AuthSeeder`: reasignar roles (Vendedor conserva ambos).
  - _Requisitos: 4.3_

- [x] 5. Checkpoint — Modelos + seeders
  - `php artisan migrate:fresh --seed` verde; preguntar dudas antes de tocar controladores.

- [x] 6. Renombrar controladores panel y cuenta
  - `VehiculoController → TipoVehiculoController` (`TipoVehiculo::paginate(10)`, `destroy` por `lavados()`, flashes "Tipo de vehículo…").
  - `AutomotorController → VehiculoController` (`unique:vehiculos,placa`, `consultarPlaca` idéntica).
  - `Cuenta\VehiculoController` → `use Vehiculo` (`show(Request, Vehiculo $vehiculo)`).
  - `LavadoController` (`tipo_vehiculo_id exists:tipos_vehiculo,id` + `obtenerVehiculo`), `CambioAceiteController` (sin catálogo), `ClienteController@buscarPorPlaca|resumen`, `ReporteController`.
  - _Requisitos: 4.1, 4.2_

  - [ ]* 6.1 Property test — P1/P2 equivalencia CRUD (Feature: renombrado-tipo-vehiculo, Property 1, 2)
    - ≥100 iteraciones válidas/inválidas por entidad; conteos y flashes idénticos a hoy.
    - _Requisitos: 1.3, 2.3_

- [x] 7. Reescribir rutas y permisos
  - `routes/web.php`: grupo `acceso-tipos-vehiculo` → `resource('tipos-vehiculo')->parameters(['tipos-vehiculo'=>'tipoVehiculo'])`; grupo `acceso-vehiculos` → `GET /vehiculos/consultar-placa` (ANTES) + `resource('vehiculos')->parameters(['vehiculos'=>'vehiculo'])`; `/consulta-placa-api` misma URI con `VehiculoController`; `cuenta.*` y `reportes.vehiculo` intactos de URI.
  - _Requisitos: 1.3, 1.4, 2.3, 2.4_

- [x] 8. Checkpoint — Rutas y controladores
  - `php artisan route:list --name=tipos-vehiculo,vehiculos` + `composer run test` parcial; confirmar.

- [x] 9. Migrar vistas Blade
  - `views/vehiculos/*` → `views/tipos-vehiculo/*`; `views/automotores/*` → `views/vehiculos/*`; actualizar `route()`, variables, títulos, `@vite`; `lavados/*` (`#tipo_vehiculo_id`, `$lavado->tipoVehiculo/$vehiculo`), `cambio-aceite/*`, `reportes/*`, `cuenta/vehiculos/*`, `layouts/app.blade.php` (`routeIs`, `@can`), `clientes/index`.
  - Respetar dark-mode (`bg-surface`, `input-main`…), `paginate(10)`, `py-6/py-8`, `overflow-x-auto`, sin `<script>` inline salvo `window.*=@json` antes de `@vite`.
  - _Requisitos: 5.1, 5.2, 5.4_

- [x] 10. Migrar JS y Vite
  - `js/vehiculos/create.js → js/tipos-vehiculo/create.js`; `js/automotores/validate.js → js/vehiculos/validate.js`; `vite.config.js` inputs; `js/lavados/*` (`tipo_vehiculo_id`), `js/buscador-placa.js` (URLs `/vehiculos/consultar-placa`), `js/clientes/index.js`.
  - `npm run test` verde.
  - _Requisitos: 5.3, 5.4_

- [x] 11. Reportes, dashboard y auditoría UI
  - `ReporteVehiculo/Lavados/Operativo/ClientesService` + vistas (`porTipoVehiculo`→`tipos_vehiculo`, `porVehiculo/topPlacas/vehiculos_unicos`→`vehiculos`), `dashboard.blade.php` (etiqueta ahora literal), auditoría UI.
  - _Requisitos: 4.2, 5.2_

  - [ ]* 11.1 Property test — P3 integridad FK (Feature: renombrado-tipo-vehiculo, Property 3)
    - Lavados/cambios aleatorios resuelven (nombre, placa); borrado bloqueado / SET NULL.
    - _Requisitos: 3.3, 3.4_

  - [ ]* 11.2 Property test — P5 migración con datos (Feature: renombrado-tipo-vehiculo, Property 5)
    - Snapshot aleatorio → `up()` preserva conteos y pares (nombre, placa); `down()` revierte.
    - _Requisitos: 6.1, 6.2_

- [x] 12. Renombrar tests existentes y grep de cierre
  - `AutomotorCrudTest → VehiculoCrudTest`, `AutomotorConsultaPlacaTest → VehiculoConsultaPlacaTest`, resto con nuevos modelos/rutas; `tests/js` (buscar-placa, clientes/index).
  - `composer run test` + `npm run test` completos en verde.
  - Grep vacío: `Automotor|automotores|automotor_id` (salvo spec); `vehiculo_id` solo físico + `tipo_vehiculo_id` catálogo.
  - _Requisitos: 4.4_

  - [ ]* 12.1 Property test — P4 sin residuales (Feature: renombrado-tipo-vehiculo, Property 4)
    - Recorre `app/routes/resources/database/tests/config` y falla si hay residual.
    - _Requisitos: 4.1, 4.2, 5.1, 5.3_

- [x] 13. Documentación
  - Actualizar `docs/feature-automotores.md`, `docs/diagrama-entidad-relacion.md`, `.kiro/guia-*` si mencionan nombres, y este spec (marcar implementado tras visto bueno).
  - _Requisitos: 6.3, 6.4_

- [x] 14. Checkpoint final — suite completa
  - `composer run test` + `npm run test` + `vendor/bin/pint --test` (si aplica) + `migrate:fresh --seed` verificados; pedir visto bueno para cierre.

## Notas

- Tareas `*` son property tests (100+ iteraciones) y pueden ir en paralelo a su fase.
- Ninguna tarea cambia tipos/nulables/validaciones lógicas: solo identificadores.
- `VEHICLE_API_TOKEN` / `config/services.php vehicle_api` no cambian.
- Sin alias permanentes `/automotores` por defecto (301 opcional, fuera de alcance).
