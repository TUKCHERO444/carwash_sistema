# Documento de Diseño Técnico — `clientes-crud`

## Visión General

Este documento describe el diseño técnico para implementar el CRUD de Clientes en el sistema de gestión de taller mecánico. El módulo permite al Administrador registrar, editar y eliminar clientes identificados por DNI, nombre y placa de su vehículo.

El diseño sigue estrictamente los patrones establecidos en el proyecto: controlador resource de Laravel, vistas Blade con Tailwind CSS, protección por rol mediante Spatie Permission, y navegación lateral colapsable. A diferencia del módulo de Vehículos, **no se requiere ninguna migración**: la tabla `clientes` y el modelo `Cliente` ya existen y están correctamente configurados. La eliminación está bloqueada por tres relaciones independientes: `ingresos`, `ventas` y `cambioAceites`.

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
ClienteController
    ├── index()   → clientes.index  (paginación 15/página)
    ├── create()  → clientes.create
    ├── store()   → validar → Cliente::create() → redirect
    ├── edit()    → clientes.edit   (model binding)
    ├── update()  → validar → $cliente->update() → redirect
    └── destroy() → check ingresos
                  → check ventas
                  → check cambioAceites
                  → $cliente->delete() → redirect
    │
    ▼
Cliente (Model)  ←→  clientes (tabla SQLite)
    ├── ingresos():     HasMany → Ingreso
    ├── ventas():       HasMany → Venta
    └── cambioAceites(): HasMany → CambioAceite
```

El layout `resources/views/layouts/app.blade.php` se modifica únicamente para:
1. Agregar el enlace "Clientes" dentro de la sección "Gestión Administrativa" ya existente (sidebar desktop y bottom nav móvil).
2. Actualizar `$gestionAdministrativaActive` para incluir `clientes.*`.

---

## Componentes e Interfaces

### Archivos a crear

| Archivo | Propósito |
|---|---|
| `app/Http/Controllers/ClienteController.php` | Controlador resource con los 5 métodos CRUD |
| `resources/views/clientes/index.blade.php` | Vista de listado con tabla paginada |
| `resources/views/clientes/create.blade.php` | Vista de formulario de creación |
| `resources/views/clientes/edit.blade.php` | Vista de formulario de edición con datos precargados |

### Archivos a modificar

| Archivo | Cambio |
|---|---|
| `routes/web.php` | Agregar `Route::resource('clientes', ClienteController::class)` al grupo `role:Administrador` |
| `resources/views/layouts/app.blade.php` | Agregar enlace "Clientes" en la sección "Gestión Administrativa" existente y actualizar `$gestionAdministrativaActive` |

---

### ClienteController

```php
namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    /**
     * Muestra la lista paginada de clientes.
     */
    public function index(): View

    /**
     * Muestra el formulario de creación.
     */
    public function create(): View

    /**
     * Valida y persiste un nuevo cliente.
     *
     * Reglas de validación:
     *   dni:    required | string | size:8 | regex:/^\d{8}$/ | unique:clientes,dni
     *   nombre: required | string | max:100
     *   placa:  required | string | max:7
     *
     * Éxito:  redirect clientes.index  + flash 'success'
     */
    public function store(Request $request): RedirectResponse

    /**
     * Muestra el formulario de edición con datos precargados.
     * Usa Route Model Binding: Cliente $cliente
     */
    public function edit(Cliente $cliente): View

    /**
     * Valida y actualiza un cliente existente.
     *
     * Reglas de validación:
     *   dni:    required | string | size:8 | regex:/^\d{8}$/ | unique:clientes,dni,{id}
     *   nombre: required | string | max:100
     *   placa:  required | string | max:7
     *
     * Éxito:  redirect clientes.index  + flash 'success'
     */
    public function update(Request $request, Cliente $cliente): RedirectResponse

    /**
     * Elimina un cliente si no tiene registros asociados.
     *
     * Checks independientes (en orden):
     *   1. $cliente->ingresos()->exists()    → redirect + flash 'error'
     *   2. $cliente->ventas()->exists()      → redirect + flash 'error'
     *   3. $cliente->cambioAceites()->exists() → redirect + flash 'error'
     *   Si ninguno aplica → delete() + redirect + flash 'success'
     */
    public function destroy(Cliente $cliente): RedirectResponse
}
```

**Reglas de validación — `store`**:

```php
$request->validate([
    'dni'    => ['required', 'string', 'size:8', 'regex:/^\d{8}$/', 'unique:clientes,dni'],
    'nombre' => ['required', 'string', 'max:100'],
    'placa'  => ['required', 'string', 'max:7'],
]);
```

**Reglas de validación — `update`** (excluye el propio registro del check de unicidad):

```php
$request->validate([
    'dni'    => ['required', 'string', 'size:8', 'regex:/^\d{8}$/', "unique:clientes,dni,{$cliente->id}"],
    'nombre' => ['required', 'string', 'max:100'],
    'placa'  => ['required', 'string', 'max:7'],
]);
```

**Mensajes flash**:

| Acción | Clave | Mensaje |
|---|---|---|
| Creación exitosa | `success` | `'Cliente creado correctamente.'` |
| Edición exitosa | `success` | `'Cliente actualizado correctamente.'` |
| Eliminación exitosa | `success` | `'Cliente eliminado correctamente.'` |
| Bloqueado por ingresos | `error` | `'No se puede eliminar el cliente porque tiene ingresos asociados.'` |
| Bloqueado por ventas | `error` | `'No se puede eliminar el cliente porque tiene ventas asociadas.'` |
| Bloqueado por cambios de aceite | `error` | `'No se puede eliminar el cliente porque tiene cambios de aceite asociados.'` |

---

## Modelos de Datos

### Tabla `clientes` (ya existente — sin cambios)

```sql
CREATE TABLE clientes (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    dni        VARCHAR(8)   NOT NULL UNIQUE,
    nombre     VARCHAR(100) NOT NULL,
    placa      VARCHAR(7)   NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

> **No se requiere migración.** La tabla ya existe con la estructura correcta.

### Modelo `Cliente` (ya existente — sin cambios)

```php
protected $fillable = ['dni', 'nombre', 'placa'];

public function ingresos(): HasMany
{
    return $this->hasMany(Ingreso::class);
}

public function ventas(): HasMany
{
    return $this->hasMany(Venta::class);
}

public function cambioAceites(): HasMany
{
    return $this->hasMany(CambioAceite::class);
}
```

### Rutas

Agregar dentro del grupo `middleware(['auth', 'role:Administrador'])` en `routes/web.php`:

```php
use App\Http\Controllers\ClienteController;

Route::resource('clientes', ClienteController::class)
     ->except(['show'])
     ->parameters(['clientes' => 'cliente']);
```

Esto genera las rutas:

| Método | URI | Nombre | Acción |
|---|---|---|---|
| GET | `/clientes` | `clientes.index` | `index` |
| GET | `/clientes/create` | `clientes.create` | `create` |
| POST | `/clientes` | `clientes.store` | `store` |
| GET | `/clientes/{cliente}/edit` | `clientes.edit` | `edit` |
| PUT/PATCH | `/clientes/{cliente}` | `clientes.update` | `update` |
| DELETE | `/clientes/{cliente}` | `clientes.destroy` | `destroy` |

---

## Estructura de Vistas

### `clientes/index.blade.php`

```
@extends('layouts.app')
@section('content')
  <div class="p-6">
    [Flash messages: session('success') verde / session('error') rojo]

    [Header]
      <h1>Clientes</h1>
      <a href="clientes.create">Crear cliente</a>  ← bg-blue-600

    [Estado vacío si $clientes->isEmpty()]
      "No hay clientes registrados."  ← text-center py-12 text-gray-500 text-sm

    [Tabla si hay registros]
      <thead bg-gray-50>
        DNI | Nombre | Placa | Acciones
      <tbody divide-y divide-gray-200, hover:bg-gray-50>
        @foreach $clientes as $cliente
          | {{ $cliente->dni }}
          | {{ $cliente->nombre }}
          | {{ $cliente->placa }}
          | [Editar] ← bg-gray-100  [Eliminar] ← bg-red-100 + confirm()

    [Paginación]
      {{ $clientes->links() }}
  </div>
@endsection
```

**Botón Eliminar** (patrón idéntico a vehículos/servicios):
```html
<form method="POST" action="{{ route('clientes.destroy', $cliente) }}" class="inline">
    @csrf
    @method('DELETE')
    <button type="submit"
            onclick="return confirm('¿Estás seguro de que deseas eliminar este cliente?')"
            class="...bg-red-100 text-red-700...">
        Eliminar
    </button>
</form>
```

---

### `clientes/create.blade.php`

```
@extends('layouts.app')
@section('content')
  <div class="p-6">
    [Flash messages]

    [Header]
      <h1>Crear cliente</h1>
      <a href="clientes.index">Volver</a>  ← bg-gray-100

    <div class="bg-white rounded-lg border border-gray-200 p-6 max-w-lg">
      <form action="clientes.store" method="POST" novalidate>
        @csrf

        [Campo: DNI]
          label: "DNI *"
          input type="text" name="dni" value="{{ old('dni') }}" maxlength="8"
          error: border-red-400 bg-red-50 + @error('dni')

        [Campo: Nombre]
          label: "Nombre *"
          input type="text" name="nombre" value="{{ old('nombre') }}"
          error: border-red-400 bg-red-50 + @error('nombre')

        [Campo: Placa]
          label: "Placa *"
          input type="text" name="placa" value="{{ old('placa') }}" maxlength="7"
          error: border-red-400 bg-red-50 + @error('placa')

        [Submit]
          <button type="submit" class="bg-blue-600...">Guardar</button>
          <a href="clientes.index">Cancelar</a>
      </form>
    </div>
  </div>
@endsection
```

---

### `clientes/edit.blade.php`

Idéntico a `create.blade.php` con estas diferencias:

- Título: `"Editar cliente"`
- `<form action="{{ route('clientes.update', $cliente) }}" method="POST">`
- Agregar `@method('PUT')` dentro del form
- Valores precargados: `old('dni', $cliente->dni)`, `old('nombre', $cliente->nombre)`, `old('placa', $cliente->placa)`
- Botón submit: `"Guardar cambios"`

---

## Modificación del Layout — Agregar enlace "Clientes"

### Cambio 1: Variable `$gestionAdministrativaActive`

Actualizar la línea existente en el bloque `@php` del layout:

```php
// Antes:
$gestionAdministrativaActive = request()->routeIs('vehiculos.*', 'servicios.*');

// Después:
$gestionAdministrativaActive = request()->routeIs('vehiculos.*', 'servicios.*', 'clientes.*');
```

### Cambio 2: Sidebar desktop — agregar enlace "Clientes"

Dentro del `data-dropdown-menu="gestion-administrativa"` existente, agregar después del enlace "Servicios":

```html
<a href="{{ route('clientes.index') }}"
   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
          {{ request()->routeIs('clientes.*') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
    Clientes
</a>
```

### Cambio 3: Bottom nav móvil — agregar enlace "Clientes"

Dentro del `data-dropdown-menu="gestion-administrativa-mobile"` existente, agregar después del enlace "Servicios":

```html
<a href="{{ route('clientes.index') }}"
   class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
          {{ request()->routeIs('clientes.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
    Clientes
</a>
```

---

## Propiedades de Corrección

*Una propiedad es una característica o comportamiento que debe cumplirse en todas las ejecuciones válidas del sistema — esencialmente, una declaración formal sobre lo que el software debe hacer. Las propiedades sirven como puente entre las especificaciones legibles por humanos y las garantías de corrección verificables por máquina.*

### Propiedad 1: Creación persiste datos válidos

*Para cualquier* combinación válida de DNI (exactamente 8 dígitos numéricos, único en la base de datos), nombre (string no vacío de hasta 100 caracteres) y placa (string no vacío de hasta 7 caracteres), al enviar el formulario de creación el sistema debe persistir exactamente un nuevo registro en la tabla `clientes` con los datos enviados y redirigir a `clientes.index` con flash `success`.

**Valida: Requisitos 2.3**

---

### Propiedad 2: Validación rechaza DNI inválido sin persistir

*Para cualquier* valor de DNI inválido — vacío, con longitud distinta de 8, con caracteres no numéricos, o duplicado de un DNI ya existente — tanto en creación como en edición, el sistema debe rechazar la operación, no persistir ningún cambio en la base de datos, y retornar la vista del formulario con errores de validación para el campo `dni`.

**Valida: Requisitos 2.4, 3.3**

---

### Propiedad 3: Validación rechaza nombre o placa inválidos sin persistir

*Para cualquier* nombre inválido (vacío o con más de 100 caracteres) o placa inválida (vacía o con más de 7 caracteres), tanto en creación como en edición, el sistema debe rechazar la operación, no persistir ningún cambio en la base de datos, y retornar la vista del formulario con los errores de validación correspondientes.

**Valida: Requisitos 2.5, 2.6, 3.4, 3.5**

---

### Propiedad 4: Repoblación de campos tras fallo de validación

*Para cualquier* envío de formulario (creación o edición) que falle la validación, los campos del formulario en la respuesta deben contener exactamente los valores que el usuario envió originalmente (comportamiento `old()`), sin importar qué campo específico causó el error.

**Valida: Requisitos 2.7, 3.6**

---

### Propiedad 5: Edición actualiza datos y preserva identidad del registro

*Para cualquier* cliente existente y cualquier conjunto de datos válidos de actualización, al enviar el formulario de edición el sistema debe actualizar exactamente ese registro (mismo `id`) con los nuevos valores, sin crear registros adicionales ni modificar otros registros, y redirigir a `clientes.index` con flash `success`.

**Valida: Requisitos 3.1, 3.2**

---

### Propiedad 6: Eliminación bloqueada por cualquier registro asociado

*Para cualquier* cliente que tenga al menos un registro asociado — ya sea un ingreso, una venta o un cambio de aceite — la operación de eliminación debe ser cancelada: el registro debe seguir existiendo en la base de datos y el sistema debe redirigir a `clientes.index` con flash `error` con el mensaje correspondiente al tipo de asociación encontrado.

**Valida: Requisitos 4.2, 4.3, 4.4**

---

### Propiedad 7: Control de acceso por rol

*Para cualquier* ruta del recurso `clientes` (index, create, store, edit, update, destroy), una petición de un usuario sin el rol `Administrador` — ya sea no autenticado o autenticado con otro rol — debe ser rechazada: redirigir a login si no está autenticado, o retornar HTTP 403 si está autenticado sin el rol correcto.

**Valida: Requisitos 5.1, 5.2**

---

## Manejo de Errores

### Errores de validación (redirect back con errores)

Laravel redirige automáticamente de vuelta al formulario con los errores en `$errors` y los valores anteriores en `old()`. Las vistas deben:
- Aplicar `border-red-400 bg-red-50` al input con error
- Mostrar el mensaje con `@error('campo') ... @enderror`
- Repoblar con `old('campo', $valorActual)` (en edición) o `old('campo')` (en creación)

### Eliminación bloqueada — tres checks independientes

El controlador verifica las tres relaciones en orden secuencial. En cuanto una retorna `true`, redirige con el flash `error` correspondiente sin continuar con los checks restantes ni llamar a `delete()`. No se lanza excepción.

```php
if ($cliente->ingresos()->exists()) {
    return redirect()->route('clientes.index')
        ->with('error', 'No se puede eliminar el cliente porque tiene ingresos asociados.');
}
if ($cliente->ventas()->exists()) {
    return redirect()->route('clientes.index')
        ->with('error', 'No se puede eliminar el cliente porque tiene ventas asociadas.');
}
if ($cliente->cambioAceites()->exists()) {
    return redirect()->route('clientes.index')
        ->with('error', 'No se puede eliminar el cliente porque tiene cambios de aceite asociados.');
}
$cliente->delete();
```

### Modelo no encontrado (404)

Laravel maneja automáticamente el caso en que el `{cliente}` del Route Model Binding no existe, retornando HTTP 404.

### Acceso no autorizado

El middleware `role:Administrador` de Spatie Permission retorna HTTP 403 para usuarios autenticados sin el rol. El middleware `auth` redirige a `/login` para usuarios no autenticados.

### Unicidad del DNI en edición

La regla `unique:clientes,dni,{$cliente->id}` excluye el propio registro del check de unicidad, permitiendo que un administrador guarde el formulario de edición sin cambiar el DNI sin recibir un falso error de duplicado.

---

## Estrategia de Testing

### Enfoque dual

Se combinan tests de ejemplo (feature tests HTTP) con tests basados en propiedades para cubrir tanto casos concretos como el espacio amplio de entradas válidas e inválidas.

### Tests de ejemplo (Feature Tests — PHPUnit/Pest)

Ubicación: `tests/Feature/ClienteCrudTest.php`

Casos concretos a cubrir:
- `GET /clientes` retorna 200 con la vista correcta (usuario Administrador)
- `GET /clientes/create` retorna 200
- `GET /clientes/{id}/edit` retorna 200 con datos precargados
- Estado vacío muestra el mensaje "No hay clientes registrados."
- El botón Eliminar incluye `onclick="return confirm(...)"` en la vista
- La tabla muestra las columnas DNI, Nombre y Placa
- Con 16 clientes, aparecen controles de paginación
- El sidebar muestra el enlace "Clientes" para usuarios Administrador
- La sección "Gestión Administrativa" permanece expandida al navegar por `clientes.*`

### Tests de propiedades (Property-Based Tests)

**Librería**: Dado que el ecosistema PHP carece de una librería PBT estándar equivalente a Hypothesis o fast-check, los tests de propiedades se implementarán como **data providers parametrizados con Pest** usando `it()->with(...)` con conjuntos amplios de inputs generados, ejecutando mínimo 50 combinaciones por propiedad. Esto captura el espíritu de PBT (cobertura amplia del espacio de inputs) dentro del ecosistema Laravel/Pest estándar.

Ubicación: `tests/Feature/ClientePropertiesTest.php`

**Propiedad 1 — Creación persiste datos válidos**
```
Tag: Feature: clientes-crud, Property 1: creación persiste datos válidos
Generadores: DNIs de exactamente 8 dígitos numéricos únicos,
             nombres de 1-100 chars (alfanumérico, con espacios, con acentos),
             placas de 1-7 chars alfanuméricos
Verificación: count(Cliente::all()) aumenta en 1, el registro tiene los valores enviados,
              response es redirect a clientes.index con flash success
```

**Propiedad 2 — Validación rechaza DNI inválido**
```
Tag: Feature: clientes-crud, Property 2: validación rechaza DNI inválido
Generadores: DNIs vacíos, strings de longitud ≠ 8, strings con letras/símbolos,
             DNIs duplicados de registros existentes
Verificación: count(Cliente::all()) no cambia, response tiene errores de validación para 'dni'
```

**Propiedad 3 — Validación rechaza nombre o placa inválidos**
```
Tag: Feature: clientes-crud, Property 3: validación rechaza nombre o placa inválidos
Generadores: nombres vacíos, strings de 101+ chars; placas vacías, strings de 8+ chars
Verificación: count(Cliente::all()) no cambia, response tiene errores de validación
              para el campo correspondiente
```

**Propiedad 4 — Repoblación de campos tras fallo de validación**
```
Tag: Feature: clientes-crud, Property 4: repoblación de campos tras fallo de validación
Generadores: combinaciones de datos con al menos un campo inválido
Verificación: los valores enviados aparecen en el HTML de la respuesta (old())
```

**Propiedad 5 — Edición actualiza y preserva identidad**
```
Tag: Feature: clientes-crud, Property 5: edición actualiza y preserva identidad
Generadores: clientes existentes aleatorios, nuevos datos válidos aleatorios
Verificación: mismo id, nuevos valores, count(Cliente::all()) no cambia,
              redirect a clientes.index con flash success
```

**Propiedad 6 — Eliminación bloqueada por registros asociados**
```
Tag: Feature: clientes-crud, Property 6: eliminación bloqueada por registros asociados
Generadores: clientes con 1 a N ingresos; clientes con 1 a N ventas;
             clientes con 1 a N cambios de aceite
Verificación: Cliente::find($id) sigue existiendo, response redirige con flash error
              con el mensaje correspondiente al tipo de asociación
```

**Propiedad 7 — Control de acceso por rol**
```
Tag: Feature: clientes-crud, Property 7: control de acceso por rol
Generadores: todas las rutas del recurso (6 rutas), usuarios no autenticados,
             usuarios autenticados sin rol Administrador
Verificación: no autenticado → redirect /login; autenticado sin rol → 403
```

### Cobertura mínima esperada

| Área | Tipo de test | Archivo |
|---|---|---|
| Rutas y respuestas HTTP | Feature (ejemplo) | `ClienteCrudTest.php` |
| Estructura de vistas (columnas, mensajes) | Feature (ejemplo) | `ClienteCrudTest.php` |
| Paginación y estado vacío | Feature (ejemplo) | `ClienteCrudTest.php` |
| Validación de DNI | Feature (propiedad) | `ClientePropertiesTest.php` |
| Validación de nombre y placa | Feature (propiedad) | `ClientePropertiesTest.php` |
| Repoblación de campos | Feature (propiedad) | `ClientePropertiesTest.php` |
| Persistencia CRUD | Feature (propiedad) | `ClientePropertiesTest.php` |
| Integridad referencial (3 relaciones) | Feature (propiedad) | `ClientePropertiesTest.php` |
| Control de acceso | Feature (propiedad) | `ClientePropertiesTest.php` |
| Navegación sidebar | Feature (ejemplo) | `ClienteCrudTest.php` |
