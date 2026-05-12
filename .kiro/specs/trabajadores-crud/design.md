# Design Document — trabajadores-crud

## Overview

Este módulo añade un CRUD completo para la entidad **Trabajador** siguiendo exactamente los patrones del módulo `users` existente en la aplicación Laravel + Blade + Tailwind CSS.

El diseño es deliberadamente conservador: no introduce nuevas abstracciones, librerías ni patrones. Reutiliza la misma estructura de controlador, las mismas convenciones de validación y el mismo sistema de vistas que ya existe en `UserController` y `resources/views/users/`. La única diferencia sustancial respecto al módulo `users` es la lógica de eliminación protegida, que verifica relaciones antes de proceder.

**Decisiones de diseño clave:**

- **Sin Form Requests separados**: El módulo `users` valida inline en el controlador; `TrabajadorController` sigue el mismo patrón para mantener consistencia.
- **Sin soft deletes**: La tabla `trabajadores` no tiene columna `deleted_at` y el modelo no usa `SoftDeletes`. La eliminación es permanente, protegida por verificación de relaciones.
- **Paginación de 15**: Igual que `UserController::index()`.
- **Route Model Binding implícito**: Laravel resuelve `{trabajador}` automáticamente porque el parámetro coincide con el nombre del modelo en snake_case.

---

## Architecture

El módulo sigue la arquitectura MVC estándar de Laravel sin capas adicionales:

```
HTTP Request
    │
    ▼
routes/web.php
  Route::resource('trabajadores', TrabajadorController::class)
  [middleware: auth, role:Administrador]
    │
    ▼
TrabajadorController
  ├── index()   → trabajadores.index  (paginación)
  ├── create()  → trabajadores.create (formulario vacío)
  ├── store()   → validación + Trabajador::create() + redirect
  ├── edit()    → trabajadores.edit   (formulario precargado)
  ├── update()  → validación + $trabajador->update() + redirect
  └── destroy() → verificación relaciones + $trabajador->delete() + redirect
    │
    ▼
Trabajador (Eloquent Model)
  tabla: trabajadores
  fillable: [nombre, estado]
  casts: [estado => boolean]
  relaciones: cambioAceites (hasMany), ingresos (belongsToMany)
    │
    ▼
Blade Views (extienden layouts.app)
  resources/views/trabajadores/
    ├── index.blade.php
    ├── create.blade.php
    └── edit.blade.php
```

No se añaden servicios, repositorios ni eventos. La lógica de negocio (verificación de relaciones antes de eliminar) vive directamente en el controlador, igual que la lógica de roles en `UserController`.

---

## Components and Interfaces

### TrabajadorController

**Namespace:** `App\Http\Controllers`  
**Ruta del archivo:** `app/Http/Controllers/TrabajadorController.php`

| Método | HTTP | URI | Descripción |
|--------|------|-----|-------------|
| `index()` | GET | `/trabajadores` | Lista paginada (15/página) |
| `create()` | GET | `/trabajadores/create` | Formulario de creación |
| `store(Request $request)` | POST | `/trabajadores` | Persiste nuevo trabajador |
| `edit(Trabajador $trabajador)` | GET | `/trabajadores/{trabajador}/edit` | Formulario de edición precargado |
| `update(Request $request, Trabajador $trabajador)` | PUT | `/trabajadores/{trabajador}` | Actualiza trabajador existente |
| `destroy(Trabajador $trabajador)` | DELETE | `/trabajadores/{trabajador}` | Elimina si no tiene relaciones |

**Reglas de validación — store:**

```php
'nombre' => ['required', 'string', 'max:100', 'unique:trabajadores'],
'estado' => ['required', 'boolean'],
```

**Reglas de validación — update:**

```php
'nombre' => ['required', 'string', 'max:100', 'unique:trabajadores,nombre,' . $trabajador->id],
'estado' => ['required', 'boolean'],
```

**Lógica de destroy:**

```
if ($trabajador->cambioAceites()->exists()) → redirect con error específico
if ($trabajador->ingresos()->exists())      → redirect con error específico
$trabajador->delete()                       → redirect con éxito
```

### Vistas Blade

| Vista | Extiende | Propósito |
|-------|----------|-----------|
| `trabajadores.index` | `layouts.app` | Tabla paginada con badges de estado y acciones |
| `trabajadores.create` | `layouts.app` | Formulario de creación (nombre + select estado) |
| `trabajadores.edit` | `layouts.app` | Formulario de edición con datos precargados |

### Registro de rutas

En `routes/web.php`, dentro del grupo `middleware(['auth', 'role:Administrador'])` existente:

```php
Route::resource('trabajadores', TrabajadorController::class)->except(['show']);
```

Esto genera las rutas: `trabajadores.index`, `trabajadores.create`, `trabajadores.store`, `trabajadores.edit`, `trabajadores.update`, `trabajadores.destroy`.

---

## Data Models

### Modelo Trabajador (existente)

El modelo `app/Models/Trabajador.php` ya existe y no requiere modificaciones:

```php
class Trabajador extends Model
{
    protected $table    = 'trabajadores';
    protected $fillable = ['nombre', 'estado'];
    protected $casts    = ['estado' => 'boolean'];

    public function cambioAceites(): HasMany
    {
        return $this->hasMany(CambioAceite::class);
    }

    public function ingresos(): BelongsToMany
    {
        return $this->belongsToMany(Ingreso::class, 'ingreso_trabajadores');
    }
}
```

### Esquema de la tabla `trabajadores` (existente)

| Columna | Tipo | Restricciones |
|---------|------|---------------|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| `nombre` | VARCHAR(100) | NOT NULL |
| `estado` | BOOLEAN | NOT NULL, DEFAULT true |
| `created_at` | TIMESTAMP | nullable |
| `updated_at` | TIMESTAMP | nullable |

Índice adicional: `INDEX(estado)`.

La tabla ya existe con su migración en `database/migrations/2024_01_01_000003_create_trabajadores_table.php`. No se requieren nuevas migraciones.

### Relaciones relevantes para la eliminación protegida

| Relación | Tipo | Tabla pivot/FK | Bloquea eliminación |
|----------|------|----------------|---------------------|
| `cambioAceites` | `hasMany` | `cambio_aceites.trabajador_id` | Sí |
| `ingresos` | `belongsToMany` | `ingreso_trabajadores` | Sí |

---

## Correctness Properties

*Una propiedad es una característica o comportamiento que debe mantenerse verdadero en todas las ejecuciones válidas del sistema — esencialmente, una declaración formal sobre lo que el sistema debe hacer. Las propiedades sirven como puente entre las especificaciones legibles por humanos y las garantías de corrección verificables por máquina.*

El feature involucra lógica de negocio pura (validación, persistencia, protección de eliminación) que varía significativamente con los inputs. PBT es aplicable para las propiedades de validación, persistencia y control de acceso.

**Reflexión sobre redundancia:**

- Los criterios 2.3, 2.4, 2.5 (validaciones en store) y 3.3, 3.4, 3.5 (validaciones en update) son análogos. Se consolidan en propiedades que cubren ambos endpoints donde aplica.
- Los criterios 5.2 y 5.3 (control de acceso) se consolidan en una propiedad por tipo de protección.
- Los criterios 4.2 y 4.3 (eliminación bloqueada por relaciones) se consolidan en una sola propiedad paramétrica.
- Los criterios de UI visual (6.x) son tests de ejemplo, no propiedades universales.

---

### Property 1: Badge de estado es consistente con el valor booleano

*Para cualquier* trabajador con un valor de `estado` (true o false), la vista `trabajadores.index` debe renderizar un badge con el texto "Activo" y clases verdes cuando `estado` es `true`, y un badge con el texto "Inactivo" y clases rojas cuando `estado` es `false`.

**Validates: Requirements 1.3**

---

### Property 2: Creación persiste cualquier dato válido

*Para cualquier* combinación de nombre válido (string no vacío, máximo 100 caracteres, único en la tabla) y estado booleano, `POST /trabajadores` debe crear exactamente un registro en la base de datos con esos valores y redirigir a `trabajadores.index` con el flash de éxito.

**Validates: Requirements 2.2**

---

### Property 3: Validación rechaza nombres inválidos

*Para cualquier* string que sea vacío, supere los 100 caracteres, o sea igual a un nombre ya existente en la tabla `trabajadores`, el endpoint de creación (`POST /trabajadores`) y el de actualización (`PUT /trabajadores/{trabajador}`) deben rechazar la petición, redirigir de vuelta al formulario y preservar los valores introducidos en sesión.

**Validates: Requirements 2.3, 2.4, 2.6, 3.3, 3.6**

---

### Property 4: La unicidad de nombre en edición ignora el propio registro

*Para cualquier* trabajador existente con nombre X, una petición `PUT /trabajadores/{trabajador}` con el mismo nombre X (sin cambios) debe ser válida y actualizar el registro correctamente, sin fallar por restricción de unicidad.

**Validates: Requirements 3.4**

---

### Property 5: El formulario de edición precarga los valores actuales

*Para cualquier* trabajador existente con nombre X y estado Y, la vista `trabajadores.edit` debe renderizar el campo `nombre` con el valor X y el `<select>` de estado con la opción correspondiente a Y preseleccionada.

**Validates: Requirements 3.1, 3.7**

---

### Property 6: Eliminación procede solo cuando no hay relaciones

*Para cualquier* trabajador sin `cambioAceites` ni `ingresos` asociados, `DELETE /trabajadores/{trabajador}` debe eliminar el registro de la base de datos y redirigir con flash de éxito. El trabajador no debe existir en la tabla tras la operación.

**Validates: Requirements 4.1**

---

### Property 7: Eliminación es bloqueada por cualquier relación existente

*Para cualquier* trabajador que tenga al menos un `CambioAceite` o al menos un `Ingreso` asociado, `DELETE /trabajadores/{trabajador}` debe cancelar la eliminación (el registro debe seguir existiendo en la tabla) y redirigir con el flash de error correspondiente.

**Validates: Requirements 4.2, 4.3**

---

### Property 8: Todas las rutas del módulo requieren autenticación

*Para cualquier* ruta del módulo `trabajadores` (index, create, store, edit, update, destroy), una petición HTTP sin sesión autenticada debe resultar en una redirección a `/login`, independientemente del método HTTP o los parámetros de ruta.

**Validates: Requirements 5.2**

---

### Property 9: Todas las rutas del módulo requieren el rol Administrador

*Para cualquier* ruta del módulo `trabajadores`, una petición de un usuario autenticado que no tenga el rol `Administrador` debe ser denegada (HTTP 403 o redirección de acceso denegado).

**Validates: Requirements 5.3**

---

## Error Handling

### Errores de validación

Laravel redirige automáticamente de vuelta al formulario con los errores en `$errors` y los valores anteriores en `old()`. Las vistas deben:

- Aplicar `border-red-400 bg-red-50` al campo con error.
- Mostrar el mensaje con `@error('campo')` y clase `text-xs text-red-600`.
- El formulario debe tener `novalidate` para que la validación del navegador no interfiera.

### Eliminación bloqueada

El controlador verifica las relaciones antes de llamar a `delete()`. Si existe alguna relación, redirige a `trabajadores.index` con un flash `error` con mensaje específico por tipo de relación:

- CambioAceite: `"No se puede eliminar el trabajador porque tiene cambios de aceite asociados."`
- Ingreso: `"No se puede eliminar el trabajador porque tiene ingresos asociados."`

No se lanza ninguna excepción; el flujo es siempre una redirección con flash message.

### Route Model Binding fallido

Si `{trabajador}` no corresponde a ningún registro, Laravel lanza automáticamente `ModelNotFoundException` y devuelve HTTP 404. No se requiere manejo adicional en el controlador.

### Acceso no autorizado

El middleware `role:Administrador` (Spatie Permission) maneja el rechazo antes de que la petición llegue al controlador. No se requiere lógica adicional en el controlador.

---

## Testing Strategy

### Enfoque dual

Se combinan tests de ejemplo (feature/HTTP tests) con tests basados en propiedades para las partes de lógica de negocio que varían con el input.

### Tests de ejemplo (PHPUnit / Laravel Feature Tests)

Cubren los flujos concretos y los requisitos de UI:

| Test | Criterio |
|------|----------|
| `GET /trabajadores` devuelve 200 con vista correcta | 1.1 |
| La vista index muestra columnas Nombre, Estado, Acciones | 1.2 |
| La vista index muestra "No hay trabajadores registrados." cuando está vacía | 1.5 |
| La vista index muestra paginación con más de 15 registros | 1.6 |
| La vista index tiene botón "Crear trabajador" enlazando a `trabajadores.create` | 1.4 |
| `GET /trabajadores/create` devuelve 200 con formulario vacío | 2.1 |
| El formulario de creación tiene campo nombre, select estado y atributo novalidate | 2.7, 2.8 |
| `GET /trabajadores/{id}/edit` devuelve 200 | 3.1 |
| El botón de eliminar en index tiene confirmación onclick | 4.4 |
| Las rutas trabajadores.* están registradas en la aplicación | 5.1 |
| Las vistas extienden layouts.app con las clases Tailwind correctas | 6.1–6.6 |

### Tests basados en propiedades (PestPHP con `pest-plugin-arch` o similar)

Para este proyecto PHP/Laravel, se usará **PestPHP** con generadores de datos aleatorios (o `fakerphp/faker` para generar inputs variados) para implementar las propiedades. Cada test de propiedad debe ejecutarse con un mínimo de **100 iteraciones** sobre inputs generados aleatoriamente.

**Librería recomendada:** [`eris/eris`](https://github.com/giorgiosironi/eris) (property-based testing para PHP) o implementación manual con Faker en un loop dentro de Pest.

**Configuración de tags:**

```php
// Feature: trabajadores-crud, Property N: <texto de la propiedad>
```

| Propiedad | Test |
|-----------|------|
| Property 1 | Para 100 trabajadores con estado aleatorio, verificar que el badge renderizado coincide con el valor booleano |
| Property 2 | Para 100 combinaciones de nombre válido + estado aleatorio, verificar que POST crea el registro y redirige con éxito |
| Property 3 | Para 100 inputs inválidos (vacío, >100 chars, duplicado), verificar que la validación falla y redirige con errores |
| Property 4 | Para 100 trabajadores existentes, verificar que PUT con el mismo nombre no falla por unicidad |
| Property 5 | Para 100 trabajadores con datos aleatorios, verificar que la vista edit precarga los valores correctos |
| Property 6 | Para 100 trabajadores sin relaciones, verificar que DELETE elimina el registro |
| Property 7 | Para 100 trabajadores con relaciones (CambioAceite o Ingreso), verificar que DELETE cancela y redirige con error |
| Property 8 | Para cada ruta del módulo, verificar que sin autenticación redirige a /login |
| Property 9 | Para cada ruta del módulo, verificar que sin rol Administrador se deniega el acceso |

### Cobertura de edge cases

Los generadores de inputs para las propiedades deben incluir:

- Nombres con caracteres especiales (tildes, ñ, espacios múltiples)
- Nombres en el límite exacto de 100 caracteres
- Estado enviado como string `"0"` / `"1"` (conversión de formulario HTML)
- Trabajadores con solo CambioAceites, solo Ingresos, o ambos (para Property 7)
