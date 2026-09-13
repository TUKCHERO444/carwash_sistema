# Plan de Implementación: panel de asistencia

## Descripción general

Implementar el panel de asistencia de trabajadores de forma incremental: primero esquema y backend (migración + modelo + servicio + controlador + rutas + permisos + auditoría), luego frontend (vista + calendario vanilla + modal + módulo JS), y finalmente las pruebas de propiedad que garantizan los invariantes de conteo y de cuadrícula.

Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP. Cada tarea referencia los requisitos para trazabilidad. Los property tests se etiquetan `// Feature: asistencia, Property N: <descripción>` y **Valida:** en docblock, con mínimo 100 iteraciones.

## Tareas

- [ ] 1. Crear migración y modelo
  - [ ] 1.1 Crear `database/migrations/2026_09_12_000001_create_asistencias_table.php`
    - Columnas: `id`, `trabajador_id` (FK `constrained()->onDelete('cascade')`), `fecha` (date, index), `hora_entrada` (time), `timestamps`.
    - Restricción `unique(['fecha', 'trabajador_id'])`.
    - _Requirements: 7_

  - [ ] 1.2 Crear `app/Models/Asistencia.php`
    - `$table = 'asistencias'`, `$fillable = ['trabajador_id', 'fecha', 'hora_entrada']`, `$casts = ['fecha' => 'date']`.
    - Relación `trabajador(): BelongsTo`.
    - _Requirements: 7_

- [ ] 2. Registrar auditoría y permisos
  - [ ] 2.1 Añadir `Asistencia::class` a la lista de observables en `app/Providers/AuditServiceProvider.php`.
    - _Requirements: 9.1_

  - [ ] 2.2 Añadir `Asistencia::class` a `AUDITABLE` y `'asistencias'` a `$modulos` en `app/Services/AuditService.php`.
    - _Requirements: 9.2_
    - Nota: verificar que `Asistencia` no aparezca en `EXCLUIDOS`.

  - [ ] 2.3 Añadir el permiso `'acceso-asistencia'` en `database/seeders/PermissionSeeder.php` (bloque `// Personal`).
    - El rol Administrador lo recibe vía `AuthSeeder::syncPermissions(Permission::all())`. No tocar `AuthSeeder`.
    - _Requirements: 8.1, 8.2, 8.3_

- [ ] 3. Crear `AsistenciaService`
  - [ ] 3.1 Crear `app/Services/AsistenciaService.php`
    - `trabajadoresActivos()`: `Trabajador::where('estado', true)->orderBy('nombre')->get()`.
    - `resumenDeFecha(string $fecha)`: keyBy marcas; asistentes ordenados por `hora_entrada` ↑ y nombre; no asistentes por nombre ↑; conteos `asistieron`, `no_asistieron`, `total_activos`.
    - `estadoPorMes(string $mes)`: `Asistencia::selectRaw('fecha, count(*) as total')->whereBetween('fecha', [primerDia, ultimoDia])->groupBy('fecha')` + `total_activos`.
    - `sincronizarMarca(string $fecha, array $marcas)`: filtrar a activos; `updateOrCreate` por `(fecha, trabajador_id)`; eliminar marcas de activos omitidas; todo en `DB::transaction`; devolver `resumenDeFecha`.
    - _Requirements: 5, 6, 7_

- [ ] 4. Crear `AsistenciaController` y rutas
  - [ ] 4.1 Crear `app/Http/Controllers/AsistenciaController.php`
    - `index()`: devolver vista `asistencia.index` con `$totalActivos`.
    - `porFecha(Request)`: validar `fecha` (`required`, `date_format:Y-m-d`, `before_or_equal:today`); responder JSON de `AsistenciaService::resumenDeFecha`. En formato inválido responder `422` con `errors` (JSON, ya que es AJAX).
    - `porMes(Request)`: validar `mes` (`required`, `date_format:Y-m`); responder JSON de `estadoPorMes`.
    - `marcar(Request)`: validar `fecha` y `marcas` (array nullable; `marcas.*` `date_format:H:i`); llamar `sincronizarMarca`; responder JSON con resumen.
    - _Requirements: 2, 3, 4, 5, 6, 7_

  - [ ] 4.2 Rutas en `routes/web.php`, junto al bloque de gestión de usuarios
    - Grupo `middleware('permission:acceso-asistencia')`.
    - Rutas AJAX (`por-fecha`, `por-mes`, `marcar`) ANTES de `asistencia.index` (no hay resource; comentario de convención).
    - `use App\Http\Controllers\AsistenciaController;` en imports.
    - _Requirements: 8.4, 8.7_

- [ ] 5. Checkpoint backend
  - Asegurarse de que `php artisan migrate` no rompe nada y las rutas responden con los permisos correctos.

- [ ] 6. Menú y navegación
  - [ ] 6.1 En `resources/views/layouts/app.blade.php`:
    - Añadir `'asistencia.*'` a `$userManagementActive` en el bloque `@php`.
    - Entrada "Asistencia" en el dropdown "Gestión de usuarios", protegida con `@can('acceso-asistencia')`, estado activo con `request()->routeIs('asistencia.*')`, tanto en sidebar desktop como en bottom-nav móvil (con `data-persistent` si aplica).
    - _Requirements: 1_

- [ ] 7. Vista `resources/views/asistencia/index.blade.php`
  - Extender `layouts.app`.
  - Encabezado con título "Asistencia" y botón "Registrar asistencia de hoy" (`data-modal-open="modal-asistencia"`).
  - Contenedores con `bg-surface rounded-lg border border-main`; textos `text-primary` / `text-secondary`.
  - Navegación del calendario: botones `data-nav-prev` / `data-nav-next` y `#asistencia-titulo-mes`.
  - Cuadrícula `#asistencia-calendario` (tabla 7 columnas, encabezados L M X J V S D).
  - Leyenda de colores (verde/ámbar/rojo/gris).
  - `<x-modal id="modal-asistencia" maxWidth="2xl" title="Detalle de asistencia">` con: `#asistencia-fecha`, `#asistencia-resumen` (2 tarjetas de conteo con indicadores de color), `#asistencia-asistentes`, `#asistencia-no-asistentes`, `#asistencia-gestion` (filas checkbox + hora), `#asistencia-mensaje`, footer con botón "Guardar asistencia" y cerrar.
  - Datos iniciales `window.asistenciaHoy` / `window.asistenciaTotalActivos` ANTES de `@vite('resources/js/asistencia/index.js')` al final de `@section('content')`.
  - _Requirements: 2, 3, 4, 5, 6, 7_

- [ ] 8. Módulo JS `resources/js/asistencia/index.js`
  - Funciones puras exportadas: `INICIO_SEMANA`, `buildMonthGrid`, `fechaKey`, `parseFechaKey`, `esFechaPasadaOActual`, `claseDia`, `etiquetaFecha`.
  - Render inicial del mes actual; navegación prev/next; `fetch por-mes` para pintar indicadores de color por día.
  - Clic en día futuro → no-op; día pasado/actual → `fetch por-fecha`, poblar modal, `openModal('modal-asistencia')`.
  - Modo de gestión: filas checkbox + hora por trabajador activo; guardar → `POST marcar` con `{fecha, marcas}`; respuesta OK repinta modal y calendario; 422 muestra errores dentro del modal.
  - Delegación de eventos en `document`; fetch con `Accept: application/json` y `X-CSRF-TOKEN`.
  - _Requirements: 2, 3, 4, 6, 7_

  - [ ] 8.1 Registrar `'resources/js/asistencia/index.js'` en el `input` de `vite.config.js` (bloque `// Asistencia`).
    - _Requirements: convención Vite_

- [ ] 9. Validación `composer run dev` / build
  - Verificar que `npm run build` compila el módulo y la vista carga sin 404.

- [ ] 10. Tests de ejemplo (PHPUnit) — `tests/Feature/Asistencia/AsistenciaTest.php`
  - `RefreshDatabase`; `Permission::firstOrCreate('acceso-asistencia')` + `givePermissionTo`; trabajadores creados con factory o manualmente.
  - Tests listados en la estrategia de pruebas del design (acceso, conteos, por-mes, marcar full-sync, idempotencia, validaciones 422, exclusión de inactivos).
  - _Requirements: 2–8_

- [ ] 11. Property tests PHP — `tests/Feature/Asistencia/AsistenciaPropertyTest.php` (100 iteraciones)
  - [ ]* 11.1 Property 1: conteos particionan el universo de activos
    - Para N activos y subconjunto aleatorio marcado por iteración, verificar `asistieron + no_asistieron = total_activos` y partitividad de listas. Los inactivos nunca aparecen.
    - **Valida: Requisitos 5.4, 6.1, 6.2, 6.3**

  - [ ]* 11.2 Property 2: full-sync refleja exactamente el payload
    - Para payloads aleatorios sobre activos, verificar que la BD tiene exactamente una fila por id enviado y ninguna por los omitidos; inactivos no generan filas.
    - **Valida: Requisitos 7.4, 7.6**

  - [ ]* 11.3 Property 3: el guardado es idempotente
    - Para payloads aleatorios, aplicar dos veces produce el mismo estado que una sola.
    - **Valida: Requisitos 7.5**

  - [ ]* 11.4 Property 4: rutas requieren autenticación (sin sesión → redirect `/login`) para cada ruta del módulo
    - **Valida: Requisitos 8.5**

  - [ ]* 11.5 Property 5: rutas requieren `acceso-asistencia` (403) para cada ruta del módulo
    - **Valida: Requisitos 8.6**

- [ ] 12. Property tests JS — `tests/js/asistencia/index.property.test.js` (fast-check, 100 runs)
  - [ ]* 12.1 Property 6: `buildMonthGrid` cubre exactamente el mes (42 celdas, días únicos y ordenados, nulas solo en bordes) para years 2000–2035 y todos los meses.
    - **Valida: Requisitos 2.2**

  - [ ]* 12.2 Property 7: la celda del día 1 coincide con su día de semana (áncora lunes).
    - **Valida: Requisitos 2.2**

  - [ ]* 12.3 Property 8: `parseFechaKey(fechaKey(...))` round-trip y formato canónico `YYYY-MM-DD`.
    - **Valida: Requisitos 2.2, 4**

- [ ] 13. Formateo y chequeos de calidad
  - `vendor/bin/pint` sobre los archivos nuevos/modificados.
  - `composer run test` (suite completa).
  - `npm run test` (tests JS).
  - `npm run build` (Vite compila).

## Checkpoints

1. **Backend listo** (tareas 1–5): se puede marcar asistencia por terminal/Postman y consultar resúmenes JSON.
2. **Frontend operativo** (tareas 6–9): el calendario navega, abre el modal y guarda marcas sin recargar.
3. **Cierre** (tareas 10–13): suite verde y propiedad de conteo/idempotencia demostrada.

## Notas

- Las tareas con `*` son opcionales (property tests). El MVP mínimo cubre tareas 1–10 y 13.
- La convención de rutas AJAX antes que rutas dinámicas es obligatoria (ver `routes/web.php`).
- Los property tests corren sobre SQLite en memoria; no se necesita configurar BD.
- Ningún test debe tocar redes (Cloudinary/DNI): la feature no usa APIs externas.
- El calendario inicia la semana en **lunes** (`INICIO_SEMANA = 1`); si el negocio prefiere domingo es un cambio de una constante.
- No hay migración de `migrate:fresh` pendiente: añadir `Asistencia::class` a la auditoría no requiere reseed (el seeder de permisos es `firstOrCreate`).