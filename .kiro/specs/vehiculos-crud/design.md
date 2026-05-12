# Documento de Diseño Técnico — `vehiculos-crud`

## Visión General

Este documento describe el diseño técnico para implementar el CRUD de Vehículos en el sistema de gestión de taller mecánico. El módulo permite al Administrador registrar, editar y eliminar tipos de vehículos con sus precios estandarizados de servicio.

El diseño sigue estrictamente los patrones establecidos en el proyecto: controlador resource de Laravel, vistas Blade con Tailwind CSS, protección por rol mediante Spatie Permission, y navegación lateral colapsable. Se requiere además una migración adicional para hacer nullable la columna `descripcion` de la tabla `vehiculos`.

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
VehiculoController
    ├── index()   → vehiculos.index  (paginación 15/página)
    ├── create()  → vehiculos.create
    ├── store()   → validar → Vehiculo::create() → redirect
    ├── edit()    → vehiculos.edit   (model binding)
    ├── update()  → validar → $vehiculo->update() → redirect
    └── destroy() → check ingresos → $vehiculo->delete() → redirect
    │
    ▼
Vehiculo (Model)  ←→  vehiculos (tabla SQLite)
    │
    └── ingresos(): HasMany → Ingreso
```

El layout `resources/views/layouts/app.blade.php` se modifica para agregar la sección colapsable "Gestión Administrativa" tanto en el sidebar desktop como en el bottom nav móvil.

---

## Componentes e Interfaces

### Archivos a crear

| Archivo | Propósito |
|---|---|
| `app/Http/Controllers/VehiculoController.php` | Controlador resource con los 5 métodos CRUD |
| `resources/views/vehiculos/index.blade.php` | Vista de listado con tabla paginada |
| `resources/views/vehiculos/create.blade.php` | Vista de formulario de creación |
| `resources/views/vehiculos/edit.blade.php` | Vista de formulario de edición con datos precargados |
| `database/migrations/YYYY_MM_DD_HHMMSS_make_vehiculos_descripcion_nullable.php` | Migración para hacer nullable la columna `descripcion` |

### Archivos a modificar

| Archivo | Cambio |
|---|---|
| `routes/web.php` | Agregar `Route::resource('vehiculos', ...)` al grupo `role:Administrador` |
| `resources/views/layouts/app.blade.php` | Agregar variable `$gestionAdministrativaActive`, sección sidebar y bottom nav |

---

### VehiculoController

```php
namespace App\Http\Controllers;

use App\Models\Vehiculo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VehiculoController extends Controller
{
    /**
     * Muestra la lista paginada de vehículos.
     */
    public function index(): View

    /**
     * Muestra el formulario de creación.
     */
    public function create(): View

    /**
     * Valida y persiste un nuevo vehículo.
     *
     * Reglas de validación:
     *   nombre:      required | string | max:100
     *   descripcion: nullable | string
     *   precio:      required | numeric | gt:0
     *
     * Éxito:  redirect vehiculos.index  + flash 'success'
     */
    public function store(Request $request): RedirectResponse

    /**
     * Muestra el formulario de edición con datos precargados.
     * Usa Route Model Binding: Vehiculo $vehiculo
     */
    public function edit(Vehiculo $vehiculo): View

    /**
     * Valida y actualiza un vehículo existente.
     *
     * Mismas reglas de validación que store().
     * Éxito:  redirect vehiculos.index  + flash 'success'
     */
    public function update(Request $request, Vehiculo $vehiculo): RedirectResponse

    /**
     * Elimina un vehículo si no tiene ingresos asociados.
     *
     * Si $vehiculo->ingresos()->exists() → redirect + flash 'error'
     * Si no tiene ingresos             → delete() + redirect + flash 'success'
     */
    public function destroy(Vehiculo $vehiculo): RedirectResponse
}
```

**Reglas de validación compartidas** (usadas en `store` y `update`):

```php
$request->validate([
    'nombre'      => ['required', 'string', 'max:100'],
    'descripcion' => ['nullable', 'string'],
    'precio'      => ['required', 'numeric', 'gt:0'],
]);
```

**Mensajes flash**:

| Acción | Clave | Mensaje |
|---|---|---|
| Creación exitosa | `success` | `'Vehículo creado correctamente.'` |
| Edición exitosa | `success` | `'Vehículo actualizado correctamente.'` |
| Eliminación exitosa | `success` | `'Vehículo eliminado correctamente.'` |
| Eliminación bloqueada | `error` | `'No se puede eliminar el vehículo porque tiene ingresos asociados.'` |

---

## Modelos de Datos

### Tabla `vehiculos` (estado actual)

```sql
CREATE TABLE vehiculos (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre      VARCHAR(100) NOT NULL,
    descripcion TEXT         NOT NULL,   -- ← debe cambiar a nullable
    precio      DECIMAL(10,2) NOT NULL,
    created_at  TIMESTAMP,
    updated_at  TIMESTAMP
);
```

### Migración requerida

La columna `descripcion` es `NOT NULL` en la migración original, pero los requisitos la definen como opcional. Se necesita una nueva migración:

```php
// database/migrations/YYYY_MM_DD_HHMMSS_make_vehiculos_descripcion_nullable.php

public function up(): void
{
    Schema::table('vehiculos', function (Blueprint $table) {
        $table->text('descripcion')->nullable()->change();
    });
}

public function down(): void
{
    Schema::table('vehiculos', function (Blueprint $table) {
        $table->text('descripcion')->nullable(false)->change();
    });
}
```

> **Nota**: Esta migración requiere el paquete `doctrine/dbal` en proyectos Laravel anteriores a 11.x. En Laravel 11 con SQLite, `->change()` funciona de forma nativa.

### Modelo `Vehiculo` (sin cambios)

El modelo existente ya está correctamente configurado:

```php
protected $fillable = ['nombre', 'descripcion', 'precio'];
protected $casts    = ['precio' => 'decimal:2'];

public function ingresos(): HasMany
{
    return $this->hasMany(Ingreso::class);
}
```

### Rutas

Agregar dentro del grupo `middleware(['auth', 'role:Administrador'])` en `routes/web.php`:

```php
use App\Http\Controllers\VehiculoController;

Route::resource('vehiculos', VehiculoController::class)
     ->except(['show'])
     ->parameters(['vehiculos' => 'vehiculo']);
```

Esto genera las rutas:

| Método | URI | Nombre | Acción |
|---|---|---|---|
| GET | `/vehiculos` | `vehiculos.index` | `index` |
| GET | `/vehiculos/create` | `vehiculos.create` | `create` |
| POST | `/vehiculos` | `vehiculos.store` | `store` |
| GET | `/vehiculos/{vehiculo}/edit` | `vehiculos.edit` | `edit` |
| PUT/PATCH | `/vehiculos/{vehiculo}` | `vehiculos.update` | `update` |
| DELETE | `/vehiculos/{vehiculo}` | `vehiculos.destroy` | `destroy` |

---

## Estructura de Vistas

### `vehiculos/index.blade.php`

```
@extends('layouts.app')
@section('content')
  <div class="p-6">
    [Flash messages: session('success') verde / session('error') rojo]

    [Header]
      <h1>Vehículos</h1>
      <a href="vehiculos.create">Crear vehículo</a>  ← bg-blue-600

    [Estado vacío si $vehiculos->isEmpty()]
      "No hay vehículos registrados."  ← text-center py-12 text-gray-500 text-sm

    [Tabla si hay registros]
      <thead bg-gray-50>
        Nombre | Descripción | Precio | Acciones
      <tbody divide-y divide-gray-200, hover:bg-gray-50>
        @foreach $vehiculos as $vehiculo
          | {{ $vehiculo->nombre }}
          | {{ $vehiculo->descripcion ?? '—' }}
          | S/ {{ number_format($vehiculo->precio, 2) }}
          | [Editar] ← bg-gray-100  [Eliminar] ← bg-red-100 + confirm()

    [Paginación]
      {{ $vehiculos->links() }}
  </div>
@endsection
```

**Formato de precio**: `'S/ ' . number_format($vehiculo->precio, 2)` produce `S/ 15,000.00`.

**Botón Eliminar** (patrón idéntico a productos):
```html
<form method="POST" action="{{ route('vehiculos.destroy', $vehiculo) }}" class="inline">
    @csrf
    @method('DELETE')
    <button type="submit"
            onclick="return confirm('¿Estás seguro de que deseas eliminar este vehículo?')"
            class="...bg-red-100 text-red-700...">
        Eliminar
    </button>
</form>
```

---

### `vehiculos/create.blade.php`

```
@extends('layouts.app')
@section('content')
  <div class="p-6">
    [Flash messages]

    [Header]
      <h1>Crear vehículo</h1>
      <a href="vehiculos.index">Volver</a>  ← bg-gray-100

    <div class="bg-white rounded-lg border border-gray-200 p-6 max-w-lg">
      <form action="vehiculos.store" method="POST" novalidate>
        @csrf

        [Campo: Nombre]
          label: "Nombre *"
          input type="text" name="nombre" value="{{ old('nombre') }}"
          error: border-red-400 bg-red-50 + @error('nombre')

        [Campo: Descripción]
          label: "Descripción"  (sin asterisco — opcional)
          textarea name="descripcion" rows="3"
          value: {{ old('descripcion') }}
          sin validación de error requerida

        [Campo: Precio]
          label: "Precio *"
          input type="number" name="precio" step="0.01" min="0.01"
          value="{{ old('precio') }}"
          error: border-red-400 bg-red-50 + @error('precio')

        [Submit]
          <button type="submit" class="bg-blue-600...">Guardar</button>
          <a href="vehiculos.index">Cancelar</a>
      </form>
    </div>
  </div>
@endsection
```

---

### `vehiculos/edit.blade.php`

Idéntico a `create.blade.php` con estas diferencias:

- Título: `"Editar vehículo"`
- `<form action="{{ route('vehiculos.update', $vehiculo) }}" method="POST">`
- Agregar `@method('PUT')` dentro del form
- Valores precargados: `old('nombre', $vehiculo->nombre)`, `old('descripcion', $vehiculo->descripcion)`, `old('precio', $vehiculo->precio)`
- Botón submit: `"Guardar cambios"`

---

## Modificación del Layout — Sección "Gestión Administrativa"

### Variable de estado activo

Agregar al bloque `@php` al inicio del layout:

```php
$gestionAdministrativaActive = request()->routeIs('vehiculos.*');
```

### Sidebar desktop

Agregar un nuevo bloque `data-dropdown="gestion-administrativa"` dentro del `@if(auth()->user()?->hasRole('Administrador'))`, después del bloque `product-management`:

```html
<div data-dropdown="gestion-administrativa">
    <button data-dropdown-toggle="gestion-administrativa"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                   {{ $gestionAdministrativaActive ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h8l2-2z"/>
        </svg>
        <span class="flex-1 text-left">Gestión Administrativa</span>
        <svg data-chevron
             class="w-4 h-4 transition-transform {{ $gestionAdministrativaActive ? 'rotate-180' : '' }}"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div data-dropdown-menu="gestion-administrativa"
         class="{{ $gestionAdministrativaActive ? '' : 'hidden' }} ml-4 mt-1 space-y-1">
        <a href="{{ route('vehiculos.index') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                  {{ request()->routeIs('vehiculos.*') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
            Vehículos
        </a>
    </div>
</div>
```

### Bottom nav móvil

Agregar un bloque equivalente `data-dropdown="gestion-administrativa-mobile"` dentro del `@if(auth()->user()?->hasRole('Administrador'))` en el bottom nav, siguiendo el mismo patrón que `product-management-mobile`:

```html
<div data-dropdown="gestion-administrativa-mobile" class="relative">
    <button data-dropdown-toggle="gestion-administrativa-mobile"
            class="flex flex-col items-center gap-1 px-4 py-2 text-xs font-medium transition-colors
                   {{ $gestionAdministrativaActive ? 'text-blue-600' : 'text-gray-500 hover:text-gray-900' }}">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h8l2-2z"/>
        </svg>
        Gestión Adm.
        <svg data-chevron
             class="w-3 h-3 transition-transform {{ $gestionAdministrativaActive ? 'rotate-180' : '' }}"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div data-dropdown-menu="gestion-administrativa-mobile"
         {{ $gestionAdministrativaActive ? 'data-persistent' : '' }}
         class="absolute bottom-16 left-1/2 -translate-x-1/2 bg-white border border-gray-200 rounded-lg shadow-lg min-w-max
                {{ $gestionAdministrativaActive ? '' : 'hidden' }}">
        <a href="{{ route('vehiculos.index') }}"
           class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
                  {{ request()->routeIs('vehiculos.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
            Vehículos
        </a>
    </div>
</div>
```

---

## Propiedades de Corrección

*Una propiedad es una característica o comportamiento que debe cumplirse en todas las ejecuciones válidas del sistema — esencialmente, una declaración formal sobre lo que el software debe hacer. Las propiedades sirven como puente entre las especificaciones legibles por humanos y las garantías de corrección verificables por máquina.*

### Propiedad 1: Creación persiste datos válidos

*Para cualquier* nombre válido (string no vacío, máximo 100 caracteres), descripción opcional y precio válido (numérico mayor a 0), al enviar el formulario de creación el sistema debe persistir exactamente un nuevo registro en la tabla `vehiculos` con los datos enviados y redirigir a `vehiculos.index` con flash `success`.

**Valida: Requisitos 2.3**

---

### Propiedad 2: Validación rechaza entradas inválidas sin persistir

*Para cualquier* combinación de datos inválidos — nombre vacío, nombre con más de 100 caracteres, precio igual a 0, precio negativo, o precio no numérico — tanto en creación como en edición, el sistema debe rechazar la operación, no persistir ningún cambio en la base de datos, y retornar la vista del formulario con errores de validación.

**Valida: Requisitos 2.4, 2.5, 3.3, 3.4**

---

### Propiedad 3: Edición actualiza datos y preserva identidad del registro

*Para cualquier* vehículo existente y cualquier conjunto de datos válidos de actualización, al enviar el formulario de edición el sistema debe actualizar exactamente ese registro (mismo `id`) con los nuevos valores, sin crear registros adicionales ni modificar otros registros, y redirigir a `vehiculos.index` con flash `success`.

**Valida: Requisitos 3.2**

---

### Propiedad 4: Eliminación bloqueada por integridad referencial

*Para cualquier* vehículo que tenga al menos un ingreso asociado, la operación de eliminación debe ser cancelada: el registro debe seguir existiendo en la base de datos y el sistema debe redirigir a `vehiculos.index` con flash `error`.

**Valida: Requisitos 4.2**

---

### Propiedad 5: Control de acceso por rol

*Para cualquier* ruta del recurso `vehiculos` (index, create, store, edit, update, destroy), una petición de un usuario sin el rol `Administrador` — ya sea no autenticado o autenticado con otro rol — debe ser rechazada: redirigir a login si no está autenticado, o retornar HTTP 403 si está autenticado sin el rol correcto.

**Valida: Requisitos 5.1, 5.2**

---

### Propiedad 6: Formato monetario consistente

*Para cualquier* precio decimal positivo almacenado en un vehículo, la representación en la vista de listado debe seguir el formato `S/ X,XXX.XX` — es decir, prefijo `S/ `, separador de miles con coma, y exactamente dos decimales separados por punto.

**Valida: Requisitos 1.3**

---

## Manejo de Errores

### Errores de validación (HTTP 422 / redirect back)

Laravel redirige automáticamente de vuelta al formulario con los errores en `$errors` y los valores anteriores en `old()`. Las vistas deben:
- Aplicar `border-red-400 bg-red-50` al input con error
- Mostrar el mensaje con `@error('campo') ... @enderror`
- Repoblar con `old('campo', $valorActual)` (en edición)

### Eliminación bloqueada

El controlador verifica `$vehiculo->ingresos()->exists()` antes de eliminar. Si retorna `true`, redirige con flash `error` sin llamar a `delete()`. No se lanza excepción.

### Modelo no encontrado (404)

Laravel maneja automáticamente el caso en que el `{vehiculo}` del Route Model Binding no existe, retornando HTTP 404.

### Acceso no autorizado

El middleware `role:Administrador` de Spatie Permission retorna HTTP 403 para usuarios autenticados sin el rol. El middleware `auth` redirige a `/login` para usuarios no autenticados.

---

## Estrategia de Testing

### Enfoque dual

Se combinan tests de ejemplo (feature tests HTTP) con tests basados en propiedades para cubrir tanto casos concretos como el espacio amplio de entradas válidas e inválidas.

### Tests de ejemplo (Feature Tests — PHPUnit)

Ubicación: `tests/Feature/VehiculoCrudTest.php`

Casos concretos a cubrir:
- `GET /vehiculos` retorna 200 con la vista correcta (usuario Administrador)
- `GET /vehiculos/create` retorna 200
- `GET /vehiculos/{id}/edit` retorna 200 con datos precargados
- Estado vacío muestra el mensaje "No hay vehículos registrados."
- El botón Eliminar incluye `onclick="return confirm(...)"` en la vista
- Tras fallo de validación, los campos se repueblan con `old()`
- La sección "Gestión Administrativa" aparece en el sidebar para Administrador
- La sección "Gestión Administrativa" no aparece para usuarios sin el rol

### Tests de propiedades (Property-Based Tests)

**Librería**: [`spatie/pest-plugin-test-time`](https://github.com/spatie/pest-plugin-test-time) no aplica aquí. Se usará **[`eris/eris`](https://github.com/giorgiosironi/eris)** (PHP property-based testing) o, preferiblemente, **generadores manuales con Pest** dado que el ecosistema PHP no tiene una librería PBT tan madura como fast-check o Hypothesis.

> **Decisión de diseño**: Dado que el ecosistema PHP carece de una librería PBT estándar equivalente a Hypothesis o fast-check, los tests de propiedades se implementarán como **data providers parametrizados con Pest** usando `it()->with(...)` con conjuntos amplios de inputs generados, ejecutando mínimo 50 combinaciones por propiedad. Esto captura el espíritu de PBT (cobertura amplia del espacio de inputs) dentro del ecosistema Laravel/Pest estándar.

Ubicación: `tests/Feature/VehiculoPropertiesTest.php`

**Propiedad 1 — Creación persiste datos válidos**
```
Tag: Feature: vehiculos-crud, Property 1: creación persiste datos válidos
Generadores: nombres de 1-100 chars (alfanumérico, con espacios, con acentos),
             precios de 0.01 a 999999.99
Verificación: count(Vehiculo::all()) aumenta en 1, el registro tiene los valores enviados,
              response es redirect a vehiculos.index con flash success
```

**Propiedad 2 — Validación rechaza entradas inválidas**
```
Tag: Feature: vehiculos-crud, Property 2: validación rechaza entradas inválidas
Generadores: nombres vacíos, strings de 101+ chars, precios 0, negativos, strings no numéricos
Verificación: count(Vehiculo::all()) no cambia, response tiene errores de validación
```

**Propiedad 3 — Edición actualiza y preserva identidad**
```
Tag: Feature: vehiculos-crud, Property 3: edición actualiza y preserva identidad
Generadores: vehículos existentes aleatorios, nuevos datos válidos aleatorios
Verificación: mismo id, nuevos valores, count(Vehiculo::all()) no cambia,
              redirect a vehiculos.index con flash success
```

**Propiedad 4 — Eliminación bloqueada por integridad referencial**
```
Tag: Feature: vehiculos-crud, Property 4: eliminación bloqueada por integridad referencial
Generadores: vehículos con 1 a N ingresos asociados
Verificación: Vehiculo::find($id) sigue existiendo, response redirige con flash error
```

**Propiedad 5 — Control de acceso por rol**
```
Tag: Feature: vehiculos-crud, Property 5: control de acceso por rol
Generadores: todas las rutas del recurso (6 rutas), usuarios sin rol / con rol incorrecto
Verificación: no autenticado → redirect /login; autenticado sin rol → 403
```

**Propiedad 6 — Formato monetario consistente**
```
Tag: Feature: vehiculos-crud, Property 6: formato monetario consistente
Generadores: precios decimales positivos variados (enteros, decimales, grandes, pequeños)
Verificación: 'S/ ' . number_format($precio, 2) cumple regex /^S\/ \d{1,3}(,\d{3})*\.\d{2}$/
```

### Cobertura mínima esperada

| Área | Tipo de test | Archivo |
|---|---|---|
| Rutas y respuestas HTTP | Feature (ejemplo) | `VehiculoCrudTest.php` |
| Validación de inputs | Feature (propiedad) | `VehiculoPropertiesTest.php` |
| Persistencia CRUD | Feature (propiedad) | `VehiculoPropertiesTest.php` |
| Integridad referencial | Feature (propiedad) | `VehiculoPropertiesTest.php` |
| Control de acceso | Feature (propiedad) | `VehiculoPropertiesTest.php` |
| Formato de precio | Unit (propiedad) | `VehiculoPropertiesTest.php` |
| Estructura de vistas | Feature (ejemplo) | `VehiculoCrudTest.php` |
