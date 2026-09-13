# Plan de Implementación: servicios-web

## Visión General

Conectar la página pública de servicios con la entidad real `Servicio`: añadir campos de publicación (`activo`, `orden`, `icono`, `imagen`), toggle activar/inactivar AJAX, gestión Cloudinary de la imagen y grid de servicios reales en `/nuestros-servicios` y en la sección de inicio. La cabecera de la página consume `servicios_titulo`/`servicios_intro` del panel de contenidos (`contenido-web`) con fallback.

## Tareas

- [ ] 1. Migración y modelo
  - [ ] 1.1 Crear-migración `2026_09_13_000002_add_web_fields_to_servicios_table.php`
    - Columnas: `activo` bool default true, `orden` unsignedInteger default 0, `icono` string(30) nullable default 'sparkles', `imagen` string nullable (después de `precio`)
    - _Requisito: 1.1_
  - [ ] 1.2 Actualizar `app/Models/Servicio.php`
    - `$fillable` += `['activo', 'orden', 'icono', 'imagen']`; `$casts` += `['activo' => 'boolean', 'orden' => 'integer']`
    - Scope local `web()`: `where('activo', true)->orderBy('orden')->orderBy('nombre')`
    - _Requisito: 1.2, 1.4_

- [ ] 2. Controlador `app/Http/Controllers/ServicioController.php`
  - [ ] 2.1 Constante `ICONOS` y reglas de validación ampliadas (`activo` bool, `orden` int ≥ 0, `icono` `Rule::in(ICONOS)`, `imagen` image ≤ 2MB)
  - [ ] 2.2 `store()` y `update()`: incluir campos nuevos, `activo` por `$request->boolean()`, `icono` default 'sparkles', subida Cloudinary y reemplazo de imagen anterior (helper de `ProductoController`)
  - [ ] 2.3 Método `toggleStatus(Servicio $servicio): JsonResponse` con `AuditService::anotarAccion('toggle estado')` (réplica de `ProductoController::toggleStatus()`)
  - [ ] 2.4 `destroy()`: destruir imagen en Cloudinary fuera de la transacción
  - _Requisitos: 1.3, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 4.4_

- [ ] 3. Rutas
  - [ ] 3.1 Agregar dentro del grupo `permission:acceso-servicios`, ANTES del resource:
    - `Route::patch('servicios/{servicio}/toggle-status', [ServicioController::class, 'toggleStatus'])->name('servicios.toggleStatus')`
  - _Requisito: 7.1_

- [ ] 4. JS compartido y módulo de servicios
  - [ ] 4.1 Crear `resources/js/toggle-status.js` (updateBadge, updateButton, initToggleStatus por delegación; badge localizado por `closest('tr').querySelector('[data-badge]')`; leer `data-url` y `data-nombre`)
  - [ ] 4.2 Refactorizar `resources/js/productos/index.js` para importar de `toggle-status.js` sin cambiar comportamiento
  - [ ] 4.3 Crear `resources/js/servicios/index.js` que inicializa `initToggleStatus()`
  - [ ] 4.4 Registrar `resources/js/servicios/index.js` en `vite.config.js` (`input`)
  - _Requisito: 8.1, 8.2, 8.3, 8.4_

- [ ] 5. Vistas del panel
  - [ ] 5.1 `servicios/index.blade.php`: columna "Estado" (badge `data-badge` + botón `data-toggle-status` con `data-url` y `data-nombre`), filas con py-8/py-6 según convención, y `@vite(['resources/js/servicios/index.js'])` al final de `content`
  - [ ] 5.2 `servicios/create.blade.php` y `edit.blade.php`: campos `activo` (checkbox), `orden` (number), `icono` (select ICONOS) e `imagen` (file + preview actual en edit), con `old()` y errores
  - _Requisitos: 2.1, 2.2, 3.1, 3.2, 3.3_

- [ ] 6. Test — toggle y campos (feature)
  - [ ] 6.1 Crear `tests/Feature/ServicioWebToggleTest.php`
    - Toggle invierte estado + JSON; 403 sin permiso; solicita login; store/update con nuevos campos; rechazo de `icono`/`orden` inválidos; index muestra badge/toggle
  - _Requisitos: 2.2, 2.3, 2.5, 3.2, 7.1, 7.2_
  - [ ] 6.2 Crear `tests/Feature/ServicioCloudinaryTest.php` (mocks de `Cloudinary::uploadApi()`)
    - store con imagen upload+persistencia; update conserva/reemplaza+destroy; destroy imagen+servicio
  - _Requisitos: 4.1, 4.2, 4.3, 4.4_

- [ ] 7. Página pública
  - [ ] 7.1 `PaginaInicioController::servicios()`: `Servicio::web()->get()`, `$contenido` (ContenidoWebService) en vista
  - [ ] 7.2 `PaginaInicioController::index()`: sección servicios = `Servicio::web()->take(3)->get()`
  - [ ] 7.3 Crear `resources/views/publica/partials/card-servicio-item.blade.php` (imagen de fondo si existe + gradiente navy, ícono `icono-servicio`, nombre uppercase, descripción, precio `S/ number_format`)
  - [ ] 7.4 Reescribir `resources/views/publica/servicios.blade.php`: grid 1/2/3 columnas con `forelse` + estado vacío "Aún no tenemos servicios disponibles."; título/intro desde `$contenido->text('servicios_titulo'/'servicios_intro')`; conservar sellos
  - [ ] 7.5 `publica/inicio.blade.php`: envolver sección 3 con `@if ($contenido->bool('inicio_mostrar_servicios'))` y usar `$servicio->icono/nombre/descripcion`
  - _Requisitos: 5.1, 5.2, 5.3, 5.4, 5.5, 6.1, 6.2, 6.3_

- [ ] 8. Tests de ejemplos públicos y propiedades
  - [ ] 8.1 Actualizar `tests/Feature/PublicaServiciosTest.php` (solo activos ordenados + precio; inactivo oculto; estado vacío; título desde contenido-web con permiso del módulo)
  - [ ] 8.2 Crear `tests/Feature/ServicioWebPropertiesTest.php` — Properties 1, 2, 4 y 5 (100+ iteraciones, tag `// Feature: servicios-web, Property {N}: <descripción>`)
  - [ ] 8.3 Crear `tests/js/servicios/toggle-status.property.test.js` (fast-check): `updateBadge` y `updateButton` consistentes con `activo`
  - _Requisitos: 2.3, 5.1, 6.2, 8.2_

- [ ] 9. Checkpoint — Ejecutar verificación completa
  - [ ] `vendor/bin/pint`
  - [ ] `php artisan test` (toda la suite)
  - [ ] `npm run test`
  - [ ] `npm run build`
  - [ ] `php artisan migrate` (BD local desarrollo)
  - Preguntar al usuario si hay dudas antes de continuar con `contenido-web`.

## Notas

- El toggle y la imagen siguen el patrón de `ProductoController` (ProductoController:284-303, 193-213, 313-326).
- El módulo AJAX usa `meta[name="csrf-token"]` y headers `X-CSRF-TOKEN`/`Accept: application/json`.
- `ContenidoWebService` se implementa en `.kiro/specs/contenido-web`; aquí solo se consume con fallback de texto actual.