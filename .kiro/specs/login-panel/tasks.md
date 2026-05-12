# Plan de Implementación: Panel de Login

## Visión General

Implementación manual del sistema de autenticación en Laravel 12 sin scaffolding (Breeze/Jetstream). Incluye el `LoginController`, vistas Blade con layout dedicado para auth, rutas protegidas con middleware, el trait `HasRoles` en el modelo `User`, y el `AuthSeeder` para roles y usuario administrador inicial.

## Tareas

- [x] 1. Añadir el trait `HasRoles` al modelo `User`
  - Importar `Spatie\Permission\Traits\HasRoles` en `app/Models/User.php`
  - Añadir `HasRoles` a la lista de traits usados en la clase
  - _Requisitos: 4.5_

  - [ ]* 1.1 Escribir smoke test: el modelo `User` usa el trait `HasRoles`
    - Verificar que `User` tiene el método `hasRole()` disponible (instancia de `HasRoles`)
    - _Requisitos: 4.5_

- [x] 2. Crear el `AuthSeeder` con roles y usuario administrador
  - Crear `database/seeders/AuthSeeder.php`
  - Usar `Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web'])` y lo mismo para `Asistente`
  - Usar `User::firstOrCreate(['email' => 'admin@sistema.com'], [...])` y asignar rol `Administrador`
  - Registrar `AuthSeeder` en `DatabaseSeeder::run()` (llamar con `$this->call([AuthSeeder::class])`)
  - _Requisitos: 4.1, 4.2, 4.3, 4.4_

  - [ ]* 2.1 Escribir smoke tests del `AuthSeeder`
    - Test: rol `Administrador` existe en la tabla `roles` tras ejecutar el seeder
    - Test: rol `Asistente` existe en la tabla `roles` tras ejecutar el seeder
    - Test: usuario `admin@sistema.com` existe y tiene el rol `Administrador`
    - _Requisitos: 4.1, 4.2, 4.3_

  - [ ]* 2.2 Escribir property test para idempotencia del `AuthSeeder`
    - **Propiedad 5: El AuthSeeder es idempotente**
    - Ejecutar el seeder N veces (N entre 1 y 20) y verificar que el estado final es idéntico al de una sola ejecución (mismo número de roles, mismo número de usuarios admin, mismas asignaciones)
    - **Valida: Requisito 4.4**

- [x] 3. Crear el layout `layouts.auth` para páginas de autenticación
  - Crear `resources/views/layouts/auth.blade.php`
  - Layout minimalista centrado, sin sidebar ni bottom nav
  - Incluir directivas `@vite` para CSS/JS
  - Incluir sección `@yield('content')`
  - _Requisitos: 1.4_

- [x] 4. Crear la vista `auth/login.blade.php`
  - Crear `resources/views/auth/login.blade.php`
  - Extender `layouts.auth`
  - Campo `email` (tipo `email`) con etiqueta "Correo electrónico" y visualización de errores `@error('email')`
  - Campo `password` (tipo `password`) con etiqueta "Contraseña" y visualización de errores `@error('password')`
  - Botón de envío con texto "Iniciar sesión"
  - Token CSRF con `@csrf`
  - Acción del formulario apuntando a `route('login')` con método POST
  - _Requisitos: 1.1, 1.2, 1.3, 1.4, 2.6_

- [x] 5. Crear el `LoginController`
  - Crear `app/Http/Controllers/Auth/LoginController.php`
  - Método `showLoginForm()`: retorna la vista `auth.login`
  - Método `login(Request $request)`: valida campos (`email`: required/string/email, `password`: required/string), llama a `Auth::attempt()`, regenera sesión con `$request->session()->regenerate()`, redirige a `/dashboard` si éxito, o retorna con error en clave `email` con el mensaje genérico si falla
  - Método `logout(Request $request)`: llama a `Auth::logout()`, invalida la sesión con `$request->session()->invalidate()`, regenera el token CSRF con `$request->session()->regenerateToken()`, redirige a `/login`
  - _Requisitos: 2.1, 2.2, 2.3, 2.4, 2.5, 3.1, 3.2, 3.3_

- [x] 6. Registrar las rutas de autenticación en `routes/web.php`
  - Importar `LoginController` en `routes/web.php`
  - Grupo con middleware `guest`: `GET /login` → `showLoginForm` (name: `login`), `POST /login` → `login`
  - Ruta `POST /logout` con middleware `auth` → `logout` (name: `logout`)
  - Añadir middleware `auth` a la ruta existente `GET /dashboard`
  - _Requisitos: 1.5, 1.6, 3.1, 3.3, 3.4, 5.1, 5.2, 5.3_

- [x] 7. Checkpoint — Verificar flujo básico de autenticación
  - Asegurar que todas las pruebas pasan hasta este punto, preguntar al usuario si surgen dudas.

- [x] 8. Escribir Feature Tests para el flujo de autenticación
  - Crear directorio `tests/Feature/Auth/`
  - Crear `tests/Feature/Auth/LoginTest.php` con los siguientes tests de ejemplo:

  - [x] 8.1 Tests del formulario de login (Requisito 1)
    - `GET /login` retorna HTTP 200 y renderiza la vista `auth.login`
    - La vista contiene campo `email` con etiqueta "Correo electrónico"
    - La vista contiene campo `password` con etiqueta "Contraseña"
    - La vista contiene botón con texto "Iniciar sesión"
    - Usuario ya autenticado en `GET /login` es redirigido a `/dashboard`
    - _Requisitos: 1.1, 1.2, 1.3, 1.5, 1.6_

  - [x] 8.2 Tests de autenticación de credenciales (Requisito 2)
    - `POST /login` con credenciales válidas redirige a `/dashboard` y crea sesión autenticada
    - `POST /login` con email inexistente retorna al login con error en campo `email`
    - `POST /login` con contraseña incorrecta retorna al login con el mensaje de error genérico
    - `POST /login` con campo `email` vacío retorna error de validación obligatorio
    - `POST /login` con campo `password` vacío retorna error de validación obligatorio
    - `POST /login` con email de formato inválido retorna error de validación de formato
    - _Requisitos: 2.1, 2.2, 2.3, 2.4, 2.5, 2.7, 2.8, 2.9_

  - [x] 8.3 Tests de cierre de sesión y protección de rutas (Requisitos 3 y 5)
    - `POST /logout` invalida la sesión y redirige a `/login`
    - `GET /dashboard` sin autenticación redirige a `/login`
    - `GET /dashboard` con autenticación retorna HTTP 200
    - _Requisitos: 3.1, 3.2, 3.3, 3.4, 5.1, 5.2, 5.3_

  - [x] 8.4 Tests de roles (Requisito 4)
    - Usuario con rol `Administrador` accede al dashboard sin redirección
    - Usuario con rol `Asistente` accede al dashboard sin redirección
    - _Requisitos: 4.6, 4.7_

- [x] 9. Escribir Property Tests para las propiedades de corrección
  - Crear `tests/Feature/Auth/LoginPropertyTest.php`
  - Usar generadores con `faker` dentro de PHPUnit para iterar sobre rangos de entradas (mínimo 100 iteraciones por propiedad)

  - [ ]* 9.1 Escribir property test para autenticación exitosa con credenciales válidas
    - **Propiedad 1: Autenticación exitosa para credenciales válidas**
    - Para cualquier usuario generado aleatoriamente (email y contraseña aleatorios), `Auth::attempt()` con sus credenciales debe retornar `true`
    - **Valida: Requisito 2.1**

  - [ ]* 9.2 Escribir property test para mensaje de error con credenciales inválidas
    - **Propiedad 2: Credenciales inválidas producen el mensaje de error correcto**
    - Para cualquier combinación de email no registrado o contraseña incorrecta, `POST /login` debe retornar al formulario con el mensaje `"Las credenciales proporcionadas no coinciden con nuestros registros."` en el campo `email`
    - **Valida: Requisitos 2.4, 2.5**

  - [ ]* 9.3 Escribir property test para visualización de errores de validación
    - **Propiedad 3: Errores de validación se muestran junto al campo correspondiente**
    - Para cualquier envío con datos inválidos (campo vacío o email con formato incorrecto), el HTML de respuesta debe contener el mensaje de error junto al campo que falló
    - **Valida: Requisitos 2.6, 2.7, 2.8, 2.9**

  - [ ]* 9.4 Escribir property test para protección de rutas
    - **Propiedad 4: Acceso no autenticado a rutas protegidas redirige a /login**
    - Para cualquier ruta protegida por middleware `auth`, una solicitud sin sesión activa debe recibir una redirección HTTP 302 hacia `/login`
    - **Valida: Requisitos 3.4, 5.1, 5.4**

- [x] 10. Checkpoint final — Asegurar que todas las pruebas pasan
  - Ejecutar la suite completa con `php artisan test`
  - Asegurar que todos los tests pasan, preguntar al usuario si surgen dudas.

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia los requisitos específicos para trazabilidad
- Los property tests usan `faker` con PHPUnit (sin dependencia externa adicional); cada propiedad se itera mínimo 100 veces
- El campo `password` nunca se repopula en el formulario (seguridad por defecto de Laravel con `$dontFlash`)
- El mensaje de error de credenciales es genérico para evitar enumeración de usuarios
