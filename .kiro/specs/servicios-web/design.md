# Documento de Diseño Técnico — `servicios-web`

## Visión General

Conecta la página pública `/nuestros-servicios` con la entidad real `Servicio`. Se añaden los campos de publicación (`activo`, `orden`, `icono`, `imagen`) al CRUD existente, se agrega el toggle activar/inactivar (mismo patrón que `Producto`), se gestiona la imagen con Cloudinary y se deja de maquetar categorías ficticias: la página pública muestra los `Servicio` con `activo = true`, ordenados por `orden`/`nombre`.

La cabecera (título/intro) de la página pública proviene del panel de contenidos (`contenido-web`), pero **este spec no lo implementa**; solo consume `servicios_titulo` / `servicios_intro` con fallback de texto actual. El panel de contenidos se documenta en `.kiro/specs/contenido-web`.

---

## Arquitectura

```
PATCH /servicios/{servicio}/toggle-status   (permission:acceso-servicios)
        │
        ▼
ServicioController
    ├── index()   → servicios.index  (paginate(10), todas filas)
    ├── create()  → servicios.create
    ├── store()   → validar + Cloudinary → Servicio::create() → redirect
    ├── edit()    → servicios.edit
    ├── update()  → validar + Cloudinary(reemplazo) → $servicio->update() → redirect
    ├── toggleStatus() → JSON {success, activo, message} + auditoría
    └── destroy() → Cloudinary(destroy imagen) → delete() → redirect
        │
        ▼
Servicio (Model)  ←→  servicios (SQLite)   activo/orden/icono/imagen añadidos

GET /nuestros-servicios  (pública, sin permiso)
        │
        ▼
PaginaInicioController::servicios()
    ├── Servicio::where('activo', true)->orderBy('orden')->orderBy('nombre')->get()
    ├── ContenidoWebService::text('servicios_titulo'/'servicios_intro')
    └── → publica.servicios
```

---

## Componentes e Interfaces

### Migración

`database/migrations/2026_09_13_000002_add_web_fields_to_servicios_table.php`

```php
Schema::table('servicios', function (Blueprint $table) {
    $table->boolean('activo')->default(true)->after('precio');
    $table->unsignedInteger('orden')->default(0)->after('activo');
    $table->string('icono', 30)->nullable()->default('sparkles')->after('orden');
    $table->string('imagen')->nullable()->after('icono');
});
```

### Archivos a crear

| Archivo | Propósito |
|---|---|
| `resources/js/toggle-status.js` | Módulo compartido del toggle AJAX (extraído de `productos/index.js`) |
| `resources/js/servicios/index.js` | Inicializa el toggle en el listado de servicios |
| `resources/views/publica/partials/card-servicio-item.blade.php` | Card de servicio real para la página pública |
| `tests/Feature/ServicioWebToggleTest.php` | Tests del toggle + campos web |
| `tests/Feature/ServicioCloudinaryTest.php` | Tests de imagen Cloudinary (mocks) |

### Archivos a modificar

| Archivo | Cambio |
|---|---|
| `app/Models/Servicio.php` | `$fillable` + `$casts` nuevos |
| `app/Http/Controllers/ServicioController.php` | Validación, store/update (Cloudinary), `toggleStatus()`, destroy con imagen |
| `routes/web.php` | `Route::patch('servicios/{servicio}/toggle-status', ...)` antes del resource |
| `resources/views/servicios/index.blade.php` | Columna "Estado" (badge + toggle) + búsqueda de fila; `@vite` del módulo |
| `resources/views/servicios/create.blade.php` / `edit.blade.php` | Campos `activo`, `orden`, `icono`, `imagen` |
| `resources/js/productos/index.js` | Refactor para reutilizar `toggle-status.js` |
| `vite.config.js` | Añadir `resources/js/servicios/index.js` al input |
| `app/Http/Controllers/PaginaInicioController.php` | `servicios()` e `index()` con datos reales |
| `resources/views/publica/servicios.blade.php` | Grid de servicios reales (reemplaza mosaico de categorías) |
| `resources/views/publica/inicio.blade.php` | `inicio_mostrar_servicios` + servicio real en tarjeta |

---

### Modelo `Servicio`

```php
protected $fillable = ['nombre', 'descripcion', 'precio', 'activo', 'orden', 'icono', 'imagen'];

protected $casts = [
    'precio' => 'decimal:2',
    'activo' => 'boolean',
    'orden'  => 'integer',
];

public function scopeWeb($query)
{
    return $query->where('activo', true)->orderBy('orden')->orderBy('nombre');
}
```

### ServicioController

```php
private const ICONOS = ['sparkles', 'shield', 'oil', 'layers', 'paint', 'wrench', 'droplets', 'car'];

private function reglas(): array
{
    return [
        'nombre'      => self::NOMBRE_RULES,
        'descripcion' => self::DESCRIPCION_RULES,
        'precio'      => ['required', 'numeric', 'gt:0'],
        'activo'      => ['sometimes', 'boolean'],
        'orden'       => ['nullable', 'integer', 'min:0'],
        'icono'       => ['nullable', 'string', Rule::in(self::ICONOS)],
        'imagen'      => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
    ];
}

public function store(Request $request): RedirectResponse
{
    $validated = $request->validate($this->reglas());
    $data = $request->only('nombre', 'descripcion', 'precio');
    $data['activo'] = $request->boolean('activo', true);
    $data['orden']  = (int) ($validated['orden'] ?? 0);
    $data['icono']  = $validated['icono'] ?? 'sparkles';

    if ($request->hasFile('imagen')) {
        $data['imagen'] = Cloudinary::uploadApi()->upload($request->file('imagen')->getRealPath())['secure_url'];
    }

    Servicio::create($data);
    return redirect()->route('servicios.index')->with('success', 'Servicio creado correctamente.');
}
```

**update()**: mismas reglas. Si `$request->hasFile('imagen')` → borrar imagen previa (mismo helper que `ProductoController::update()`) y subir la nueva. `$data['activo'] = $request->boolean('activo', (bool) $servicio->activo);`

**toggleStatus()** — réplica de `ProductoController::toggleStatus()`:

```php
public function toggleStatus(Servicio $servicio): JsonResponse
{
    try {
        app(AuditService::class)->anotarAccion('toggle estado');
        $servicio->activo = ! $servicio->activo;
        $servicio->save();

        return response()->json([
            'success' => true,
            'activo'  => (bool) $servicio->activo,
            'message' => 'Estado actualizado correctamente.',
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error al actualizar el estado.'], 500);
    }
}
```

**destroy()**: destruir `$servicio->imagen` en Cloudinary (fuera de la transacción, patrón `ProductoController::destroy()` L313-326) antes de mantener la comprobación de `lavados()` y `delete()`.

### Rutas

Dentro del grupo `permission:acceso-servicios` (routes/web.php L132-136), **antes** del resource:

```php
Route::patch('servicios/{servicio}/toggle-status', [ServicioController::class, 'toggleStatus'])
    ->name('servicios.toggleStatus');

Route::resource('servicios', ServicioController::class)
    ->except(['show'])
    ->parameters(['servicios' => 'servicio']);
```

### Vistas panel

**`servicios/index.blade.php`** — agregar antes de "Acciones":
- Badge: `<span data-badge class="...">{{ $servicio->activo ? 'Activo' : 'Inactivo' }}</span>`
- Toggle: `<button type="button" data-toggle-status data-url="{{ route('servicios.toggleStatus', $servicio) }}" ...>Activar/Inactivar</button>`

Agregar al final del `@section('content')`:
```blade
@vite(['resources/js/servicios/index.js'])
```

**`create.blade.php` / `edit.blade.php`** — campos nuevos:
- `Activo` checkbox (edit: `old('activo', $servicio->activo)`)
- `Orden` number `min="0"` (edit: `old('orden', $servicio->orden)`)
- `Icono` select con `@foreach` de `ServicioController::ICONOS` (default `sparkles`)
- `Imagen` file input + preview de la imagen actual en edit

### JS compartido `resources/js/toggle-status.js`

Extraído de `productos/index.js` con estas mejoras de reutilización:

- `updateBadge(badge, activo)` y `updateButton(button, activo, nombre)` sin cambios.
- `initToggleStatus()` lee `data-url` y **localiza el badge dentro de la misma fila**: `button.closest('tr').querySelector('[data-badge]')`, sin depender de `data-producto-id`.
- El `aria-label` usa `data-nombre` del botón.
- `productos/index.js` pasa a importar de `./toggle-status.js` (manteniendo o mapeando sus atributos actuales vía `data-id`/`data-nombre`), el comportamiento público no cambia.
- `servicios/index.js`:

```js
import { initToggleStatus } from '../toggle-status';

document.addEventListener('DOMContentLoaded', () => initToggleStatus());
```

### Página pública

**`PaginaInicioController::servicios()`**:

```php
public function servicios(): View
{
    $servicios = Servicio::web()->get();
    $contenido = app(ContenidoWebService::class);
    $empresa = config('carwash');

    return view('publica.servicios', compact('servicios', 'contenido', 'empresa'));
}
```

**`PaginaInicioController::index()`** — sección servicios:

```php
$servicios = Servicio::web()->take(3)->get();
```

**`publica/servicios.blade.php`** — reemplazar el mosaico de categorías por:

```
[encabezado: breadcrumb + h1 "Nuestros servicios" + p de $_contenido->text('servicios_intro')]
[sección: eyebrow "Servicios" + h2 $_contenido->text('servicios_titulo', '¿Qué necesita tu auto?')]
[grid responsive 1 / 2 / 3 columnas]
    @forelse ($servicios as $servicio)
        @include('publica.partials.card-servicio-item', ['servicio' => $servicio])
    @empty
        "Aún no tenemos servicios disponibles."  (text-center)
    @endforelse
[sellos de confianza]
```

**`publica/partials/card-servicio-item.blade.php`** — card en estilo del tema:
- Fondo: si `$servicio->imagen` → `<img>` con `object-cover` + overlay navy; si no → gradiente navy.
- Ícono: `@include('publica.partials.icono-servicio', ['icono' => $servicio->icono])`.
- `mb_strtoupper($servicio->nombre)`, descripción y precio `S/ {{ number_format($servicio->precio, 2) }}`.

**`publica/inicio.blade.php`** — sección 3 envuelta en:

```blade
@if ($contenido->bool('inicio_mostrar_servicios'))
    ... @foreach ($servicios as $servicio) ... {{ $servicio->icono }}, {{ $servicio->nombre }}, {{ $servicio->descripcion }} ...
@endif
```

---

## Propiedades de Corrección

### Propiedad 1: Servicios publicados = activos, ordenados y completos

*Para cualquier* conjunto de servicios en BD, la lista de la página pública `/nuestros-servicios` contiene exactamente los servicios con `activo = true`, ordenados por `orden` asc y luego `nombre`, y cada tarjeta muestra nombre, descripción, ícono, precio con formato `S/ X,XXX.XX` e imagen cuando existe.

**Valida:** Requisitos 5.1, 5.2, 5.3

---

### Propiedad 2: Toggle complementa el estado

*Para cualquier* servicio con estado `activo` conocido (true/false), una llamada a `toggleStatus()` invierte exactamente ese estado, persiste el cambio, retorna JSON `{success: true, activo: <nuevo>}` y registra una acción de auditoría. Dos llamadas consecutivas devuelven el estado original (involución).

**Valida:** Requisitos 2.3, 2.4

---

### Propiedad 3: Imagen conservada o reemplazada sin perderse

*Para cualquier* servicio con imagen existente y cualquier operación de edición: si no se envía imagen nueva, la imagen anterior se conserva intacta; si se envía imagen nueva, la anterior se destruye en Cloudinary (método mockeado) y la nueva URL se persiste.

**Valida:** Requisitos 3.3, 3.4, 4.1, 4.2

---

### Propiedad 4: Rechazo de campos web inválidos sin persistir

*Para cualquier* valor inválido de `icono` (fuera de la lista, vacío no permitido si se envía), `orden` (negativo o no entero), `activo` (no booleano) o `imagen` (archivo no imagen o > 2MB), tanto en creación como en edición, el sistema rechaza la operación, no persiste cambios y muestra errores de validación.

**Valida:** Requisitos 3.2, 3.3, 3.5

---

### Propiedad 5: Inicio respeta el toggle de sección y el estado

*Para cualquier* configuración de `inicio_mostrar_servicios` y cualquier conjunto de servicios: si el toggle es `false` la sección no se renderiza; si es `true`, muestra como máximo los 3 primeros servicios activos (según orden/nombre) y nunca un servicio inactivo.

**Valida:** Requisitos 6.1, 6.2, 6.3

---

### Propiedad 6: Toggle protegido por permiso

*Para cualquier* usuario autenticado sin `acceso-servicios`, `PATCH /servicios/{servicio}/toggle-status` retorna HTTP 403 sin cambiar el estado; para usuarios no autenticados retorna redirect a `/login`.

**Valida:** Requisitos 7.1, 7.2, 7.3

---

## Manejo de Errores

- **Validación (HTTP 422)**: Laravel redirige con `$errors` y `old()`; los campos nuevos siguen el patrón de errores (`border-red-400 bg-red-50` + `@error`).
- **Toggle fallido**: catch genérico → `{success: false, message}` HTTP 500; el frontend hace `alert()`.
- **Cloudinary destroy**: `try/catch` silencioso (no bloquea CRUD), idéntico a `ProductoController`.
- **Sin servicios activos**: la vista muestra el estado vacío, no un error.
- **404**: Route Model Binding de `{servicio}` inexistente → 404 automático.

---

## Estrategia de Testing

### Tests de ejemplo

**`tests/Feature/ServicioWebToggleTest.php`**
- `PATCH /servicios/{id}/toggle-status` invierte `activo` y retorna JSON correcto (usuario con `acceso-servicios`).
- El toggle se rechaza con 403 sin permiso y no modifica el registro.
- El toggle solicita autenticación (redirect `/login`).
- `store()`/`update()` aceptan `activo`/`orden`/`icono` y persisten.
- `icono` inválido y `orden` negativo son rechazados sin persistir.
- Index muestra badge Activo/Inactivo y botón con `data-toggle-status`.

**`tests/Feature/ServicioCloudinaryTest.php`** (mocks, patrón `ProductoCloudinaryTest`)
- `store()` con `imagen` llama a `Cloudinary->upload()` y persiste `secure_url`.
- `update()` sin imagen nueva conserva la original.
- `update()` con imagen nueva destruye la anterior y persiste la nueva.
- `destroy()` destruye la imagen y elimina el servicio.

**`tests/Feature/PublicaServiciosTest.php`** (actualizar)
- `/nuestros-servicios` muestra solo servicios activos ordenados y su precio formateado.
- Un servicio inactivo no aparece.
- Estado vacío cuando no hay activos.
- Título/intro desde `contenido-web` (`servicios_titulo`).

### Property tests

**`tests/Feature/ServicioWebPropertiesTest.php`** con 100+ iteraciones (patrón `*PropertyTest.php` existente), cada uno con tag `// Feature: servicios-web, Property {N}: <descripción>`:
- **P1**: generados aleatorios de conjuntos de servicios activos/inactivos → la página pública contiene exactamente los activos ordenados.
- **P2**: estados aleatorios → toggle complementa e involuciona (2 llamadas).
- **P4**: `icono`/`orden` inválidos aleatorios → rechazo sin persistir.
- **P5**: config `inicio_mostrar_servicios` aleatoria + servicios aleatorios → sección oculta o primeros 3 activos.

### JS property tests

`tests/js/servicios/toggle-status.property.test.js` (fast-check) — propiedades puras de `toggle-status.js`:
- `updateBadge` asigna clases/texto consistentes con `activo`.
- `updateButton` alterna text entre "Activar"/"Inactivar" y aria-label correspondiente.

**Cobertura mínima esperada**

| Área | Tipo | Archivo |
|---|---|---|
| Toggle + campos web | Feature | `ServicioWebToggleTest.php` |
| Imagen Cloudinary | Feature (mock) | `ServicioCloudinaryTest.php` |
| Página pública de servicios | Feature | `PublicaServiciosTest.php` |
| Propiedades toggle/publicación | Property | `ServicioWebPropertiesTest.php` |
| UI del toggle | JS property | `tests/js/servicios/toggle-status.property.test.js` |

---

## Comandos de verificación

- `vendor/bin/pint`
- `php artisan test`
- `npm run test`
- `npm run build`
- `php artisan migrate` (BD local de desarrollo)