# Design Document — user-activation-toggle

## Overview

Esta funcionalidad agrega control de activación de usuarios al sistema. Se introduce una columna `activo` en la tabla `users` que determina si un usuario puede autenticarse. La gestión del estado se realiza desde la tabla de usuarios mediante un botón de acción rápida que opera vía AJAX, sin recargar la página. El `LoginController` existente se extiende para rechazar el acceso a usuarios inactivos.

### Decisiones de diseño clave

- **Controlador dedicado (`UserToggleController`)**: La acción de toggle se separa del `UserController` para mantener la responsabilidad única. El `UserController` gestiona CRUD; el `UserToggleController` gestiona exclusivamente el cambio de estado.
- **PATCH sobre POST**: La ruta de toggle usa el verbo HTTP `PATCH` porque modifica parcialmente un recurso existente, lo que es semánticamente correcto y consistente con REST.
- **Middleware de permiso, no de rol**: La ruta se protege con el permiso `editar usuarios` (Spatie) en lugar de `role:Administrador`, lo que permite mayor flexibilidad futura sin cambiar el código.
- **Invalidación de sesión por middleware**: La verificación del estado `activo` en cada request se implementa como un middleware de aplicación, no en el `LoginController`, para cubrir también sesiones ya activas al momento de la inactivación.
- **JavaScript vanilla con `fetch`**: Consistente con el patrón existente del proyecto (no usa jQuery ni librerías AJAX externas).

---

## Architecture

```mermaid
flowchart TD
    subgraph Frontend
        A[users/index.blade.php] -->|click Boton_Toggle| B[users/toggle.js]
        B -->|PATCH /users/{id}/toggle| C[HTTP AJAX]
    end

    subgraph Backend
        C --> D[Middleware: auth]
        D --> E[Middleware: permission:editar usuarios]
        E --> F[UserToggleController@toggle]
        F -->|findOrFail| G[(users table)]
        F -->|guard: no self-toggle| H[HTTP 403]
        F -->|update activo| G
        F -->|JSON response| B
    end

    subgraph Login Flow
        I[LoginController@login] -->|Auth::attempt| J{credentials valid?}
        J -->|no| K[error: credenciales incorrectas]
        J -->|sí| L{activo = 1?}
        L -->|no| M[error: cuenta inactiva]
        L -->|sí| N[redirect /dashboard]
    end

    subgraph Session Guard
        O[CheckUserActivo middleware] -->|every request| P{activo = 1?}
        P -->|no| Q[logout + redirect /login]
        P -->|sí| R[continue]
    end
```

---

## Components and Interfaces

### 1. Migración: `add_activo_to_users_table`

Nueva migración que agrega la columna `activo` a la tabla `users`.

```
Archivo: database/migrations/{timestamp}_add_activo_to_users_table.php
```

- `up()`: `$table->tinyInteger('activo')->default(1)->after('remember_token');`
- `down()`: `$table->dropColumn('activo');`

### 2. Modelo `User` (modificación)

Agregar `activo` al array `$fillable` y definir el cast correspondiente.

```php
protected $fillable = ['name', 'email', 'password', 'activo'];

protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'activo'            => 'boolean',
    ];
}
```

El valor por defecto `1` lo provee la migración a nivel de base de datos.

### 3. `UserController` (modificación mínima)

En el método `store()`, no se requiere ningún cambio: la columna tiene `default(1)` en la base de datos, por lo que cualquier `User::create([...])` sin el campo `activo` recibirá automáticamente el valor `1`.

### 4. `UserToggleController` (nuevo)

```
Archivo: app/Http/Controllers/UserToggleController.php
```

**Método `toggle(User $user): JsonResponse`**

- Verifica que `$user->id !== auth()->id()` → 403 si es el propio usuario.
- Invierte el valor: `$user->activo = !$user->activo`.
- Persiste con `$user->save()`.
- Retorna JSON: `{ "activo": 0|1, "message": "..." }` con HTTP 200.
- Si el usuario no existe, Laravel Route Model Binding retorna 404 automáticamente.

### 5. `CheckUserActivo` Middleware (nuevo)

```
Archivo: app/Http/Middleware/CheckUserActivo.php
```

Se ejecuta en cada request autenticado. Si el usuario autenticado tiene `activo = 0`, cierra la sesión y redirige a `/login` con un mensaje de error.

```php
public function handle(Request $request, Closure $next): Response
{
    if (Auth::check() && !Auth::user()->activo) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')
            ->withErrors(['email' => 'Tu cuenta ha sido desactivada.']);
    }
    return $next($request);
}
```

Se registra en `bootstrap/app.php` dentro del grupo `web` después de `auth`.

### 6. `LoginController` (modificación)

Después de `Auth::attempt($credentials)` exitoso, verificar `activo`:

```php
if (Auth::attempt($credentials)) {
    if (!Auth::user()->activo) {
        Auth::logout();
        return back()->withErrors([
            'email' => 'Tu cuenta está inactiva. Contacta al administrador.',
        ])->withInput($request->except('password'));
    }
    $request->session()->regenerate();
    return redirect('/dashboard');
}
```

### 7. Ruta de Toggle (modificación de `routes/web.php`)

```php
Route::middleware(['auth', 'permission:editar usuarios'])
    ->patch('/users/{user}/toggle', [UserToggleController::class, 'toggle'])
    ->name('users.toggle');
```

Se agrega dentro del grupo existente de gestión de usuarios o como ruta independiente con sus propios middlewares.

### 8. Vista `users/index.blade.php` (modificación)

- Nueva columna **Estado** con badge verde/rojo.
- `Boton_Toggle` en la columna de acciones con atributos `data-*` para el JS.
- Incluir el script `@vite('resources/js/users/toggle.js')`.

### 9. `resources/js/users/toggle.js` (nuevo)

Módulo JavaScript que:
- Escucha clicks en botones con `data-toggle-url`.
- Envía `PATCH` con `fetch` incluyendo el token CSRF.
- En respuesta exitosa: actualiza el badge y el botón del usuario afectado.
- En error: muestra un mensaje de error sin modificar el estado visual.

---

## Data Models

### Tabla `users` (modificada)

| Columna            | Tipo           | Restricciones              |
|--------------------|----------------|----------------------------|
| id                 | bigint unsigned| PK, auto-increment         |
| name               | varchar(255)   | NOT NULL                   |
| email              | varchar(255)   | NOT NULL, UNIQUE           |
| email_verified_at  | timestamp      | NULLABLE                   |
| password           | varchar(255)   | NOT NULL                   |
| **activo**         | **tinyint**    | **NOT NULL, DEFAULT 1**    |
| remember_token     | varchar(100)   | NULLABLE                   |
| created_at         | timestamp      | NULLABLE                   |
| updated_at         | timestamp      | NULLABLE                   |

### Respuesta JSON del Toggle

```json
{
  "activo": 0,
  "message": "Usuario inactivado correctamente."
}
```

```json
{
  "activo": 1,
  "message": "Usuario activado correctamente."
}
```

### Respuesta JSON de error (403)

```json
{
  "message": "No puedes modificar tu propio estado de activación."
}
```

---

## Correctness Properties

*Una propiedad es una característica o comportamiento que debe mantenerse verdadero en todas las ejecuciones válidas del sistema — esencialmente, una declaración formal sobre lo que el sistema debe hacer. Las propiedades sirven como puente entre las especificaciones legibles por humanos y las garantías de corrección verificables por máquinas.*

### Property 1: Creación de usuario siempre produce activo = 1

*Para cualquier* conjunto válido de datos de usuario (nombre, email, contraseña), cuando el `UserController` crea un nuevo `User`, el campo `activo` del usuario creado SHALL ser `1`.

**Validates: Requirements 1.1**

---

### Property 2: Toggle invierte el valor de activo (round-trip)

*Para cualquier* usuario con `activo = 0` o `activo = 1`, aplicar el toggle SHALL invertir el valor. Aplicar el toggle dos veces consecutivas SHALL devolver el usuario a su estado original.

**Validates: Requirements 2.1**

---

### Property 3: Respuesta JSON del toggle contiene el nuevo valor y mensaje

*Para cualquier* usuario con cualquier valor de `activo`, cuando el toggle se procesa exitosamente, la respuesta JSON SHALL contener el nuevo valor de `activo` (el inverso del anterior) y un campo `message` no vacío, con código HTTP 200.

**Validates: Requirements 2.2**

---

### Property 4: Toggle de usuario inexistente retorna 404

*Para cualquier* identificador de usuario que no exista en la base de datos, la solicitud de toggle SHALL retornar una respuesta JSON con código HTTP 404.

**Validates: Requirements 2.3**

---

### Property 5: Usuario inactivo no puede autenticarse

*Para cualquier* usuario con `activo = 0` y credenciales válidas, el intento de autenticación SHALL ser rechazado y el formulario de login SHALL mostrar un mensaje de error indicando que la cuenta está inactiva.

**Validates: Requirements 3.1**

---

### Property 6: Usuario activo con credenciales válidas puede autenticarse

*Para cualquier* usuario con `activo = 1` y credenciales válidas, el intento de autenticación SHALL ser exitoso y redirigir al dashboard.

**Validates: Requirements 3.2**

---

### Property 7: Credenciales inválidas producen error genérico independientemente del estado activo

*Para cualquier* usuario con cualquier valor de `activo` y credenciales incorrectas (contraseña errónea), el `LoginController` SHALL retornar el mensaje de error genérico de credenciales incorrectas, sin revelar el estado de activación.

**Validates: Requirements 3.3**

---

### Property 8: Sesión activa de usuario inactivado es invalidada en el siguiente request

*Para cualquier* usuario con una sesión activa que es inactivado (activo = 0), el siguiente request autenticado de ese usuario SHALL resultar en cierre de sesión y redirección a `/login`.

**Validates: Requirements 3.4**

---

### Property 9: La vista renderiza badge y botón correctos para cualquier valor de activo

*Para cualquier* usuario con `activo = 0` o `activo = 1`, la vista `users/index` SHALL renderizar la etiqueta de estado correcta ("Activo"/"Inactivo" con los colores correspondientes) y el botón de toggle con el texto correcto ("Inactivar"/"Activar").

**Validates: Requirements 4.1, 4.2**

---

### Property 10: El frontend actualiza badge y botón tras respuesta exitosa del toggle

*Para cualquier* respuesta JSON exitosa del toggle (con cualquier valor de `activo`), el módulo JavaScript SHALL actualizar tanto la etiqueta de estado como el texto y apariencia del botón del usuario afectado en el DOM, sin recargar la página.

**Validates: Requirements 2.5, 4.3**

---

## Error Handling

### Errores del backend (`UserToggleController`)

| Escenario | Código HTTP | Respuesta |
|-----------|-------------|-----------|
| Usuario no encontrado | 404 | `{ "message": "No encontrado." }` (Route Model Binding automático) |
| Auto-toggle (propio usuario) | 403 | `{ "message": "No puedes modificar tu propio estado de activación." }` |
| Sin autenticación | 302 | Redirect a `/login` (middleware `auth`) |
| Sin permiso `editar usuarios` | 302/403 | Redirect a `/dashboard` con error (handler de Spatie existente en `bootstrap/app.php`) |

### Errores del frontend (`toggle.js`)

| Escenario | Comportamiento |
|-----------|----------------|
| HTTP 403 | Mostrar mensaje de error; no modificar el estado visual del botón |
| HTTP 404 | Mostrar mensaje de error; no modificar el estado visual del botón |
| Error de red / timeout | Mostrar mensaje de error genérico; no modificar el estado visual del botón |
| HTTP 200 con `activo` inesperado | Actualizar el DOM con el valor recibido del servidor (fuente de verdad) |

### Errores del `LoginController`

| Escenario | Comportamiento |
|-----------|----------------|
| Credenciales inválidas (cualquier `activo`) | Mensaje genérico: "Las credenciales proporcionadas no coinciden con nuestros registros." |
| Credenciales válidas + `activo = 0` | Mensaje específico: "Tu cuenta está inactiva. Contacta al administrador." |

**Principio de seguridad**: El mensaje de cuenta inactiva solo se muestra cuando las credenciales son correctas. Si las credenciales son incorrectas, siempre se muestra el mensaje genérico, evitando revelar si una cuenta existe o su estado.

---

## Testing Strategy

### Herramientas

- **PHPUnit 11** (ya configurado en el proyecto) — tests de feature y unit para el backend.
- **Vitest + fast-check** (ya instalados en el proyecto) — tests de propiedades para el módulo JavaScript.
- **Faker** (ya disponible en el proyecto) — generación de datos aleatorios en tests PHP.

### Tests de backend (PHPUnit)

**Tests de propiedades** (`tests/Feature/UserActivationToggle/`):

Cada test de propiedad ejecuta **100 iteraciones** con datos generados por Faker.

| Archivo | Propiedades cubiertas |
|---------|----------------------|
| `UserCreationPropertyTest.php` | Property 1 |
| `UserTogglePropertyTest.php` | Properties 2, 3, 4 |
| `LoginActivationPropertyTest.php` | Properties 5, 6, 7 |
| `SessionInvalidationPropertyTest.php` | Property 8 |
| `UserIndexViewPropertyTest.php` | Property 9 |

**Tests de ejemplo** (`tests/Feature/UserActivationToggle/`):

| Archivo | Criterios cubiertos |
|---------|---------------------|
| `UserToggleExampleTest.php` | 2.4 (auto-toggle → 403), 2.7 (ruta protegida: sin auth → 302, sin permiso → 403) |
| `UserModelTest.php` | 1.2 (activo en $fillable, default 1) |

**Tests de smoke** (`tests/Feature/UserActivationToggle/`):

| Archivo | Criterios cubiertos |
|---------|---------------------|
| `MigrationSmokeTest.php` | 1.3 (columna existe con tipo y default correctos), 1.4 (rollback elimina columna) |

### Tests de frontend (Vitest + fast-check)

**Archivo**: `tests/js/users/toggle.property.test.js`

| Test | Propiedad cubierta |
|------|--------------------|
| Respuesta exitosa actualiza badge y botón | Property 10 |
| Error de respuesta no modifica estado visual | Requirement 2.6 |

Cada test de propiedad JS ejecuta **100 iteraciones** (`numRuns: 100`).

### Etiquetado de tests de propiedad

Cada test de propiedad incluye un comentario de referencia:

```php
// Feature: user-activation-toggle, Property 2: Toggle invierte el valor de activo (round-trip)
```

```js
// Feature: user-activation-toggle, Property 10: El frontend actualiza badge y botón tras respuesta exitosa
```

### Cobertura complementaria

- Los tests de propiedad cubren el espacio de inputs amplio (100+ iteraciones con datos aleatorios).
- Los tests de ejemplo cubren escenarios específicos de negocio (auto-toggle, permisos).
- Los tests de smoke verifican la configuración de infraestructura (migración).
- Juntos garantizan cobertura completa de todos los criterios de aceptación.
