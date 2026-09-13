# Plan de Implementación: contenido-web

## Visión General

Crear el panel independiente de gestión de contenidos (display general de la web): tabla clave/valor `contenido_web`, servicio con fallback a defaults, controlador edit/update (pantalla única), permiso `acceso-contenido-web` + menú "Sitio web", y conectar el inicio, `/nuestros-productos` (mosaico real con categorías de BD) y `/nuestras-marcas` (curaduría) al panel. Los datos del negocio (`config/carwash.php`) NO se migran en esta iteración.

**Dependencias:** `servicios-web` (los textos `servicios_titulo`/`servicios_intro` se consumen ahí; este módulo provee el servicio).

## Tareas

- [ ] 1. Migración y modelo del panel
  - [ ] 1.1 Crear migración `2026_09_13_000003_create_contenido_web_table.php` (clave unique, valor text, tipo default 'string', timestamps)
  - [ ] 1.2 Crear `app/Models/ContenidoWeb.php` ($fillable clave/valor/tipo)
  - [ ] 1.3 Registrar `ContenidoWeb::class` en `AuditServiceProvider::$modelos`
  - _Requisitos: 1.1, 1.2, 3.4_

- [ ] 2. `ContenidoWebService`
  - [ ] 2.1 Definir `DEFAULTS` (11 claves: 5 bool, 6 texto, 1 json `marcas_web`) con metadata `tipo`, `seccion`, `default`
  - [ ] 2.2 `all()` (1 query + caché por request), `bool()`, `text()`, `json()` con fallback
  - [ ] 2.3 `marcasWeb()`: curaduría ordenada o fallback `Marca::orderBy('nombre')`
  - [ ] 2.4 Método de metadata para la vista (`clavesPorSeccion()`, `boolValue()`, `textValue()`)
  - [ ] 2.5 Registrar como singleton en `AppServiceProvider::register()`
  - _Requisitos: 1.3, 1.4, 1.5_

- [ ] 3. `ContenidoWebController` + rutas + permiso
  - [ ] 3.1 `edit()`: `$contenido` + `$marcas = Marca::orderBy('nombre')->get()`
  - [ ] 3.2 `update()`: validar (strings max:120, `marcas_orden` int≥0) → armar `data` (bool→'1'/'0', marcas_web→JSON `[{marca_id, orden}]`) → `updateOrCreate` por clave → `AuditService::anotarAccion('actualizar contenidos web')` → redirect flash success
  - [ ] 3.3 Rutas GET/PUT `/contenido-web` con `permission:acceso-contenido-web` (antes del grupo de ventas)
  - [ ] 3.4 `PermissionSeeder` += `'acceso-contenido-web'`
  - _Requisitos: 2.1-2.7, 3.1, 3.2, 3.3, 4.1, 4.2, 4.3_

- [ ] 4. Vista `contenido-web/edit.blade.php`
  - [ ] 4.1 Secciones inicio (4 toggles), productos (toggle mosaico + 2 textos), servicios (2 textos), marcas (2 textos + curaduría con checkbox + `marcas_orden[]`)
  - [ ] 4.2 Valores iniciales desde `$contenido` (BD o default); dark mode con `.input-main`/`.label-main`
  - [ ] 4.3 `@vite(['resources/js/contenido-web/edit.js'])` al final de `content`
  - _Requisitos: 2.1-2.7_

- [ ] 5. JS del panel
  - [ ] 5.1 Crear `resources/js/contenido-web/edit.js` (subir/bajar marcas en la curaduría, sincronizar `marcas_orden[]`)
  - [ ] 5.2 Registrar en `vite.config.js`
  - _Requisito: 8.1, 8.2, 8.3_

- [ ] 6. Menú lateral "Sitio web"
  - [ ] 6.1 Agregar enlace "Contenido de la web" → `contenido-web.edit` con `@can('acceso-contenido-web')` en sidebar desktop y bottom nav móvil (`layouts/app.blade.php`)
  - _Requisito: 4.4_

- [ ] 7. Página de inicio
  - [ ] 7.1 `PaginaInicioController::index()`: eliminar arrays hardcodeados; `$contenido`, `$servicios = Servicio::web()->take(3)`, `$marcas = $contenido->marcasWeb()`, `$productos = Producto::where('activo', true)->latest()->take(6)->with('marca')`
  - [ ] 7.2 `inicio.blade.php`: `@if` por sección con `$contenido->bool(...)`; marcas como `$marca->nombre`; productos reales (foto, marca, precio, precio_anterior)
  - _Requisitos: 5.1-5.8_

- [ ] 8. Mosaico `/nuestros-productos`
  - [ ] 8.1 `productos()`: categorías con productos activos, `withCount` de activos, mapeadas a arrays con `icono` derivado del slug (`iconoParaCategoria()`) e `imagen => null`
  - [ ] 8.2 `productos.blade.php`: sección bajo `@if ($contenido->bool('productos_mostrar_mosaico'))`; textos desde el panel; reparto dinámico 2-1-2 por grupos de 5; tiles enlazan a `publica.productos.categoria` con slug; estado vacío
  - [ ] 8.3 `promo-categoria-producto.blade.php`: sustituir `href="#"` por la ruta real
  - _Requisitos: 6.1-6.6_

- [ ] 9. Página de marcas
  - [ ] 9.1 `marcas()` → `$contenido->marcasWeb()`; textos desde panel
  - [ ] 9.2 `marcas.blade.php`: iterar `$marca->nombre`, encabezado `marcas_titulo`/`marcas_intro`
  - _Requisitos: 7.1-7.5_

- [ ] 10. Tests
  - [ ] 10.1 Crear `tests/Feature/ContenidoWebTest.php` (200 edit; PUT persiste e impacta páginas; upsert sin duplicados; auditoría; 403 sin permiso; curaduría respeta orden/filtro; estado vacío mosaico)
  - [ ] 10.2 Actualizar `tests/Feature/PublicaMarcasTest.php` y `tests/Feature/PublicaProductosTest.php` (datos reales + textos panel)
  - [ ] 10.3 Crear `tests/Feature/ContenidoWebPropertiesTest.php` — Properties 1, 2, 4, 5 (100+ iteraciones, tag `// Feature: contenido-web, Property {N}: <descripción>`)
  - [ ] 10.4 Crear `tests/js/contenido-web/curaduria.property.test.js` (fast-check): reparto 2-1-2 preserva el multiset
  - _Requisitos: 2.3, 3.2, 3.3, 5.1-5.8, 6.2-6.5, 7.1-7.5_

- [ ] 11. Checkpoint final — Verificación completa
  - [ ] `vendor/bin/pint`
  - [ ] `php artisan test` (suite completa)
  - [ ] `npm run test`
  - [ ] `npm run build`
  - [ ] `php artisan migrate` (BD local desarrollo)
  - Preguntar al usuario si hay dudas o ajustes antes de cerrar.

## Notas

- Orden recomendado: ejecutar primero `servicios-web` (el panel consume/reúne sus textos), luego `contenido-web`.
- `marcas_web` JSON: `[{ "marca_id": 1, "orden": 10 }]`; vacío = fallback a todas las marcas por nombre.
- El mosaico no añade columnas a `categorias`: el ícono se deriva del slug y la imagen queda `null` (FUTURO).
- Los datos del negocio (`config/carwash.php`) se migran a este panel en una fase futura, fuera de alcance.