# Feature: Sección de Auditoría — Módulo de Auditoría de Acciones (registro completo de actividad)

## Índice

1. [Resumen ejecutivo](#resumen-ejecutivo)
2. [Estado actual del sistema](#estado-actual-del-sistema)
3. [Decisiones confirmadas por el usuario](#decisiones-confirmadas-por-el-usuario)
4. [Requisitos funcionales y no funcionales](#requisitos-funcionales-y-no-funcionales)
5. [Diseño técnico](#diseño-técnico)
6. [Plan de implementación en 7 unidades de trabajo](#plan-de-implementación-en-7-unidades-de-trabajo)
7. [Riesgos y decisiones pendientes](#riesgos-y-decisiones-pendientes)
8. [Resultados esperados](#resultados-esperados)
9. [Criterios de aceptación](#criterios-de-aceptación)
10. [Verificación](#verificación)

---

## Resumen ejecutivo

Se implementa, dentro de la **Sección de Auditoría**, un **segundo módulo independiente**: la **Auditoría de Acciones**. Mientras el Kardex registra **movimientos de inventario**, este módulo registra **todo lo que se mueve en los registros**: cada acción del proceso **CRUD sobre todos los módulos** del sistema, más los eventos de **inicio y cierre de sesión**.

Cada registro captura:

- **módulo** afectado (p. ej. `usuarios`, `productos`, `ventas`, `caja`),
- **acción** realizada (`crear`, `actualizar`, `eliminar`, y etiquetas de negocio como `confirmar`, `toggle estado`, `abrir caja`, `inicio de sesión`…),
- **id del registro** donde se accionó (relación polimórfica → apunta a la fila concreta de la tabla afectada),
- **usuario** que registró la acción,
- **fecha y hora** de la acción,
- **detalle de cambios** (`datos_antes` / `datos_despues` en JSON) para ver qué campos mudaron.

La captura es **100% automática** mediante **Model Observers** de Eloquent: no se toca cada controlador para registrar el CRUD base. Las etiquetas de negocio concretas (`confirmar`, `toggle`, caja, egreso…) se añaden de forma **puntual** en los métodos correspondientes mediante una anotación de contexto.

La tabla es una **tabla hija relacionada con todas las demás** mediante **relaciones polimórficas** (`auditable_type` + `auditable_id`), de modo que una única tabla sirva de hijo de cualquier módulo.

> **Importante — sin borrado automático:** por decisión explícita del usuario, **no se implementa ningún método de limpieza/borrado automático**. Los datos acumulados se depuran **manualmente** por el desarrollador en el despliegue cuando lo considere.

La sección sigue protegida por el permiso **`acceso-auditoria`** (el mismo que ya protege el Kardex). El enlace «Auditoría» pasa a ser un **submenú desplegable** con dos ítems: **Kardex** y **Acciones**.

---

## Estado actual del sistema

### Datos vigentes relevantes

El sistema cuenta con estos módulos y modelos (todos vía Eloquent, lo que habilita la captura automática por observers):

```
usuarios  → User
roles     → Role (Spatie)
trabajadores → Trabajador
productos → Producto, Categoria, Marca
servicios → Servicio
vehiculos → Vehiculo
clientes  → Cliente
automotores → Automotor
ventas    → Venta (+ DetalleVenta)
cambio de aceite → CambioAceite (+ CambioProducto)
lavados   → Lavado
caja      → Caja, EgresoCaja
```

### Sección Auditoría HOY

- La sección **Auditoría** existe como un **enlace único** al **Kardex** (`kardex.index`), protegida por `acceso-auditoria`.
- Se renderiza en el sidebar desktop y en el bottom-nav móvil (`resources/views/layouts/app.blade.php`), con `$kardexActive = request()->routeIs('kardex.*')`.
- **No existe** ninguna tabla de auditoría de acciones ni de sesiones.
- La autenticación vive en `app/Http/Controllers/Auth/LoginController.php` (`login` y `logout`).
- Los únicos changes relevantes sobre stock ya están instrumentados por el **Kardex** (autorizado aparte); aquí nos centramos en el **CRUD de todos los modelos**, no en el inventario.

### Hallazgos clave

- **Todos** los módulos usan Eloquent (incluso Caja vía `CajaService` → `Caja::create`, `$caja->update`, `EgresoCaja::create`), por lo que **observers** capturan todo el CRUD sin tocar controladores.
- **Pivotes / tablas hijas** (`detalle_ventas`, `cambio_productos`, `lavado_trabajador`, `detalle_servicios`) se gestionan junto a su padre → se **excluyen** de la auditoría para evitar ruido.
- **`movimientos_kardex`** se **excluye** para evitar "auditar la auditoría" (recursión y ruido).
- `Role` es un modelo de Spatie; igualmente auditable vía morphs (funciona con cualquier clase).

---

## Decisiones confirmadas por el usuario

1. **Relación con las tablas — polimórfica**: una única tabla hija `registros_auditoria` apunta a **cualquier** tabla mediante `auditable_type` + `auditable_id` (patrón idiomático de Laravel para "hija de todas").
2. **Captura — Model Observers**: un observer global registra `created`/`updated`/`deleted` de todos los modelos auditables automáticamente, sin instrumentar 15 controladores.
3. **Acciones de negocio con etiqueta propia**: `confirmar`, `toggle estado/activo`, `abrir/cerrar caja`, `registrar egreso`, `actualizar ticket`, `anular venta/cambio`, `ajustar stock` — vía anotación puntual de contexto.
4. **Detalle de cambios**: se guarda JSON con `datos_antes` y `datos_despues` (diff de atributos del modelo).
5. **Rastreo de sesiones**: se registran el **inicio** y el **cierre** de sesión (módulo `sesiones`, auditable = `User`).
6. **Sin borrado automático**: la depuración de la tabla es **manual** por parte del desarrollador en el despliegue; no se agrega ninguna política/scheduler de limpieza.

---

## Requisitos funcionales y no funcionales

### Requisitos funcionales

| ID | Requisito |
|----|-----------|
| RF-01 | Crear la tabla `registros_auditoria` como **tabla hija polimórfica** de todos los módulos. |
| RF-02 | Cada registro guarda: `modulo`, `accion`, `auditable_type`, `auditable_id`, `datos_antes`, `datos_despues`, `usuario_id`, `fecha_movimiento`. |
| RF-03 | Registrar **automáticamente** `crear` / `actualizar` / `eliminar` para todos los modelos auditables (observers de Eloquent). |
| RF-04 | Guardar `datos_antes`/`datos_despues` en JSON con los **campos que cambiaron** en la operación. |
| RF-05 | Etiquetar acciones de negocio con nombres propios: `confirmar`, `toggle estado`, `toggle activo`, `abrir caja`, `cerrar caja`, `registrar egreso`, `actualizar ticket`, `anular venta`, `ajustar stock`. |
| RF-06 | Registrar el **inicio de sesión** (`login`) y el **cierre de sesión** (`logout`) bajo el módulo `sesiones`. |
| RF-07 | **No implementar borrado automático** de la tabla (limpieza manual por el desarrollador). |
| RF-08 | Proveer listado paginado con **filtros**: módulo, acción, usuario, rango de fechas y búsqueda por id de registro. |
| RF-09 | Añadir una **vista de detalle** con los datos antes/después formateados. |
| RF-10 | Convertir el enlace «Auditoría» en un **submenú** con dos ítems: **Kardex** y **Acciones**. |
| RF-11 | Proteger rutas, controlador y menú con el permiso `acceso-auditoria` (mismo que el Kardex). |

### Requisitos no funcionales

| ID | Requisito |
|----|-----------|
| RNF-01 | **No recursión**: el observer **salta** si ya se está procesando un evento de auditoría o si el modelo está en la lista de exclusión (pivotes y `MovimientoKardex`). |
| RNF-02 | **Rendimiento**: índices sobre `auditable_type`, `auditable_id`, `modulo`, `accion`, `fecha_movimiento`. |
| RNF-03 | **Integridad referencial**: `usuario_id → users` (restrict); la parte polimórfica usa índice compuesto `(auditable_type, auditable_id)`. |
| RNF-04 | **Ligereza**: pensada para limpieza manual posterior; sin cascadas de borrado hacia módulos. |
| RNF-05 | Cobertura de **tests automáticos** (feature) del registro CRUD, de las etiquetas de negocio, de sesiones y de los filtros/pantallas. |

---

## Diseño técnico

### 1. Nueva tabla `registros_auditoria`

| Columna | Tipo | Restricciones | Descripción |
|---------|------|---------------|-------------|
| `id` | bigint | PK, autoincrement | |
| `modulo` | string(40) | not null, index | Nombre legible del módulo (p. ej. `productos`, `ventas`, `sesiones`). |
| `accion` | string(30) | not null, index | `crear`, `actualizar`, `eliminar` o etiqueta de negocio. |
| `auditable_type` | string | not null | Clase del modelo auditado (`App\Models\...`) |
| `auditable_id` | bigint | not null | Id del registro en su tabla |
| `datos_antes` | JSON | nullable | Valores previos de los campos que cambiaron |
| `datos_despues` | JSON | nullable | Valores posteriores de los campos que cambiaron |
| `usuario_id` | bigint | FK → `users.id` (restrict) | Usuario que ejecutó la acción |
| `fecha_movimiento` | timestamp | not null, index | Fecha y hora de la acción |
| `created_at` / `updated_at` | timestamp | | |

**Índices**:
- `(auditable_type, auditable_id)` — compuesto, para la relación polimórfica.
- individuales: `modulo`, `accion`, `fecha_movimiento`, `usuario_id`.

#### 1.1 Vocabulario de acciones

| Acción | Cuándo |
|--------|--------|
| `crear` | observer `created` |
| `actualizar` | observer `updated` (sin etiqueta de negocio) |
| `eliminar` | observer `deleted` |
| `confirmar` | `CambioAceite::procesarConfirmacion`, `Lavado::procesarConfirmacion` |
| `toggle estado` / `toggle activo` | `Producto::toggleStatus`, `Trabajador::toggleStatus`, `UserToggleController::toggle` |
| `ajustar stock` | `Producto::updateStock` |
| `actualizar ticket` | `CambioAceite::actualizarTicket` |
| `anular venta` | `Venta::destroy` |
| `abrir caja` / `cerrar caja` / `registrar egreso` | `CajaController` |
| `inicio de sesión` / `cierre de sesión` | `LoginController` |

#### 1.2 Mapeo modelo → módulo

```
User            → 'usuarios'
Role            → 'roles'
Trabajador      → 'trabajadores'
Producto        → 'productos'
Categoria       → 'categorias'
Marca           → 'marcas'
Servicio        → 'servicios'
Vehiculo        → 'vehiculos'
Cliente         → 'clientes'
Automotor       → 'automotores'
Venta           → 'ventas'
CambioAceite    → 'cambio_aceite'
Lavado          → 'lavados'
Caja            → 'caja'
EgresoCaja      → 'caja'
(sesión)        → 'sesiones'
```

### 2. Migración a crear

| Migración | Contenido |
|-----------|-----------|
| `2026_xx_xx_000001_create_registros_auditoria_table` | Crear `registros_auditoria` con columnas + FK + índices. |

> Nombre/correlativo de timestamp a definir en la implementación (se usará una fecha posterior al 2026_08_30_000001 del Kardex para garantizar orden).

### 3. Modelo `RegistroAuditoria` (nuevo, `app/Models/RegistroAuditoria.php`)

```php
class RegistroAuditoria extends Model
{
    protected $table = 'registros_auditoria';

    protected $fillable = [
        'modulo', 'accion', 'auditable_type', 'auditable_id',
        'datos_antes', 'datos_despues', 'usuario_id', 'fecha_movimiento',
    ];

    protected $casts = [
        'datos_antes'      => 'array',
        'datos_despues'    => 'array',
        'fecha_movimiento' => 'datetime',
    ];

    public function auditable(): MorphTo { /* morphTo */ }
    public function usuario(): BelongsTo { /* belongsTo User */ }
}
```

### 4. Servicio `AuditService` (nuevo, `app/Services/AuditService.php`)

Singleton por request que centraliza la escritura y el **contexto de acción**:

```php
class AuditService
{
    // Acción de negocio pendiente para el próximo evento Eloquent
    public function anotarAccion(string $accion): void;

    // Registro directo (usado por sesiones y por puntos explícitos)
    public function registrar(
        Model $modelo, string $accion,
        ?array $antes = null, ?array $despues = null, ?string $modulo = null
    ): void;

    // Limpia el contexto anotado al final de la request
    public function reiniciarContexto(): void;

    // Lista de modelos auditables
    public static function auditableModels(): array;
}
```

- `registrar` inserta la fila con `auth()->id()`, calcula `modulo` desde la clase del modelo (o usa `'sesiones'`/el pasado), y setea `fecha_movimiento = now()`.
- `anotarAccion` guarda el contexto en la instancia singleton; el observer lo consulta **una sola vez** y lo consume.
- `siguienteCorrelativoAuditoria`: **no aplica** (no hay correlativo: el agrupador es por `auditable_type`/`auditable_id`/`fecha_movimiento`).

### 5. Observer `AuditModelObserver` (nuevo, `app/Observers/AuditModelObserver.php`)

```php
class AuditModelObserver
{
    public function created(Model $m): void { /* crear, datos_despues */ }
    public function updated(Model $m): void { /* etiqueta = contexto anotado | 'actualizar'; diff getChanges() */ }
    public function deleted(Model $m): void { /* eliminar, datos_antes */ }
}
```

Reglas internas (todas en `AuditService` o en el observer):
- **Salta** si `Auth::guest()` (no hay usuario).
- **Salta** si el modelo está en la lista de exclusión (pivotes + `MovimientoKardex` + `RegistroAuditoria`).
- **Salta** si ya se está en medio de la escritura de auditoría (flag/estado; evita recursión).
- En `updated`, `antes`/`despues` = solo los campos de `getChanges()` y sus valores originales (`getOriginal()`).

### 6. `AuditServiceProvider` (nuevo, `app/Providers/AuditServiceProvider.php`)

- Registra `AuditService` como **singleton** por request.
- Registra `AuditModelObserver` sobre cada modelo de `AuditService::auditableModels()`.
- `booted`: reinicia el contexto de acción al finalizar la request.

### 7. Rastreo de sesiones (`app/Http/Controllers/Auth/LoginController.php`)

- **`login`**: tras `Auth::attempt` exitoso y verificación de `activo`, registrar `inicio de sesión` (módulo `sesiones`, auditable = `User`, id = usuario) antes del redirect.
- **`logout`**: capturar `auth()->id()` **antes** de `Auth::logout()`; registrar `cierre de sesión`.
- Verificación de inactivos: al rechazar cuenta inactiva **no** se registra inicio (no hay sesión real).

### 8. Etiquetas de negocio (anotación puntual por método)

| Archivo / método | Anotación |
|---|---|
| `CambioAceiteController::procesarConfirmacion` | `anotarAccion('confirmar')` |
| `CambioAceiteController::actualizarTicket` | `anotarAccion('actualizar ticket')` |
| `LavadoController::procesarConfirmacion` | `anotarAccion('confirmar')` |
| `ProductoController::toggleStatus` | `anotarAccion('toggle estado')` |
| `ProductoController::updateStock` | `anotarAccion('ajustar stock')` |
| `TrabajadorController::toggleStatus` | `anotarAccion('toggle estado')` |
| `UserToggleController::toggle` | `anotarAccion('toggle activo')` |
| `VentaController::destroy` | `anotarAccion('anular venta')` |
| `CajaController::abrir` | `anotarAccion('abrir caja')` |
| `CajaController::cerrar` | `anotarAccion('cerrar caja')` |
| `CajaController::registrarEgreso` | `anotarAccion('registrar egreso')` |

### 9. Controlador `AccionesAuditoriaController` (nuevo, `app/Http/Controllers/AccionesAuditoriaController.php`)

- `index()`: listado paginado de `registros_auditoria` con relaciones `auditable` y `usuario`, y **filtros** por `modulo`, `accion`, `usuario_id`, `desde`, `hasta` y texto (id de registro). Vista `auditoria.acciones.index`.
- `show(RegistroAuditoria $registro)`: detalle con `datos_antes`/`datos_despues` formateados. Vista `auditoria.acciones.show`.

> `auditable` es polimórfico: al renderizar el id del registro, linkear a la ruta `show` del modelo si existe (fallback a mostrar el id + clase).

### 10. Rutas (`routes/web.php`)

Se agrega un **segundo grupo bajo `auditoria`** (coexiste con el grupo `kardex.*`):

```php
Route::middleware(['auth', 'permission:acceso-auditoria'])->prefix('auditoria')->name('auditoria.')->group(function () {
    Route::get('/acciones', [AccionesAuditoriaController::class, 'index'])->name('acciones.index');
    Route::get('/acciones/{registroAuditoria}', [AccionesAuditoriaController::class, 'show'])->name('acciones.show');
});
```

> Nota: el grupo `kardex.*` existente se mantiene intacto; ambos comparten el prefijo `auditoria` y el mismo permiso.

### 11. Vistas (`resources/views/auditoria/acciones/`)

Siguiendo el patrón Blade + Tailwind + dark mode del Kardex:

- `index.blade.php`: tabla con Fecha/hora, Módulo, Acción (badge con color), Id registro (link polimórfico), Usuario; formulario de filtros; paginación; estado vacío.
- `show.blade.php`: detalle con `datos_antes`/`datos_despues` en layout de dos columnas.

### 12. Menú lateral (`resources/views/layouts/app.blade.php`)

- Nueva variable activa `$auditoriaActive = request()->routeIs('kardex.*', 'auditoria.*')`.
- Convertir el enlace único «Auditoría» en un **submenú desplegable** (patrón `data-dropdown` de «Gestión de Ventas»):
  - Desktop: botón «Auditoría» + chevron → submenu con **Kardex** (`kardex.index`) y **Acciones** (`auditoria.acciones.index`).
  - Móvil: dropdown análogo existente.
- Ambos ítems se muestran si `@can('acceso-auditoria')`; cada ítem se resalta individualmente con su propio `routeIs()`.

### 13. Seeders

- **No se agrega** `RegistroAuditoriaSeeder` por defecto: la auditoría se alimenta de **actividad real**. (Opcional y solo si el usuario lo solicita para demo.)

### 14. Puntos de integración (resumen de modificaciones)

| Archivo | Cambio |
|---------|--------|
| `database/migrations/2026_xx_xx_xxxxxxxx_create_registros_auditoria_table.php` | Tabla + FK + índices |
| `app/Models/RegistroAuditoria.php` | Modelo nuevo (morphTo + BelongsTo) |
| `app/Services/AuditService.php` | Singleton: registrar + anotarAccion + reiniciarContexto + auditableModels |
| `app/Observers/AuditModelObserver.php` | Observers `created/updated/deleted` (con exclusión y no-recursión) |
| `app/Providers/AuditServiceProvider.php` | Registro singleton + observers por modelo |
| `app/Http/Controllers/AccionesAuditoriaController.php` | Listado/filtros + detalle (nuevo) |
| `app/Http/Controllers/Auth/LoginController.php` | Registro de inicio/cierre de sesión |
| `CambioAceiteController.php`, `LavadoController.php`, `ProductoController.php`, `TrabajadorController.php`, `UserToggleController.php`, `VentaController.php`, `CajaController.php` | Anotaciones de acción de negocio |
| `routes/web.php` | Grupo `auditoria.acciones` con permiso |
| `resources/views/layouts/app.blade.php` | Submenú Auditoría (Kardex + Acciones) |
| `resources/views/auditoria/acciones/*.blade.php` | Vistas (nuevas) |

---

## Plan de implementación en 7 unidades de trabajo

### Unidad 0 — Esquema, modelo y servicio (fundamento de datos)

| # | Tarea |
|---|-------|
| 0.1 | Migración `create_registros_auditoria_table` (morphs + `modulo`/`accion`/`datos_*`/`usuario_id`/`fecha_movimiento` + índices). |
| 0.2 | Modelo `RegistroAuditoria` (casts JSON/datetime, `morphTo auditable`, `belongsTo usuario`). |
| 0.3 | Servicio `AuditService` (registrar + anotarAccion + reiniciarContexto + auditableModels + exclusión). |
| 0.4 | Observer `AuditModelObserver` (created/updated/deleted, con no-recursión y exclusión). |
| 0.5 | Provider `AuditServiceProvider` (singleton + registra observers sobre modelos auditables). |
| 0.6 | **Verificación U0:** `tinker` crea/actualiza/elimina un `Producto` y se persiste un `registros_auditoria` con `datos_antes/despues` y morphs. |

### Unidad 1 — Sesiones

| # | Tarea |
|---|-------|
| 1.1 | `LoginController::login`: registrar `inicio de sesión` (módulo `sesiones`, auditable = `User`). |
| 1.2 | `LoginController::logout`: capturar id **antes** de `Auth::logout()` y registrar `cierre de sesión`. |
| 1.3 | **Verificación U1:** login/`logout` generan filas tipo `sesiones` con usuario y `fecha_movimiento`. |

### Unidad 2 — Etiquetas de negocio (anotaciones puntuales)

| # | Tarea |
|---|-------|
| 2.1 | `CambioAceiteController` (procesarConfirmacion → `confirmar`; actualizarTicket → `actualizar ticket`). |
| 2.2 | `LavadoController::procesarConfirmacion` → `confirmar`. |
| 2.3 | `ProductoController` (toggleStatus → `toggle estado`; updateStock → `ajustar stock`). |
| 2.4 | `TrabajadorController::toggleStatus`, `UserToggleController::toggle` → `toggle estado/activo`. |
| 2.5 | `VentaController::destroy` → `anular venta`. |
| 2.6 | `CajaController` (abrir/cerrar/registrarEgreso → `abrir caja`, `cerrar caja`, `registrar egreso`). |
| 2.7 | **Verificación U2:** confirmar un cambio genera accion `confirmar` (no `actualizar`) con `datos_antes/despues`. |

### Unidad 3 — Vistas y controlador (módulo visible)

| # | Tarea |
|---|-------|
| 3.1 | `AccionesAuditoriaController` (index con filtros + show con detalle). |
| 3.2 | Vistas `auditoria/acciones/index.blade.php` y `show.blade.php`. |
| 3.3 | Rutas grupo `auditoria.acciones` con permiso `acceso-auditoria`. |
| 3.4 | **Verificación U3:** `/auditoria/acciones` lista y filtra; `/auditoria/acciones/{id}` muestra detalle. |

### Unidad 4 — Navegación (submenú Auditoría)

| # | Tarea |
|---|-------|
| 4.1 | Convertir «Auditoría» en submenú desplegable (Kardex + Acciones) en desktop. |
| 4.2 | Submenú análogo en bottom-nav móvil. |
| 4.3 | `$auditoriaActive = routeIs('kardex.*','auditoria.*')` + resaltado por ítem. |
| 4.4 | **Verificación U4:** ambos ítems visibles con `@can('acceso-auditoria')` y enlace activo correcto. |

### Unidad 5 — Tests automáticos

| # | Tarea |
|---|-------|
| 5.1 | `tests/Feature/Auditoria/RegistroTest.php`: observer registra `crear`/`actualizar`/`eliminar` en varios módulos validando morphs + `datos_antes/despues`. |
| 5.2 | `tests/Feature/Auditoria/SesionesTest.php`: login/logout generan registros `sesiones`. |
| 5.3 | `tests/Feature/Auditoria/EtiquetasTest.php`: confirmar/toggle/caja/egreso/anular llevan su etiqueta de negocio. |
| 5.4 | `tests/Feature/Auditoria/ConsultaTest.php`: filtros del listado + detalle. |
| 5.5 | `tests/Feature/Auditoria/PermisoTest.php`: rutas requieren `acceso-auditoria`; se excluyen pivotes y `MovimientoKardex`; no hay recursión. |
| 5.6 | **Verificación U5:** suite completa verde. |

### Unidad 6 — Verificación integral

| # | Tarea |
|---|-------|
| 6.1 | `php artisan test`, `npm run build` (Vite), `vendor/bin/pint --test`. |
| 6.2 | Revisión manual: CRUD en varios módulos → aparece su registro en `/auditoria/acciones`. |

---

## Riesgos y decisiones pendientes

- **Exclusión de pivotes y kardex**: evita ruido y recursión. Si luego se desea auditar tablas pivote o el kardex, se agregan a la lista auditable.
- **Rastreo de sesión y cuenta inactiva**: el login de una cuenta inactiva **no** genera registro (no hay sesión). Si se desea auditar **intentos fallidos**, se puede añadir un registro opcional de `intento de login fallido` (decisión abierta).
- **`datos_antes/datos_despues` y volumen**: guardar diffs añade peso a la BD; se aceptó por decisión del usuario. La depuración es **manual** en el despliegue.
- **Roles de Spatie**: `Role` es un modelo externo; el observer lo captura igual vía morphs.
- **Anotación de contexto y múltiples saves por request**: si un método realiza varios `save()` tras `anotarAccion`, solo el **primer** evento consume la etiqueta; el resto cae en `actualizar`. Se recomienda anotar justo antes del guard relevante y (si un método dispara varios saves de negocio) revisar caso por caso.

---

## Resultados esperados

### Datos

- Toda acción CRUD (crear/actualizar/eliminar) de cada módulo queda registrada en `registros_auditoria`, con `modulo`, `accion`, morphs al registro, `usuario_id`, `fecha_movimiento` y `datos_antes/despues`.
- Los inicios y cierres de sesión quedan registrados bajo el módulo `sesiones`.
- No hay borrado automático: la limpieza la realiza el desarrollador manualmente.

### Flujo

- Los observers capturan el CRUD automáticamente en todos los módulos.
- Las acciones de negocio (confirmar, toggle, caja, egreso, anular, ajustar stock) llevan etiqueta propia.
- La sección **Auditoría** muestra dos ítems: **Kardex** y **Acciones**.

### Calidad

- Permiso `acceso-auditoria` protege rutas, controlador y menú.
- Tests automáticos cubren el registro CRUD, sesiones, etiquetas de negocio, filtros y permisos.
- Build de Vite y `pint` correctos.

---

## Criterios de aceptación

1. La tabla `registros_auditoria` existe con morphs polimórficos indexados, `modulo`, `accion`, `usuario_id` y `fecha_movimiento`. **Sin borrado automático.**
2. Crear/actualizar/eliminar cualquier registro de los módulos genera una fila de auditoría con módulo, acción, id del registro, usuario, fecha-hora y `datos_antes/despues` JSON.
3. `confirmar`, toggle, abrir/cerrar caja, registrar egreso, actualizar ticket, anular venta y ajustar stock llevan **etiqueta de negocio propia**.
4. El **inicio** y el **cierre** de sesión se registran bajo el módulo `sesiones`.
5. El listado se filtra por módulo, acción, usuario y rango de fechas; existe vista de detalle.
6. La sección **Auditoría** es un submenú con **Kardex** y **Acciones**, ambos protegidos por `acceso-auditoria`.
7. No hay recursión ni registros de auditoría sobre pivotes, `MovimientoKardex` ni `RegistroAuditoria`.
8. `php artisan test`, `npm run build` y `vendor/bin/pint --test` pasan sin errores.

---

## Verificación

| Comando | Propósito |
|---------|-----------|
| `php artisan migrate` | Crear la tabla `registros_auditoria` |
| `php artisan test` | Ejecutar suite (incluye nuevos tests de Auditoría de acciones y sesiones) |
| `vendor/bin/pint --test` | Formato de código |
| `npm run build` | Compilar assets (Vite) |
| `php artisan route:list` | Validar rutas nuevas (grupo `auditoria.acciones`) |
| `php artisan tinker` | Inspección manual: registrar un CRUD y listar `RegistroAuditoria` |

> **Recordatorio de despliegue:** la tabla almacena toda la actividad; la **depuración es manual** por el desarrollador (no hay scheduler ni política). No se incluye ningún mecanismo auto-delete.
