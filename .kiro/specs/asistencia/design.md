# Design Document — panel de asistencia

## Visión General

Este módulo añade un **panel de asistencia** para los trabajadores del sistema. Combina una **consulta visual** (calendario mensual con indicadores de color por día) con una **marcación manual** (el administrador registra quiénes asistieron y su hora de entrada desde un modal).

Decisiones de diseño clave:

- **Marcación manual por el administrador** (decisión de negocio confirmada): no hay kiosco con DNI ni derivación automática desde lavados. Asistir = tener una fila en `asistencias` para esa fecha.
- **Sin detección de tardanzas** (decisión de negocio confirmada): el sistema no conoce horarios de trabajo; se guarda la hora de entrada pero no se evalúa puntualidad. Se deja el campo `hora_entrada` listo para una futura fase de horarios.
- **Conteo de "no asistió" por diferencia** (decisión de negocio confirmada): no se almacenan ausencias. No asistente = trabajador activo sin marca ese día.
- **Nuevo permiso `acceso-asistencia`** (decisión confirmada): independiente de `acceso-trabajadores`.
- **Calendario en JavaScript vanilla**: el proyecto no tiene librerías de calendario y su convención es no introducir dependencias. Se construye una cuadrícula de 42 celdas (6 semanas) con funciones puras probables por property testing.
- **Guardado full-sync idempotente**: el payload de `marcar` es la fotografía completa del día (trabajador → hora). Reenvíos no duplican, y el estado final de la BD es exactamente lo enviado. Esto elimina la necesidad de rutas individuales de editar/eliminar hora.
- **Semana que inicia en lunes** (ISO 8601): decisión UI con expresión única y centralizada en una constante del frontend, fácil de cambiar si el negocio prefiere domingo.
- **Sin acoplamiento con Caja**: la asistencia no es una transacción de dinero; no aplica el mecanismo `error_caja`.

---

## Arquitectura

```
HTTP Request
    │
    ▼
routes/web.php  (grupo middleware: auth, permission:acceso-asistencia)
  GET  /asistencia                 → index
  GET  /asistencia/por-fecha       → porFecha (JSON; AJAX, antes que rutas dinámicas)
  GET  /asistencia/por-mes         → porMes   (JSON; AJAX)
  POST /asistencia/marcar          → marcar   (full-sync; JSON)
    │
    ▼
AsistenciaController
  ├── index()      → vista asistencia.index (mes visible = mes actual)
  ├── porFecha()   → AsistenciaService::resumenDeFecha($fecha)
  ├── porMes()     → AsistenciaService::estadoPorMes($mes)
  └── marcar()     → validación + AsistenciaService::sincronizarMarca($fecha, $marcas)
    │
    ▼
AsistenciaService   (único punto de entrada de la lógica de conteo y guardado)
  ├── trabajadoresActivos(): Collection<Trabajador>        (activos, ordenados por nombre)
  ├── resumenDeFecha(string $fecha): array                 (asistentes, noAsistentes, conteos)
  ├── estadoPorMes(string $mes): array                     (días del mes → asistentes/totalActivos)
  └── sincronizarMarca(string $fecha, array $marcas): array (upsert + delete omitidos → resumen)
    │
    ▼
Asistencia (Eloquent Model)
  tabla: asistencias
  fillable: [trabajador_id, fecha, hora_entrada]
  casts:    [fecha => date]
  relación: trabajador() BelongsTo → Trabajador
    │
    ▼
Blade Views (extienden layouts.app)
  resources/views/asistencia/index.blade.php
    + componente <x-modal id="modal-asistencia" maxWidth="2xl">
    + @vite('resources/js/asistencia/index.js')
    │
    ▼
resources/js/asistencia/index.js   (delegación de eventos + fetch; calendar vanilla)
  recursos/js/modal.js             (modal global, openModal/closeModal)
```

El modelo `Asistencia` es auditable vía `AuditModelObserver` (añadido a `AuditServiceProvider` y `AuditService`).

---

## Componentes e Interfaces

### AsistenciaController

**Namespace:** `App\Http\Controllers`  
**Ruta del archivo:** `app/Http/Controllers/AsistenciaController.php`

| Método | HTTP | URI | Descripción |
|--------|------|-----|-------------|
| `index()` | GET | `/asistencia` | Devuelve la vista del calendario (mes actual) |
| `porFecha(Request $request)` | GET | `/asistencia/por-fecha?fecha=YYYY-MM-DD` | Devuelve JSON con resumen y detalle del día |
| `porMes(Request $request)` | GET | `/asistencia/por-mes?mes=YYYY-MM` | Devuelve JSON con días del mes y conteos |
| `marcar(Request $request)` | POST | `/asistencia/marcar` | Sincroniza las marcas de la fecha; devuelve JSON con el resumen |

**Validaciones inline** (convención del proyecto, sin Form Requests):

```php
// porFecha
$data = $request->validate([
    'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
]);

// porMes
$data = $request->validate([
    'mes' => ['required', 'date_format:Y-m'],
]);

// marcar
$data = $request->validate([
    'fecha'  => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
    'marcas' => ['nullable', 'array'],
    'marcas.*' => ['required', 'date_format:H:i'],
]);
```

Notas de validación:

- `before_or_equal:today` impide marcar fechas futuras (no se puede registrar una entrada que aún no existe).
- El formato `H:i` acepta de 00:00 a 23:59; excepcionalmente se permite `24:00` en negocio? **No**: se rechaza, el rango es 00:00–23:59.
- En `marcar`, tras validar, se filtran los ids que no pertenecen a trabajadores activos (los inactivos se ignoran en silencio, nunca se crean marcas para ellos). Si `marcas` llega vacío/ausente, equivale a "nadie asistió" → se eliminan todas las marcas activas de la fecha.

### AsistenciaService

**Namespace:** `App\Services`  
**Ruta del archivo:** `app/Services/AsistenciaService.php`

```php
class AsistenciaService
{
    public function trabajadoresActivos(): Collection; // Trabajador::where('estado', true)->orderBy('nombre_completo?') → orderBy('nombre')

    /**
     * @return array{
     *   fecha: string,
     *   total_activos: int,
     *   asistieron: int,
     *   no_asistieron: int,
     *   asistentes: array<int, array{trabajador_id:int, nombre_completo:string, hora_entrada:string}>,
     *   no_asistentes: array<int, array{trabajador_id:int, nombre_completo:string}>,
     * }
     */
    public function resumenDeFecha(string $fecha): array;

    /**
     * @return array{
     *   total_activos: int,
     *   dias: array<string, array{asistentes:int, total_activos:int}>, // key: 'YYYY-MM-DD'
     * }
     */
    public function estadoPorMes(string $mes): array;

    public function sincronizarMarca(string $fecha, array $marcas): array; // devuelve resumenDeFecha tras el sync
}
```

**Lógica de `resumenDeFecha`:**

```
activos        = trabajadoresActivos()
marcas         = Asistencia::where('fecha', $fecha)->get()->keyBy('trabajador_id')
asistentes     = activos con marca, ordenados por hora_entrada ↑ luego nombre ↑
no_asistentes  = activos sin marca, ordenados por nombre ↑
asistieron     = count(asistentes)
no_asistieron  = count(activos) - asistieron
total_activos  = count(activos)
```

**Lógica de `sincronizarMarca`** (full-sync, en `DB::transaction`):

```
filtro  = { id ∈ $marcas si el trabajador está activo }
porDia  = Asistencia::where('fecha', $fecha)
inserta/actualiza: porDia con trabajador_id ∈ filtro → updateOrCreate(['fecha','trabajador_id'], ['hora_entrada'])
elimina:           porDia cuyo trabajador_id ∉ filtro (solo trabajadores activos)
retorna            resumenDeFecha($fecha)
```

La restricción `unique(fecha, trabajador_id)` es la red de seguridad ante condiciones de carrera; `updateOrCreate` más `DB::transaction` la complementan.

### Rutas

En `routes/web.php`, junto al resto de la gestión de usuarios:

```php
Route::middleware('permission:acceso-asistencia')->group(function () {
    // Ajax routes — deben registrarse ANTES de rutas dinámicas (convención del proyecto)
    Route::get('/asistencia/por-fecha', [AsistenciaController::class, 'porFecha'])->name('asistencia.porFecha');
    Route::get('/asistencia/por-mes',   [AsistenciaController::class, 'porMes'])->name('asistencia.porMes');
    Route::post('/asistencia/marcar',   [AsistenciaController::class, 'marcar'])->name('asistencia.marcar');
    Route::get('/asistencia',           [AsistenciaController::class, 'index'])->name('asistencia.index');
});
```

No se usa `Route::resource`: la feature no necesita operaciones por registro individual (el full-sync cubre crear/editar/eliminar).

### Permisos y menú

- `PermissionSeeder.php` (bloque `// Personal`): añadir `'acceso-asistencia'`.
- `layouts/app.blade.php`:
  - `@php`: `$userManagementActive = request()->routeIs('users.*', 'roles.*', 'trabajadores.*', 'asistencia.*');`
  - Desktop y móvil: entrada "Asistencia" en el dropdown "Gestión de usuarios" con `@can('acceso-asistencia')` y estado activo con `request()->routeIs('asistencia.*')`.

### Auditoría

- `app/Providers/AuditServiceProvider.php`: añadir `Asistencia::class` a la lista de observables.
- `app/Services/AuditService.php`: añadir `Asistencia::class` a `AUDITABLE` y al mapa `$modulos` → `'asistencias'`.

---

## Modelos de Datos

### Migración `2026_09_12_000001_create_asistencias_table.php`

```php
Schema::create('asistencias', function (Blueprint $table) {
    $table->id();
    $table->foreignId('trabajador_id')->constrained()->onDelete('cascade');
    $table->date('fecha');
    $table->time('hora_entrada');
    $table->timestamps();

    $table->unique(['fecha', 'trabajador_id']);
    $table->index('fecha');
});
```

### Modelo `app/Models/Asistencia.php`

```php
class Asistencia extends Model
{
    use HasFactory;

    protected $table    = 'asistencias';
    protected $fillable = ['trabajador_id', 'fecha', 'hora_entrada'];
    protected $casts    = ['fecha' => 'date'];

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }
}
```

**Decisiones de esquema:**

- `fecha` como `DATE` + `hora_entrada` como `TIME` (separadas, no `datetime`): permite consultas por día con `where('fecha', $fecha)` simples y es el mismo patrón de `Lavado.fecha`.
- `unique(fecha, trabajador_id)`: un trabajador solo puede tener una marca por día. Es la garantía estructural de "no doble marca".
- `onDelete('cascade')`: si se elimina un trabajador (eliminación protegida existente), sus marcas desaparecen con él, coherente con `lavado_trabajadores`.
- Sin `deleted_at`: consistente con el resto del sistema (eliminación permanente).

### Impacto en entidades existentes

| Entidad | Cambio |
|---------|--------|
| `Trabajador` | Ninguno (se usa tal cual; `estado`, `nombre_completo`, `foto_url`) |
| `AuditService` / `AuditServiceProvider` | Añadir `Asistencia` |
| `PermissionSeeder` | Añadir `acceso-asistencia` |
| `layouts/app.blade.php` | Entrada de menú + variable de activo |

`DataNormalizer` no interviene: la feature no recibe texto libre (fechas y horas se validan por formato; las horas de entrada no se normalizan).

---

## Vistas Blade

### `resources/views/asistencia/index.blade.php`

Extiende `layouts.app`. Contiene, dentro de `@section('content')`:

1. **Encabezado**: título "Asistencia" + botón "Registrar asistencia de hoy" (`data-modal-open="modal-asistencia"`).
2. **Barra de navegación del calendario** (`#asistencia-nav`): botones `data-nav-prev` / `data-nav-next` y etiqueta `#asistencia-titulo-mes` (p. ej. "Septiembre 2026").
3. **Cuadrícula** (`#asistencia-calendario`): contenedor con tabla de 7 columnas; encabezados L M X J V S D; celdas generadas por JS.
4. **Leyenda**: verde = asistieron todos, ámbar = asistencia parcial, rojo = nadie asistió, gris = sin datos / futuro.
5. **Modal** `<x-modal id="modal-asistencia" maxWidth="2xl" title="Detalle de asistencia">` con:
   - Cabecera de fecha `#asistencia-fecha` (fecha descriptiva).
   - Resumen `#asistencia-resumen`: dos tarjetas con conteos e indicadores de color.
   - Listados `#asistencia-asistentes` y `#asistencia-no-asistentes`.
   - Modo de gestión `#asistencia-gestion` (oculto para fechas futuras): filas por trabajador activo con checkbox + hora.
   - Feedback `#asistencia-mensaje`.
   - Footer: botón "Guardar asistencia" (solo modo gestión) + botón cerrar.
6. Datos iniciales inline antes del `@vite`:

```blade
<script>
    window.asistenciaHoy = "{{ now()->format('Y-m-d') }}";
    window.asistenciaTotalActivos = {{ $totalActivos }};
</script>
@vite('resources/js/asistencia/index.js')
```

### Estilo (guías obligatorias)

- Contenedores: `bg-surface rounded-lg border border-main`; título `text-primary`; textos secundarios `text-secondary`.
- Modales: `bg-surface`, footer `bg-gray-50 dark:bg-slate-800/50` (componente ya lo aplica vía prop del slot).
- Celdas del calendario: `bg-surface`, hover `hover:bg-slate-800/50`, día actual con borde resaltado.
- Listas del modal: filas `py-3`/`py-4`, divisores `divide-main`, `overflow-y-auto max-h-[22rem]` para no desbordar.
- Indicadores de color: círculo `h-2.5 w-2.5 rounded-full` con `bg-emerald-500` (verde), `bg-amber-500` (ámbar), `bg-red-500` (rojo), `bg-slate-500` (gris).

### Colores del calendario (clase de celda)

| Estado | Color | Clases |
|--------|-------|--------|
| Asistieron todos los activos | verde | `bg-emerald-500/15 text-emerald-600 dark:text-emerald-400` + punto `bg-emerald-500` |
| Asistencia parcial | ámbar | `bg-amber-500/15 text-amber-600 dark:text-amber-400` + punto `bg-amber-500` |
| Nadie asistió (con activos) | rojo | `bg-red-500/15 text-red-600 dark:text-red-400` + punto `bg-red-500` |
| Sin datos / futuro | neutro | celda estándar + punto `bg-slate-500` (oculto en futuro) |

---

## Frontend (JS)

**Archivo:** `resources/js/asistencia/index.js` (vanilla, ES modules, delegación de eventos en `document`).

### Funciones puras exportadas (target de los property tests JS)

```js
export const INICIO_SEMANA = 1; // Lunes (ISO 8601). Decisión UI revisable.

// Devuelve Array(42) de {year, month, day} | null para el mes pedido.
// month: 0-based (como Date). null = celda fuera del mes.
export function buildMonthGrid(year, month) {}

// Devuelve 'YYYY-MM-DD' con padding.
export function fechaKey(year, month, day) {}

// Parsea 'YYYY-MM-DD' → {year, month, day}.
export function parseFechaKey(key) {}

// Compara una fechaKey con la fecha de hoy (string 'YYYY-MM-DD' o Date).
// true si la fecha es hoy o pasada (consultable y gestionable).
export function esFechaPasadaOActual(key, hoyKey) {}

// Clasifica un día según conteos: 'completo' | 'parcial' | 'nulo' | 'sin-datos'.
export function claseDia(asistentes, totalActivos) {}

// Etiqueta descriptiva de la fecha: 'Sábado, 12 de septiembre de 2026'.
export function etiquetaFecha(key) {}
```

### Comportamiento

1. **init**: `buildMonthGrid` del mes actual, render de celdas (delegación: cada `<button data-dia="YYYY-MM-DD">`), navegación prev/next recomputando el grid, `fetch('/asistencia/por-mes?mes=YYYY-MM')` para pintar puntos de color.
2. **Clic en día**: si es futuro → no hace nada. Si es pasado/actual → `fetch('/asistencia/por-fecha?fecha=...')`; con respuesta OK puebla resumen + listas (+ modo gestión si corresponde) y `openModal('modal-asistencia')`; con error 422/500 muestra mensaje y no abre el modal.
3. **Modo gestión**: al poblarse, cada trabajador activo genera fila `checkbox + nombre + <input type="time">`; el checkbox checcado si existe marca previa. Guardar → `POST /asistencia/marcar` con `{fecha, marcas}` (objeto `{ [id]: "HH:MM" }`), headers `X-CSRF-TOKEN`; con respuesta OK repinta modal y calendario; con 422 muestra los errores dentro del modal.
4. **fetch**: headers `'Accept': 'application/json'`, `'Content-Type': 'application/json'`, `'X-CSRF-TOKEN'` desde `<meta name="csrf-token">` (patrón `stock-modal.js`).

**Vite:** añadir `'resources/js/asistencia/index.js'` al `input` de `vite.config.js` (bloque `// Asistencia`, junto a Trabajadores).

---

## Propiedades de Corrección

*Una propiedad es una característica o comportamiento que debe mantenerse verdadero en todas las ejecuciones válidas del sistema. Las propiedades sirven como puente entre las especificaciones legibles por humano y las garantías verificables por máquina.*

El feature tiene lógica pura y comprobable con PBT en tres capas: conteos server-side, guardado full-sync y generación de la cuadrícula del calendario (frontend).

**Reflexión sobre redundancia:**

- Los criterios de conteo (Requisito 5.4 y 6) se consolidan en la **Propiedad 1**.
- Los criterios de `marcar` (Requisito 7.4 y 7.5) se consolidan en las **Propiedades 2 y 3**.
- Los criterios de acceso (Requisito 8.5 y 8.6) se consolidan en las **Propiedades 4 y 5**.
- Las propiedades de la cuadrícula y la etiqueta de fecha son puramente de frontend y se prueban con fast-check en Vitest.

---

### Propiedad 1: Los conteos particionan el universo de activos

*Para cualquier* fecha `Y-m-d` y cualquier conjunto de marcas sobre trabajadores activos, los conteos de `resumenDeFecha` deben cumplir `asistieron + no_asistieron = total_activos`, y `asistentes ∪ no_asistentes` deben ser exactamente los trabajadores activos (sin solapamiento ni omisiones). Los trabajadores inactivos nunca aparecen en ninguna lista ni conteo.

**Valida: Requisitos 5.4, 6.1, 6.2, 6.3**

---

### Propiedad 2: El guardado full-sync refleja exactamente el payload

*Para cualquier* fecha no futura y cualquier mapa `{ trabajador_id: "HH:MM" }` sobre trabajadores activos, tras `sincronizarMarca` la tabla `asistencias` debe contener exactamente una fila por cada id del payload (con su hora), y cero filas para los trabajadores activos omitidos. Los ids de trabajadores inactivos presentes en el payload no deben generar filas ni errores.

**Valida: Requisito 7.4, 7.6**

---

### Propiedad 3: El guardado es idempotente

*Para cualquier* payload válido, aplicar `sincronizarMarca` dos veces consecutivas sobre la misma fecha (sin cambios intermedios) debe producir el mismo estado de base de datos que la primera aplicación.

**Valida: Requisito 7.5**

---

### Propiedad 4: Todas las rutas del módulo requieren autenticación

*Para cualquier* ruta del módulo `asistencia` (index, porFecha, porMes, marcar) y cualquier parámetro válido, una petición sin sesión autenticada debe redirigir a `/login`.

**Valida: Requisito 8.5**

---

### Propiedad 5: Todas las rutas del módulo requieren `acceso-asistencia`

*Para cualquier* ruta del módulo `asistencia`, una petición de un usuario autenticado sin el permiso `acceso-asistencia` debe ser denegada (HTTP 403).

**Valida: Requisito 8.6**

---

### Propiedad 6 (JS): La cuadrícula cubre exactamente el mes

*Para cualquier* par `(year, month)` con `month ∈ [0..11]`, `buildMonthGrid` debe devolver un arreglo de 42 elementos donde los elementos no nulos correspondan exactamente a los días del mes, en orden ascendente, sin duplicados ni omisiones, y con `null` en las celdas vacías delante y detrás.

**Valida: Requisito 2.2**

---

### Propiedad 7 (JS): El día de la semana del día 1 coincide con la celda inicial

*Para cualquier* `(year, month)`, la primera celda no nula de `buildMonthGrid` debe corresponder al día 1 del mes, y el índice de esa celda debe ser igual a `(díaSemanaISO(día 1) - INICIO_SEMANA + 7) % 7`.

**Valida: Requisito 2.2**

---

### Propiedad 8 (JS): FechaKey es un biyectivo reversible

*Para cualquier* `(year, month, day)` válido, `parseFechaKey(fechaKey(year, month, day))` debe devolver exactamente el triple original, y la cadena debe tener formato canónico `YYYY-MM-DD`.

**Valida: Requisito 2.2, Requisito 4 (claves de consulta)**

---

## Manejo de Errores

| Escenario | Comportamiento |
|-----------|----------------|
| Fecha ausente, mal formateada o futura en `porFecha`/`marcar` | HTTP 422 con mensajes de validación (`fecha`). En `marcar` no se toca la BD. |
| `mes` ausente o mal formateado en `porMes` | HTTP 422 con mensaje de validación (`mes`). |
| Hora mal formateada (`marcas.*`) | HTTP 422; ningún registro se crea/borra (la validación ocurre antes de la transacción). |
| Trabajador inactivo en `marcas` | Se ignora en silencio, sin error (decisión: no bloquea el guardado). |
| Usuario sin permiso | Middleware Spatie → HTTP 403 antes de entrar al controlador. |
| Usuario no autenticado | Middleware `auth` → redirección a `/login`. |
| `Asistencia` inexistente | No aplica: no hay rutas por registro individual (full-sync). |
| Error de red / 500 en el frontend | El modal no se abre (o se mantiene cerrado) y se muestra un mensaje de error breve; no queda un modal a medias. |
| Concurrencia en `marcar` | `unique(fecha, trabajador_id)` + `updateOrCreate` dentro de `DB::transaction`. Ante una condición de carrera es más probable un reintento 409/500 que un dato corrupto; se documenta como límite aceptado del alcance. |

---

## Estrategia de Pruebas

### Enfoque dual

PHPUnit (Laravel Feature Tests + property tests server-side en loop de 100 iteraciones, sobre SQLite en memoria) para el backend, y Vitest + fast-check para las funciones puras del frontend. Todos los tests etiquetados `// Feature: asistencia, Property N: <descripción>` con `**Valida: Requisitos ...**`.

### Tests de ejemplo (PHPUnit) — `tests/Feature/Asistencia/AsistenciaTest.php`

Patrón: `RefreshDatabase`, `Permission::firstOrCreate(['name' => 'acceso-asistencia', 'guard_name' => 'web'])` + `givePermissionTo`, trabajadores creados con el factory o `Trabajador::create([...])` manual (el factory actual no genera DNI/apellidos).

| Test | Criterio |
|------|----------|
| `GET /asistencia` sin sesión → redirect `/login` | 8.5 |
| `GET /asistencia` con usuario sin permiso → 403 | 8.6 |
| `GET /asistencia` con permiso → 200 y renderiza el calendario + datos iniciales | 2.1, 4 |
| `porFecha` con 2 activos (1 marca) → `asistieron=1`, `no_asistieron=1`, `total_activos=2`, lista de asistente con `hora_entrada` | 5, 6 |
| `porFecha` excluye trabajadores inactivos de listas y conteos | 6, 5.4 |
| `porFecha` rechaza fecha futura y formato inválido → 422 | validación |
| `porMes` devuelve solo días con marcas y sus conteos; días futuros del mes no generan registros | 3.1, 3.5 |
| `marcar` crea marcas nuevas, actualiza horas y elimina las desmarcadas | 7.4 |
| `marcar` idempotente: mismo payload dos veces → mismos registros | 7.5 |
| `marcar` ignora trabajadores inactivos en el payload | 7.6 |
| `marcar` rechaza fecha futura / hora inválida → 422 y sin cambios en BD | validación, 7.7 |
| `marcar` con `marcas` vacío elimina todas las marcas de activos de la fecha | 7.4 |

### Property tests (PHPUnit) — `tests/Feature/Asistencia/AsistenciaPropertyTest.php`

| Propiedad | Test (100 iteraciones) |
|-----------|------------------------|
| Property 1 | Para N activos aleatorios y un subconjunto aleatorio marcado, `resumenDeFecha` cumple `asistieron + no_asistieron = total_activos` y la unión de listas es exactamente los activos (Faker para nombres/DNIs; `marcas` aleatorias por iteración). |
| Property 2 | Para payloads aleatorios sobre activos, tras `sincronizarMarca` la BD tiene exactamente las filas del payload (por fecha). |
| Property 3 | Para payloads aleatorios, aplicar dos veces produce el mismo estado que una sola. |
| Property 4 | Para cada ruta del módulo con parámetros válidos, sin auth → redirect a `/login`. |
| Property 5 | Para cada ruta del módulo, usuario autenticado sin permiso → 403. |

### Property tests (Vitest) — `tests/js/asistencia/index.property.test.js`

Entorno Node (sin DOM), fast-check con `{ numRuns: 100 }`:

| Propiedad | Test |
|-----------|------|
| Property 6 | `fc.integer({min:2000,max:2035})` + `fc.integer({min:0,max:11})` → `buildMonthGrid` devuelve 42 celdas; días no nulos = 28/29/30/31 según mes, únicos, ordenados; celdas nulas solo al inicio y final. |
| Property 7 | El índice de la primera celda no nula coincide con la posición ISO del día 1 (áncora = Lunes). |
| Property 8 | Round-trip `parseFechaKey(fechaKey(y,m,d)) === {y,m,d}` para fechas válidas, con formato canónico. |

### Edge cases

- Fechas límite: fin de mes en meses de 28/29/30/31 días; años bisiestos (2024, 2028).
- Meses donde el día 1 cae en sábado/domingo (grid con 1–2 filas nulas al inicio).
- Trabajador activo sin nombre/apellidos (solo `nombre`) en `nombre_completo`.
- Día actual y día 1 del mes (borde de la celda "hoy").
- `marcas` enviado como `{}` (nadie asistió) y trabajadores con `estado=false`.

---

## Notas de alcance

- **Fuera de alcance (fases futuras)**: tardanzas con horario de trabajo, kiosco de marcación con DNI, ausencias justificadas (vacaciones/licencia), reporte mensual por trabajador, exportación. El esquema (`hora_entrada` TIME y `fecha` DATE) deja espacio para añadir `estado` (presente/tardanza/ausente) y `tipo` (descanso) en el futuro sin romper nada.
- El conteo considera únicamente trabajadores **activos**; un trabajador `estado=false` no cuenta ni como asistente ni como no asistente.
- Esta feature no modifica `Trabajador`, `Caja` ni `DataNormalizer`.