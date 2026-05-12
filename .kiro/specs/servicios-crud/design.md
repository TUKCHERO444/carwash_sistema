# Documento de Diseño Técnico — `servicios-crud`

## Visión General

Este documento describe el diseño técnico para implementar el CRUD de Servicios en el sistema de gestión de taller mecánico. El módulo permite al Administrador registrar, editar y eliminar servicios del catálogo disponible para aplicar a los vehículos en el taller, con su precio unitario de referencia.

El diseño sigue estrictamente los patrones establecidos en el proyecto: controlador resource de Laravel, vistas Blade con Tailwind CSS, protección por rol mediante Spatie Permission, y navegación lateral colapsable. La tabla `servicios` y el modelo `Servicio` ya existen y están correctamente configurados; **no se requieren migraciones adicionales**. El enlace "Servicios" se agrega dentro de la sección "Gestión Administrativa" ya existente en el layout, junto al enlace "Vehículos".

La diferencia principal respecto al módulo de Vehículos es que:
- La tabla `servicios` solo tiene `nombre` y `precio` (sin campo `descripcion`)
- La relación de integridad referencial es `BelongsToMany` a través de la tabla pivote `detalle_servicios` (no `HasMany` directa)

---

## Arquitectura

El módulo se integra en la arquitectura MVC existente sin introducir nuevas capas ni dependencias:

```
HTTP Request
    │
    ▼
routes/web.php  (middleware: auth, role:Administrador)
    │
    ▼
ServicioController
    ├── index()   → servicios.index  (paginación 15/página)
    ├── create()  → servicios.create
    ├── store()   → validar → Servicio::create() → redirect
    ├── edit()    → servicios.edit   (model binding)
    ├── update()  → validar → $servicio->update() → redirect
    └── destroy() → check ingresos (BelongsToMany) → $servicio->delete() → redirect
    │
    ▼
Servicio (Model)  ←→  servicios (tabla SQLite)
    │
    └── ingresos(): BelongsToMany → Ingreso  (a través de detalle_servicios)
```

El layout `resources/views/layouts/app.blade.php` se modifica para actualizar `$gestionAdministrativaActive` y agregar el enlace "Servicios" en el dropdown existente de "Gestión Administrativa", tanto en el sidebar desktop como en el bottom nav móvil.

---

## Componentes e Interfaces

### Archivos a crear

| Archivo | Propósito |
|---|---|
| `app/Http/Controllers/ServicioController.php` | Controlador resource con los 5 métodos CRUD |
| `resources/views/servicios/index.blade.php` | Vista de listado con tabla paginada |
| `resources/views/servicios/create.blade.php` | Vista de formulario de creación |
| `resources/views/servicios/edit.blade.php` | Vista de formulario de edición con datos precargados |

### Archivos a modificar

| Archivo | Cambio |
|---|---|
| `routes/web.php` | Agregar `Route::resource('servicios', ...)` al grupo `role:Administrador` |
| `resources/views/layouts/app.blade.php` | Actualizar `$gestionAdministrativaActive` y agregar enlace "Servicios" en el dropdown existente |

---

### ServicioController

```php
namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServicioController extends Controller
{
    /**
     * Muestra la lista paginada de servicios.
     */
    public function index(): View

    /**
     * Muestra el formulario de creación.
     */
    public function create(): View

    /**
     * Valida y persiste un nuevo servicio.
     *
     * Reglas de validación:
     *   nombre: required | string | max:100
     *   precio: required | numeric | gt:0
     *
     * Éxito:  redirect servicios.index  + flash 'success'
     */
    public function store(Request $request): RedirectResponse

    /**
     * Muestra el formulario de edición con datos precargados.
     * Usa Route Model Binding: Servicio $servicio
     */
    public function edit(Servicio $servicio): View

    /**
     * Valida y actualiza un servicio existente.
     *
     * Mismas reglas de validación que store().
     * Éxito:  redirect servicios.index  + flash 'success'
     */
    public function update(Request $request, Servicio $servicio): RedirectResponse

    /**
     * Elimina un servicio si no tiene ingresos asociados.
     *
     * Si $servicio->ingresos()->exists() → redirect + flash 'error'
     * Si no tiene ingresos               → delete() + redirect + flash 'success'
     */
    public function destroy(Servicio $servicio): RedirectResponse
}
```

**Reglas de validación compartidas** (usadas en `store` y `update`):

```php
$request->validate([
    'nombre' => ['required', 'string', 'max:100'],
    'precio' => ['required', 'numeric', 'gt:0'],
]);
```

**Mensajes flash**:

| Acción | Clave | Mensaje |
|---|---|---|
| Creación exitosa | `success` | `'Servicio creado correctamente.'` |
| Edición exitosa | `success` | `'Servicio actualizado correctamente.'` |
| Eliminación exitosa | `success` | `'Servicio eliminado correctamente.'` |
| Eliminación bloqueada | `error` | `'No se puede eliminar el servicio porque tiene ingresos asociados.'` |

---

## Modelos de Datos

### Tabla `servicios` (ya existe — sin cambios)

```sql
CREATE TABLE servicios (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre     VARCHAR(100) NOT NULL,
    precio     DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Tabla `detalle_servicios` (ya existe — tabla pivote)

```sql
CREATE TABLE detalle_servicios (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    ingreso_id INTEGER NOT NULL REFERENCES ingresos(id) ON DELETE CASCADE,
    servicio_id INTEGER NOT NULL REFERENCES servicios(id) ON DELETE CASCADE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE (ingreso_id, servicio_id)
);
```

> **Nota sobre integridad referencial**: La verificación antes de eliminar se realiza a través de la relación `BelongsToMany`: `$servicio->ingresos()->exists()`. Esto consulta la tabla pivote `detalle_servicios` para determinar si el servicio está referenciado en algún ingreso.

### Modelo `Servicio` (sin cambios)

El modelo existente ya está correctamente configurado:

```php
protected $fillable = ['nombre', 'precio'];
protected $casts    = ['precio' => 'decimal:2'];

public function ingresos(): BelongsToMany
{
    return $this->belongsToMany(Ingreso::class, 'detalle_servicios')
                ->withTimestamps();
}
```

### Rutas

Agregar dentro del grupo `middleware(['auth', 'role:Administrador'])` en `routes/web.php`:

```php
use App\Http\Controllers\ServicioController;

Route::resource('servicios', ServicioController::class)
     ->except(['show'])
     ->parameters(['servicios' => 'servicio']);
```

Esto genera las rutas:

| Método | URI | Nombre | Acción |
|---|---|---|---|
| GET | `/servicios` | `servicios.index` | `index` |
| GET | `/servicios/create` | `servicios.create` | `create` |
| POST | `/servicios` | `servicios.store` | `store` |
| GET | `/servicios/{servicio}/edit` | `servicios.edit` | `edit` |
| PUT/PATCH | `/servicios/{servicio}` | `servicios.update` | `update` |
| DELETE | `/servicios/{servicio}` | `servicios.destroy` | `destroy` |

---

## Estructura de Vistas

### `servicios/index.blade.php`

```
@extends('layouts.app')
@section('content')
  <div class="p-6">
    [Flash messages: session('success') verde / session('error') rojo]

    [Header]
      <h1>Servicios</h1>
      <a href="servicios.create">Crear servicio</a>  ← bg-blue-600

    [Estado vacío si $servicios->isEmpty()]
      "No hay servicios registrados."  ← text-center py-12 text-gray-500 text-sm

    [Tabla si hay registros]
      <thead bg-gray-50>
        Nombre | Precio | Acciones
      <tbody divide-y divide-gray-200, hover:bg-gray-50>
        @foreach $servicios as $servicio
          | {{ $servicio->nombre }}
          | S/ {{ number_format($servicio->precio, 2) }}
          | [Editar] ← bg-gray-100  [Eliminar] ← bg-red-100 + confirm()

    [Paginación]
      {{ $servicios->links() }}
  </div>
@endsection
```

**Formato de precio**: `'S/ ' . number_format($servicio->precio, 2)` produce `S/ 1,500.00`.

**Botón Eliminar** (patrón idéntico a vehículos):
```html
<form method="POST" action="{{ route('servicios.destroy', $servicio) }}" class="inline">
    @csrf
    @method('DELETE')
    <button type="submit"
            onclick="return confirm('¿Estás seguro de que deseas eliminar este servicio?')"
            class="...bg-red-100 text-red-700...">
        Eliminar
    </button>
</form>
```

---

### `servicios/create.blade.php`

```
@extends('layouts.app')
@section('content')
  <div class="p-6">
    [Flash messages]

    [Header]
      <h1>Crear servicio</h1>
      <a href="servicios.index">Volver</a>  ← bg-gray-100

    <div class="bg-white rounded-lg border border-gray-200 p-6 max-w-lg">
      <form action="servicios.store" method="POST" novalidate>
        @csrf

        [Campo: Nombre]
          label: "Nombre *"
          input type="text" name="nombre" value="{{ old('nombre') }}"
          error: border-red-400 bg-red-50 + @error('nombre')

        [Campo: Precio]
          label: "Precio *"
          input type="number" name="precio" step="0.01" min="0.01"
          value="{{ old('precio') }}"
          error: border-red-400 bg-red-50 + @error('precio')

        [Submit]
          <button type="submit" class="bg-blue-600...">Guardar</button>
          <a href="servicios.index">Cancelar</a>
      </form>
    </div>
  </div>
@endsection
```

---

### `servicios/edit.blade.php`

Idéntico a `create.blade.php` con estas diferencias:

- Título: `"Editar servicio"`
- `<form action="{{ route('servicios.update', $servicio) }}" method="POST">`
- Agregar `@method('PUT')` dentro del form
- Valores precargados: `old('nombre', $servicio->nombre)`, `old('precio', $servicio->precio)`
- Botón submit: `"Guardar cambios"`

---

## Modificación del Layout — Sección "Gestión Administrativa"

### Variable de estado activo

Actualizar la línea existente en el bloque `@php` del layout:

```php
// Antes:
$gestionAdministrativaActive = request()->routeIs('vehiculos.*');

// Después:
$gestionAdministrativaActive = request()->routeIs('vehiculos.*', 'servicios.*');
```

### Sidebar desktop — agregar enlace "Servicios"

Dentro del `data-dropdown-menu="gestion-administrativa"` existente, agregar el enlace "Servicios" después del enlace "Vehículos":

```html
<a href="{{ route('servicios.index') }}"
   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
          {{ request()->routeIs('servicios.*') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
    Servicios
</a>
```

### Bottom nav móvil — agregar enlace "Servicios"

Dentro del `data-dropdown-menu="gestion-administrativa-mobile"` existente, agregar el enlace "Servicios" después del enlace "Vehículos":

```html
<a href="{{ route('servicios.index') }}"
   class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
          {{ request()->routeIs('servicios.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
    Servicios
</a>
```

---

## Propiedades de Corrección

*Una propiedad es una característica o comportamiento que debe cumplirse en todas las ejecuciones válidas del sistema — esencialmente, una declaración formal sobre lo que el software debe hacer. Las propiedades sirven como puente entre las especificaciones legibles por humanos y las garantías de corrección verificables por máquina.*

### Propiedad 1: Creación persiste datos válidos

*Para cualquier* nombre válido (string no vacío, máximo 100 caracteres) y precio válido (numérico mayor a 0), al enviar el formulario de creación el sistema debe persistir exactamente un nuevo registro en la tabla `servicios` con los datos enviados y redirigir a `servicios.index` con flash `success`.

**Valida: Requisitos 2.3**

---

### Propiedad 2: Validación rechaza entradas inválidas sin persistir

*Para cualquier* combinación de datos inválidos — nombre vacío, nombre con más de 100 caracteres, precio igual a 0, precio negativo, o precio no numérico — tanto en creación como en edición, el sistema debe rechazar la operación, no persistir ningún cambio en la base de datos, y retornar la vista del formulario con errores de validación.

**Valida: Requisitos 2.4, 2.5, 3.3, 3.4**

---

### Propiedad 3: Edición actualiza datos y preserva identidad del registro

*Para cualquier* servicio existente y cualquier conjunto de datos válidos de actualización, al enviar el formulario de edición el sistema debe actualizar exactamente ese registro (mismo `id`) con los nuevos valores, sin crear registros adicionales ni modificar otros registros, y redirigir a `servicios.index` con flash `success`.

**Valida: Requisitos 3.2**

---

### Propiedad 4: Eliminación libre cuando no hay ingresos asociados

*Para cualquier* servicio que no tenga ingresos asociados en `detalle_servicios`, la operación de eliminación debe completarse exitosamente: el registro debe dejar de existir en la base de datos y el sistema debe redirigir a `servicios.index` con flash `success`.

**Valida: Requisitos 4.1**

---

### Propiedad 5: Eliminación bloqueada por integridad referencial

*Para cualquier* servicio que tenga al menos un ingreso asociado a través de `detalle_servicios`, la operación de eliminación debe ser cancelada: el registro debe seguir existiendo en la base de datos y el sistema debe redirigir a `servicios.index` con flash `error`.

**Valida: Requisitos 4.2**

---

### Propiedad 6: Control de acceso por rol

*Para cualquier* ruta del recurso `servicios` (index, create, store, edit, update, destroy), una petición de un usuario sin el rol `Administrador` — ya sea no autenticado o autenticado con otro rol — debe ser rechazada: redirigir a login si no está autenticado, o retornar HTTP 403 si está autenticado sin el rol correcto.

**Valida: Requisitos 5.1, 5.2**

---

### Propiedad 7: Formato monetario consistente

*Para cualquier* precio decimal positivo almacenado en un servicio, la representación en la vista de listado debe seguir el formato `S/ X,XXX.XX` — es decir, prefijo `S/ `, separador de miles con coma, y exactamente dos decimales separados por punto.

**Valida: Requisitos 1.3**

---

## Manejo de Errores

### Errores de validación (HTTP 422 / redirect back)

Laravel redirige automáticamente de vuelta al formulario con los errores en `$errors` y los valores anteriores en `old()`. Las vistas deben:
- Aplicar `border-red-400 bg-red-50` al input con error
- Mostrar el mensaje con `@error('campo') ... @enderror`
- Repoblar con `old('campo', $valorActual)` (en edición)

### Eliminación bloqueada

El controlador verifica `$servicio->ingresos()->exists()` antes de eliminar. Si retorna `true`, redirige con flash `error` sin llamar a `delete()`. No se lanza excepción. La verificación usa la relación `BelongsToMany` que consulta la tabla pivote `detalle_servicios`.

### Modelo no encontrado (404)

Laravel maneja automáticamente el caso en que el `{servicio}` del Route Model Binding no existe, retornando HTTP 404.

### Acceso no autorizado

El middleware `role:Administrador` de Spatie Permission retorna HTTP 403 para usuarios autenticados sin el rol. El middleware `auth` redirige a `/login` para usuarios no autenticados.

---

## Estrategia de Testing

### Enfoque dual

Se combinan tests de ejemplo (feature tests HTTP) con tests basados en propiedades para cubrir tanto casos concretos como el espacio amplio de entradas válidas e inválidas.

### Tests de ejemplo (Feature Tests)

Ubicación: `tests/Feature/ServicioCrudTest.php`

Casos concretos a cubrir:
- `GET /servicios` retorna 200 con la vista correcta (usuario Administrador)
- `GET /servicios/create` retorna 200
- `GET /servicios/{id}/edit` retorna 200 con datos precargados
- Estado vacío muestra el mensaje "No hay servicios registrados."
- El botón Eliminar incluye `onclick="return confirm(...)"` en la vista
- Tras fallo de validación, los campos se repueblan con `old()`
- El enlace "Servicios" aparece en el sidebar para Administrador
- El enlace "Servicios" no aparece para usuarios sin el rol
- La sección "Gestión Administrativa" se expande en rutas `servicios.*`

### Tests de propiedades (Property-Based Tests)

**Librería**: Dado que el ecosistema PHP carece de una librería PBT estándar equivalente a Hypothesis o fast-check, los tests de propiedades se implementarán como **data providers parametrizados con Pest** usando `it()->with(...)` con conjuntos amplios de inputs generados, ejecutando mínimo 50 combinaciones por propiedad. Esto captura el espíritu de PBT (cobertura amplia del espacio de inputs) dentro del ecosistema Laravel/Pest estándar.

Ubicación: `tests/Feature/ServicioPropertiesTest.php`

**Propiedad 1 — Creación persiste datos válidos**
```
Tag: Feature: servicios-crud, Property 1: creación persiste datos válidos
Generadores: nombres de 1-100 chars (alfanumérico, con espacios, con acentos, con caracteres especiales),
             precios de 0.01 a 999999.99 (enteros, decimales, grandes, pequeños)
Verificación: count(Servicio::all()) aumenta en 1, el registro tiene los valores enviados,
              response es redirect a servicios.index con flash success
Mínimo: 50 combinaciones
```

**Propiedad 2 — Validación rechaza entradas inválidas**
```
Tag: Feature: servicios-crud, Property 2: validación rechaza entradas inválidas
Generadores: nombres inválidos (vacíos, solo whitespace, strings de 101-500 chars),
             precios inválidos (0, negativos, strings no numéricos, null)
Verificación: count(Servicio::all()) no cambia, response tiene errores de validación
Mínimo: 50 combinaciones (25 nombre inválido + 25 precio inválido)
```

**Propiedad 3 — Edición actualiza y preserva identidad**
```
Tag: Feature: servicios-crud, Property 3: edición actualiza y preserva identidad
Generadores: servicios existentes aleatorios, nuevos datos válidos aleatorios
Verificación: mismo id, nuevos valores, count(Servicio::all()) no cambia,
              redirect a servicios.index con flash success
Mínimo: 50 combinaciones
```

**Propiedad 4 — Eliminación libre sin ingresos**
```
Tag: Feature: servicios-crud, Property 4: eliminación libre sin ingresos
Generadores: servicios sin ingresos asociados (nombres y precios variados)
Verificación: Servicio::find($id) retorna null, response redirige con flash success
Mínimo: 50 combinaciones
```

**Propiedad 5 — Eliminación bloqueada por integridad referencial**
```
Tag: Feature: servicios-crud, Property 5: eliminación bloqueada por integridad referencial
Generadores: servicios con 1 a N ingresos asociados en detalle_servicios
Verificación: Servicio::find($id) sigue existiendo, response redirige con flash error
Mínimo: 50 combinaciones (variando número de ingresos asociados: 1, 2, 5, 10...)
```

**Propiedad 6 — Control de acceso por rol**
```
Tag: Feature: servicios-crud, Property 6: control de acceso por rol
Generadores: todas las rutas del recurso (6 rutas × 2 tipos de usuario = 12 combinaciones base),
             usuarios no autenticados y usuarios autenticados sin rol Administrador
Verificación: no autenticado → redirect /login; autenticado sin rol → 403
Mínimo: 50 combinaciones (rutas × métodos HTTP × tipos de usuario)
```

**Propiedad 7 — Formato monetario consistente**
```
Tag: Feature: servicios-crud, Property 7: formato monetario consistente
Generadores: precios decimales positivos variados (enteros, decimales, grandes, pequeños,
             con muchos decimales que se redondean)
Verificación: 'S/ ' . number_format($precio, 2) cumple regex /^S\/ \d{1,3}(,\d{3})*\.\d{2}$/
Mínimo: 50 valores de precio distintos
```

### Cobertura mínima esperada

| Área | Tipo de test | Archivo |
|---|---|---|
| Rutas y respuestas HTTP | Feature (ejemplo) | `ServicioCrudTest.php` |
| Estructura de vistas | Feature (ejemplo) | `ServicioCrudTest.php` |
| Navegación y layout | Feature (ejemplo) | `ServicioCrudTest.php` |
| Persistencia en creación | Feature (propiedad) | `ServicioPropertiesTest.php` |
| Validación de inputs | Feature (propiedad) | `ServicioPropertiesTest.php` |
| Persistencia en edición | Feature (propiedad) | `ServicioPropertiesTest.php` |
| Eliminación libre | Feature (propiedad) | `ServicioPropertiesTest.php` |
| Integridad referencial | Feature (propiedad) | `ServicioPropertiesTest.php` |
| Control de acceso | Feature (propiedad) | `ServicioPropertiesTest.php` |
| Formato de precio | Feature (propiedad) | `ServicioPropertiesTest.php` |
