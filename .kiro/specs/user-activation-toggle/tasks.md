# Plan de Implementación: user-activation-toggle

## Descripción general

Implementar el control de activación de usuarios: columna `activo` en la tabla `users`, middleware de verificación de sesión, bloqueo en login, controlador de toggle vía AJAX, y actualización de la vista con badge de estado y botón de acción rápida.

## Tareas

- [x] 1. Migración y modelo: agregar columna `activo` a la tabla `users`
  - Crear el archivo de migración `{timestamp}_add_activo_to_users_table.php` en `database/migrations/`
  - `up()`: `$table->tinyInteger('activo')->default(1)->after('remember_token');`
  - `down()`: `$table->dropColumn('activo');`
  - Agregar `'activo'` al array `$fillable` del modelo `User`
  - Agregar el cast `'activo' => 'boolean'` en el método `casts()` del modelo `User`
  - _Requisitos: 1.2, 1.3, 1.4_

  - [ ]* 1.1 Escribir smoke test de migración
    - Verificar que la columna `activo` existe en la tabla `users` con tipo `tinyint` y valor por defecto `1`
    - Verificar que el rollback elimina la columna
    - Archivo: `tests/Feature/UserActivationToggle/MigrationSmokeTest.php`
    - _Requisitos: 1.3, 1.4_

  - [ ]* 1.2 Escribir test de propiedad: creación de usuario siempre produce `activo = 1`
    - **Propiedad 1: Creación de usuario siempre produce activo = 1**
    - Ejecutar 100 iteraciones con datos generados por Faker (nombre, email, contraseña aleatorios)
    - Verificar que `$user->activo === 1` en cada iteración
    - Archivo: `tests/Feature/UserActivationToggle/UserCreationPropertyTest.php`
    - _Requisitos: 1.1_

- [x] 2. Middleware `CheckUserActivo`: invalidar sesiones de usuarios inactivos
  - Crear `app/Http/Middleware/CheckUserActivo.php`
  - Si `Auth::check() && !Auth::user()->activo`: cerrar sesión, invalidar sesión, regenerar token CSRF, redirigir a `/login` con error `'Tu cuenta ha sido desactivada.'`
  - Registrar el middleware en `bootstrap/app.php` dentro del grupo `web` (después de `auth`)
  - _Requisitos: 3.4_

  - [ ]* 2.1 Escribir test de propiedad: sesión activa de usuario inactivado es invalidada
    - **Propiedad 8: Sesión activa de usuario inactivado es invalidada en el siguiente request**
    - Para cualquier usuario con sesión activa que es inactivado, el siguiente request autenticado SHALL resultar en cierre de sesión y redirección a `/login`
    - Archivo: `tests/Feature/UserActivationToggle/SessionInvalidationPropertyTest.php`
    - _Requisitos: 3.4_

- [x] 3. `LoginController`: bloquear autenticación de usuarios inactivos
  - Modificar `app/Http/Controllers/Auth/LoginController.php`, método `login()`
  - Después de `Auth::attempt($credentials)` exitoso, verificar `Auth::user()->activo`
  - Si `activo = 0`: llamar `Auth::logout()`, retornar `back()->withErrors(['email' => 'Tu cuenta está inactiva. Contacta al administrador.'])->withInput($request->except('password'))`
  - Si `activo = 1`: continuar con `$request->session()->regenerate()` y `redirect('/dashboard')`
  - No modificar el mensaje de error para credenciales inválidas
  - _Requisitos: 3.1, 3.2, 3.3_

  - [ ]* 3.1 Escribir test de propiedad: usuario inactivo no puede autenticarse
    - **Propiedad 5: Usuario inactivo no puede autenticarse**
    - Para cualquier usuario con `activo = 0` y credenciales válidas, el intento SHALL ser rechazado con mensaje de cuenta inactiva
    - Archivo: `tests/Feature/UserActivationToggle/LoginActivationPropertyTest.php`
    - _Requisitos: 3.1_

  - [ ]* 3.2 Escribir test de propiedad: usuario activo con credenciales válidas puede autenticarse
    - **Propiedad 6: Usuario activo con credenciales válidas puede autenticarse**
    - Para cualquier usuario con `activo = 1` y credenciales válidas, el intento SHALL ser exitoso y redirigir al dashboard
    - Incluir en el mismo archivo `LoginActivationPropertyTest.php`
    - _Requisitos: 3.2_

  - [ ]* 3.3 Escribir test de propiedad: credenciales inválidas producen error genérico
    - **Propiedad 7: Credenciales inválidas producen error genérico independientemente del estado activo**
    - Para cualquier usuario con cualquier valor de `activo` y contraseña incorrecta, el mensaje SHALL ser el genérico de credenciales incorrectas
    - Incluir en el mismo archivo `LoginActivationPropertyTest.php`
    - _Requisitos: 3.3_

- [x] 4. Checkpoint — Verificar backend base
  - Asegurarse de que todos los tests pasen hasta este punto. Consultar al usuario si surgen dudas.

- [x] 5. `UserToggleController`: controlador dedicado para el toggle vía AJAX
  - Crear `app/Http/Controllers/UserToggleController.php`
  - Método `toggle(User $user): JsonResponse`
  - Verificar `$user->id !== auth()->id()` → retornar JSON 403 con `{ "message": "No puedes modificar tu propio estado de activación." }`
  - Invertir: `$user->activo = !$user->activo; $user->save();`
  - Retornar JSON 200: `{ "activo": 0|1, "message": "Usuario activado/inactivado correctamente." }`
  - El 404 lo maneja automáticamente Route Model Binding de Laravel
  - _Requisitos: 2.1, 2.2, 2.3, 2.4_

  - [ ]* 5.1 Escribir test de propiedad: toggle invierte el valor de `activo` (round-trip)
    - **Propiedad 2: Toggle invierte el valor de activo (round-trip)**
    - Para cualquier usuario con `activo = 0` o `activo = 1`, aplicar toggle invierte el valor; aplicar dos veces devuelve al estado original
    - Archivo: `tests/Feature/UserActivationToggle/UserTogglePropertyTest.php`
    - _Requisitos: 2.1_

  - [ ]* 5.2 Escribir test de propiedad: respuesta JSON contiene nuevo valor y mensaje
    - **Propiedad 3: Respuesta JSON del toggle contiene el nuevo valor y mensaje**
    - Para cualquier usuario, la respuesta exitosa SHALL contener `activo` (inverso del anterior) y `message` no vacío con HTTP 200
    - Incluir en `UserTogglePropertyTest.php`
    - _Requisitos: 2.2_

  - [ ]* 5.3 Escribir test de propiedad: toggle de usuario inexistente retorna 404
    - **Propiedad 4: Toggle de usuario inexistente retorna 404**
    - Para cualquier ID que no exista en la base de datos, la solicitud SHALL retornar HTTP 404
    - Incluir en `UserTogglePropertyTest.php`
    - _Requisitos: 2.3_

  - [ ]* 5.4 Escribir tests de ejemplo: auto-toggle y protección de ruta
    - Verificar que el toggle sobre el propio usuario autenticado retorna HTTP 403
    - Verificar que la ruta sin autenticación retorna HTTP 302 (redirect a login)
    - Verificar que la ruta sin permiso `editar usuarios` retorna redirección al dashboard
    - Archivo: `tests/Feature/UserActivationToggle/UserToggleExampleTest.php`
    - _Requisitos: 2.4, 2.7_

- [x] 6. Ruta de toggle: registrar en `routes/web.php`
  - Agregar `use App\Http\Controllers\UserToggleController;` al bloque de imports
  - Registrar la ruta dentro del grupo `auth` + `permission:editar usuarios`:
    ```php
    Route::middleware(['auth', 'permission:editar usuarios'])
        ->patch('/users/{user}/toggle', [UserToggleController::class, 'toggle'])
        ->name('users.toggle');
    ```
  - _Requisitos: 2.7_

- [x] 7. Vista `users/index.blade.php`: columna de estado y botón de toggle
  - Agregar columna **Estado** en el `<thead>` entre "Rol" y "Acciones"
  - En cada fila `@foreach`, agregar la celda de estado con badge condicional:
    - `activo = 1`: badge verde con texto "Activo"
    - `activo = 0`: badge rojo con texto "Inactivo"
  - Agregar el `Boton_Toggle` en la columna de acciones con atributos `data-toggle-url="{{ route('users.toggle', $user) }}"` y `data-user-id="{{ $user->id }}"`
    - Texto del botón: "Inactivar" si `activo = 1`, "Activar" si `activo = 0`
  - Incluir `@vite('resources/js/users/toggle.js')` al final de la sección
  - No exponer el campo `activo` en el formulario de edición
  - _Requisitos: 4.1, 4.2_

  - [ ]* 7.1 Escribir test de propiedad: la vista renderiza badge y botón correctos
    - **Propiedad 9: La vista renderiza badge y botón correctos para cualquier valor de activo**
    - Para cualquier usuario con `activo = 0` o `activo = 1`, la vista SHALL renderizar la etiqueta y el botón correctos
    - Archivo: `tests/Feature/UserActivationToggle/UserIndexViewPropertyTest.php`
    - _Requisitos: 4.1, 4.2_

- [x] 8. Módulo JavaScript `resources/js/users/toggle.js`
  - Crear `resources/js/users/toggle.js`
  - Escuchar clicks en botones con atributo `data-toggle-url` usando delegación de eventos en `document`
  - Enviar `PATCH` con `fetch` incluyendo el header `X-CSRF-TOKEN` (leer de `<meta name="csrf-token">`) y `Content-Type: application/json`
  - En respuesta exitosa (HTTP 200): actualizar el badge de estado y el texto/apariencia del botón del usuario afectado usando `data-user-id`
  - En error (HTTP 403, 404, error de red): mostrar mensaje de error sin modificar el estado visual del botón
  - _Requisitos: 2.5, 2.6, 4.3_

  - [ ]* 8.1 Escribir test de propiedad JS: respuesta exitosa actualiza badge y botón
    - **Propiedad 10: El frontend actualiza badge y botón tras respuesta exitosa del toggle**
    - Para cualquier respuesta JSON exitosa con cualquier valor de `activo`, el módulo SHALL actualizar badge y botón en el DOM sin recargar la página
    - Usar Vitest + fast-check, 100 iteraciones (`numRuns: 100`)
    - Archivo: `tests/js/users/toggle.property.test.js`
    - _Requisitos: 2.5, 4.3_

  - [ ]* 8.2 Escribir test de propiedad JS: error de respuesta no modifica estado visual
    - Para cualquier respuesta de error (403, 404, error de red), el módulo SHALL mostrar mensaje de error sin modificar el estado visual del botón
    - Incluir en `tests/js/users/toggle.property.test.js`
    - _Requisitos: 2.6_

- [x] 9. Checkpoint final — Verificar integración completa
  - Asegurarse de que todos los tests pasen. Consultar al usuario si surgen dudas.

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia requisitos específicos para trazabilidad
- Los tests de propiedad ejecutan 100 iteraciones con datos aleatorios para cubrir el espacio de inputs amplio
- Los tests de ejemplo cubren escenarios específicos de negocio (auto-toggle, permisos)
- El smoke test de migración verifica la configuración de infraestructura
- El middleware `CheckUserActivo` debe registrarse en el grupo `web` de `bootstrap/app.php` para cubrir todos los requests autenticados
