# Plan de Implementación: Gestión de Usuarios

## Visión General

Implementación del módulo de Gestión de Usuarios sobre Laravel 12 con Spatie Permission v6. El plan cubre: handler de excepciones, rutas resource protegidas, dos controladores CRUD, modificación del layout con dropdown/dropup, JS vanilla para los toggles, seis vistas Blade, y una suite de pruebas de ejemplo y de propiedades.

## Tareas

- [x] 1. Registrar el handler de `UnauthorizedException` en `bootstrap/app.php`
  - Importar `Spatie\Permission\Exceptions\UnauthorizedException` y `Illuminate\Foundation\Configuration\Exceptions`
  - Dentro de `->withExceptions()`, registrar `$exceptions->render()` para `UnauthorizedException` que redirija a `route('dashboard')` con `with('error', 'No tienes permisos para acceder a esta sección.')`
  - _Requisitos: 1.1, 1.4_

- [x] 2. Registrar las rutas resource en `routes/web.php`
  - Importar `App\Http\Controllers\UserController` y `App\Http\Controllers\RoleController`
  - Añadir grupo `Route::middleware(['auth', 'role:Administrador'])` con `Route::resource('users', UserController::class)->except(['show'])` y `Route::resource('roles', RoleController::class)->except(['show'])`
  - Verificar que las 12 rutas generadas (`users.*`, `roles.*`) quedan registradas
  - _Requisitos: 1.4, 4.1, 5.1, 6.1, 7.1, 8.1, 9.1, 10.1, 11.1_

- [x] 3. Implementar `UserController`
  - [x] 3.1 Crear `app/Http/Controllers/UserController.php` con los métodos `index`, `create`, `store`, `edit`, `update`, `destroy`
    - `index()`: recuperar `User::with('roles')->paginate(15)` y retornar vista `users.index`
    - `create()`: recuperar `Role::all()` y retornar vista `users.create`
    - `store(Request $request)`: validar campos (ver tabla de reglas en diseño), crear usuario con `User::create()`, llamar `$user->syncRoles([$request->role])`, redirigir a `users.index` con `success`
    - `edit(User $user)`: recuperar `Role::all()` y retornar vista `users.edit` con el usuario
    - `update(Request $request, User $user)`: validar campos (password `nullable`/`sometimes`), actualizar usuario omitiendo password si viene vacío, llamar `$user->syncRoles([$request->role])`, redirigir a `users.index` con `success`
    - `destroy(User $user)`: rechazar si `$user->id === auth()->id()`, eliminar y redirigir con `success`
    - Usar `Hash::make()` para la contraseña; nunca almacenar texto plano
    - _Requisitos: 4.1, 4.3, 5.3, 5.4–5.10, 6.1–6.8, 7.1–7.3_

  - [ ]* 3.2 Escribir pruebas de ejemplo para `UserController`
    - Crear `tests/Feature/UserManagement/UserControllerTest.php`
    - Cubrir: GET /users con Asistente redirige al dashboard; GET /users sin auth redirige a login; GET /users con Administrador retorna 200; GET /users con 0 usuarios muestra mensaje vacío; vista index contiene botón "Crear usuario"; formulario create contiene todos los campos y roles; POST /users sin nombre retorna error; POST /users sin rol retorna error; GET /users/{id}/edit con ID inexistente retorna 404; DELETE /users/{id} con ID inexistente retorna 404; botón eliminar tiene confirmación JS; vistas usan @csrf y @method; botones tienen aria-label
    - _Requisitos: 1.1, 1.2, 1.3, 4.4, 4.5, 5.1, 5.2, 5.7, 5.9, 6.8, 7.2, 7.4, 12.5, 12.6, 12.7_

  - [ ]* 3.3 Escribir prueba de propiedad: acceso denegado a Asistente (Propiedad 1)
    - Crear `tests/Feature/UserManagement/AccessControlPropertyTest.php`
    - **Propiedad 1: Acceso denegado a usuarios sin rol Administrador**
    - Para cualquier ruta del módulo (`users.*`, `roles.*`), usuario con rol Asistente es redirigido al dashboard con error
    - Mínimo 100 iteraciones
    - **Valida: Requisitos 1.1, 1.4**

  - [ ]* 3.4 Escribir prueba de propiedad: acceso sin autenticación redirige a login (Propiedad 2)
    - En `tests/Feature/UserManagement/AccessControlPropertyTest.php`
    - **Propiedad 2: Acceso no autenticado redirige a login**
    - Para cualquier ruta del módulo, solicitud sin sesión activa recibe redirect 302 a `login`
    - Mínimo 100 iteraciones
    - **Valida: Requisitos 1.2, 1.4**

- [x] 4. Checkpoint — Verificar rutas, handler y controlador de usuarios
  - Asegurarse de que todas las pruebas pasan hasta este punto. Consultar al usuario si surgen dudas.

- [x] 5. Implementar `RoleController`
  - [x] 5.1 Crear `app/Http/Controllers/RoleController.php` con los métodos `index`, `create`, `store`, `edit`, `update`, `destroy`
    - `index()`: recuperar `Role::with('permissions')->get()` y retornar vista `roles.index`
    - `create()`: recuperar `Permission::all()` y retornar vista `roles.create`
    - `store(Request $request)`: validar campos (ver tabla de reglas en diseño), crear rol con `Role::create(['name' => ..., 'guard_name' => 'web'])`, llamar `$role->syncPermissions($request->permissions ?? [])`, redirigir a `roles.index` con `success`
    - `edit(Role $role)`: recuperar `Permission::all()` y retornar vista `roles.edit` con el rol
    - `update(Request $request, Role $role)`: validar campos, actualizar nombre, llamar `$role->syncPermissions($request->permissions ?? [])`, redirigir a `roles.index` con `success`
    - `destroy(Role $role)`: rechazar si `$role->users()->count() > 0`, eliminar y redirigir con `success`
    - _Requisitos: 8.1–8.4, 9.1–9.6, 10.1–10.5, 11.1–11.4_

  - [ ]* 5.2 Escribir pruebas de ejemplo para `RoleController`
    - Crear `tests/Feature/UserManagement/RoleControllerTest.php`
    - Cubrir: GET /roles con 0 roles muestra mensaje vacío; vista roles/index contiene botón "Crear rol"; formulario roles/create contiene campo nombre y checkboxes de permisos; POST /roles sin nombre retorna error; GET /roles/{id}/edit con ID inexistente retorna 404; DELETE /roles/{id} con ID inexistente retorna 404; botón eliminar rol tiene confirmación JS; vistas usan @csrf y @method; botones tienen aria-label
    - _Requisitos: 8.3, 8.4, 9.2, 9.4, 10.5, 11.2, 11.4, 12.5, 12.6, 12.7_

- [x] 6. Implementar las vistas Blade de usuarios
  - [x] 6.1 Crear `resources/views/users/index.blade.php`
    - Extender `layouts.app` con `@extends('layouts.app')`
    - Mostrar mensajes flash `success` y `error` al inicio del contenido
    - Tabla con columnas: Nombre, Email, Rol, Acciones (Editar, Eliminar)
    - Mostrar `$user->getRoleNames()->first()` para el rol
    - Botón "Crear usuario" que navega a `users.create`
    - Mensaje de lista vacía cuando `$users->isEmpty()`
    - Paginación con `{{ $users->links() }}`
    - Botón Eliminar con `@method('DELETE')`, `@csrf`, y `onclick="return confirm('¿Estás seguro?')"`
    - Atributos `aria-label` en botones Editar y Eliminar
    - _Requisitos: 4.2, 4.3, 4.4, 4.5, 7.4, 12.1–12.7_

  - [x] 6.2 Crear `resources/views/users/create.blade.php`
    - Extender `layouts.app`
    - Formulario con `action="{{ route('users.store') }}"`, `method="POST"`, `@csrf`
    - Campos: nombre (text), email (email), contraseña (password), confirmación de contraseña (password), rol (select con `@foreach($roles as $role)`)
    - Mostrar errores de validación junto a cada campo con `@error('campo')`
    - Repoblar campos con `old('campo')` excepto contraseña
    - Atributo `aria-label` en el botón de envío
    - _Requisitos: 5.1, 5.2, 12.1–12.7_

  - [x] 6.3 Crear `resources/views/users/edit.blade.php`
    - Extender `layouts.app`
    - Formulario con `action="{{ route('users.update', $user) }}"`, `method="POST"`, `@csrf`, `@method('PUT')`
    - Campos pre-rellenados con `old('name', $user->name)` y `old('email', $user->email)`
    - Campo contraseña vacío con nota "Dejar en blanco para conservar la contraseña actual"
    - Select de rol con el rol actual del usuario pre-seleccionado
    - Mostrar errores de validación junto a cada campo con `@error('campo')`
    - Atributo `aria-label` en el botón de envío
    - _Requisitos: 6.1, 6.2, 6.3, 12.1–12.7_

- [x] 7. Implementar las vistas Blade de roles
  - [x] 7.1 Crear `resources/views/roles/index.blade.php`
    - Extender `layouts.app`
    - Mostrar mensajes flash `success` y `error` al inicio del contenido
    - Tabla con columnas: Nombre del Rol, Permisos, Acciones (Editar, Eliminar)
    - Mostrar permisos como lista separada por comas con `$role->permissions->pluck('name')->join(', ')`
    - Botón "Crear rol" que navega a `roles.create`
    - Mensaje de lista vacía cuando `$roles->isEmpty()`
    - Botón Eliminar con `@method('DELETE')`, `@csrf`, y `onclick="return confirm('¿Estás seguro?')"`
    - Atributos `aria-label` en botones Editar y Eliminar
    - _Requisitos: 8.2, 8.3, 8.4, 11.4, 12.1–12.7_

  - [x] 7.2 Crear `resources/views/roles/create.blade.php`
    - Extender `layouts.app`
    - Formulario con `action="{{ route('roles.store') }}"`, `method="POST"`, `@csrf`
    - Campo nombre del rol (text)
    - Lista de checkboxes con todos los permisos disponibles (`@foreach($permissions as $permission)`)
    - Mostrar errores de validación junto a cada campo con `@error('campo')`
    - Repoblar checkboxes con `old('permissions', [])` para mantener selección tras error
    - Atributo `aria-label` en el botón de envío
    - _Requisitos: 9.1, 9.2, 12.1–12.7_

  - [x] 7.3 Crear `resources/views/roles/edit.blade.php`
    - Extender `layouts.app`
    - Formulario con `action="{{ route('roles.update', $role) }}"`, `method="POST"`, `@csrf`, `@method('PUT')`
    - Campo nombre pre-rellenado con `old('name', $role->name)`
    - Checkboxes de permisos con los permisos actuales del rol pre-marcados: `checked="{{ in_array($permission->name, old('permissions', $role->permissions->pluck('name')->toArray())) ? 'checked' : '' }}"`
    - Mostrar errores de validación junto a cada campo con `@error('campo')`
    - Atributo `aria-label` en el botón de envío
    - _Requisitos: 10.1, 12.1–12.7_

- [x] 8. Checkpoint — Verificar controladores y vistas
  - Asegurarse de que todas las pruebas pasan hasta este punto. Consultar al usuario si surgen dudas.

- [x] 9. Modificar `resources/views/layouts/app.blade.php` para añadir navegación de Gestión de Usuarios
  - [x] 9.1 Añadir variable de estado activo al inicio del layout
    - Añadir bloque `@php $userManagementActive = request()->routeIs('users.*', 'roles.*'); @endphp`
    - _Requisitos: 2.7, 3.7_

  - [x] 9.2 Añadir dropdown "Gestión de usuarios" en el sidebar (desktop)
    - Envolver en `@if(auth()->user()?->hasRole('Administrador'))` para visibilidad condicional
    - Estructura: `<div data-dropdown="user-management">` con botón `data-dropdown-toggle="user-management"` y menú `data-dropdown-menu="user-management"`
    - Botón con ícono de usuarios, texto "Gestión de usuarios", y chevron SVG con `data-chevron` y clase `rotate-180` si `$userManagementActive`
    - Menú con clase `hidden` si `!$userManagementActive`, con enlaces a `users.index` y `roles.index`
    - Aplicar estilo activo al botón cuando `$userManagementActive` es verdadero
    - _Requisitos: 1.5, 1.6, 2.1–2.8_

  - [x] 9.3 Añadir dropup "Gestión de usuarios" en el bottom nav (móvil)
    - Envolver en `@if(auth()->user()?->hasRole('Administrador'))` para visibilidad condicional
    - Estructura: `<div data-dropdown="user-management-mobile" class="relative">` con botón `data-dropdown-toggle="user-management-mobile"` y menú `data-dropdown-menu="user-management-mobile"`
    - Menú posicionado con `absolute bottom-16` para que se despliegue hacia arriba
    - Menú con clase `hidden` si `!$userManagementActive`, con `data-persistent` si `$userManagementActive`
    - Botón con ícono, texto "Gestión de usuarios", y chevron SVG con `data-chevron`
    - _Requisitos: 1.5, 1.6, 3.1–3.8_

  - [ ]* 9.4 Escribir pruebas de ejemplo para el layout
    - En `tests/Feature/UserManagement/UserControllerTest.php` o archivo separado `LayoutNavigationTest.php`
    - Cubrir: layout muestra botón "Gestión de usuarios" para Administrador; layout oculta botón para Asistente
    - _Requisitos: 1.5, 1.6, 2.1, 3.1_

- [x] 10. Implementar toggle de dropdown/dropup en `resources/js/app.js`
  - Reemplazar el contenido de `app.js` manteniendo `import './bootstrap'`
  - Añadir listener `DOMContentLoaded` que itera sobre `[data-dropdown-toggle]` y registra click handler
  - En el click handler: obtener `key` de `button.dataset.dropdownToggle`, localizar `[data-dropdown-menu="${key}"]` y `[data-chevron]`, hacer toggle de clase `hidden` en el menú y `rotate-180` en el chevron
  - Añadir listener global `click` en `document` para cerrar dropdowns al hacer clic fuera, respetando `data-persistent` (no cerrar si el menú tiene ese atributo)
  - _Requisitos: 2.2, 2.3, 2.4, 2.8, 3.2, 3.3, 3.4, 3.8_

- [ ] 11. Pruebas de propiedades para CRUD de usuarios
  - [ ]* 11.1 Escribir prueba de propiedad: listado incluye todos los usuarios con sus roles (Propiedad 3)
    - Crear `tests/Feature/UserManagement/UserCrudPropertyTest.php`
    - **Propiedad 3: Listado de usuarios incluye todos los registros con sus roles**
    - Para cualquier N ≤ 15 usuarios, GET /users contiene nombre, email y rol de cada uno
    - Mínimo 100 iteraciones
    - **Valida: Requisitos 4.1, 4.2**

  - [ ]* 11.2 Escribir prueba de propiedad: paginación limita a 15 registros (Propiedad 4)
    - En `tests/Feature/UserManagement/UserCrudPropertyTest.php`
    - **Propiedad 4: Paginación limita a 15 registros por página**
    - Para cualquier N > 15 usuarios, la primera página muestra exactamente 15 y tiene controles de paginación
    - Mínimo 50 iteraciones
    - **Valida: Requisito 4.3**

  - [ ]* 11.3 Escribir prueba de propiedad: creación de usuario con datos válidos persiste (Propiedad 5)
    - En `tests/Feature/UserManagement/UserCrudPropertyTest.php`
    - **Propiedad 5: Creación de usuario con datos válidos persiste en base de datos**
    - Para cualquier combinación válida (nombre, email único, password ≥ 8 chars, rol existente), POST /users crea el usuario con el rol asignado y redirige con éxito
    - Mínimo 100 iteraciones
    - **Valida: Requisito 5.3**

  - [ ]* 11.4 Escribir prueba de propiedad: unicidad de email en creación y edición (Propiedad 6)
    - En `tests/Feature/UserManagement/UserCrudPropertyTest.php`
    - **Propiedad 6: Unicidad de email se aplica en creación y edición**
    - Para cualquier email ya existente, crear o editar un usuario diferente con ese email retorna error "El correo electrónico ya está en uso."
    - Mínimo 100 iteraciones
    - **Valida: Requisitos 5.4, 6.5**

  - [ ]* 11.5 Escribir prueba de propiedad: validación de contraseña (Propiedad 7)
    - En `tests/Feature/UserManagement/UserCrudPropertyTest.php`
    - **Propiedad 7: Validación de contraseña se aplica en creación y edición**
    - Para cualquier contraseña de longitud 1–7 chars, o par contraseña/confirmación no idénticos, el sistema rechaza el formulario con errores de validación
    - Mínimo 100 iteraciones
    - **Valida: Requisitos 5.5, 5.6, 6.6, 6.7**

  - [ ]* 11.6 Escribir prueba de propiedad: contraseñas nunca en texto plano (Propiedad 8)
    - En `tests/Feature/UserManagement/UserCrudPropertyTest.php`
    - **Propiedad 8: Las contraseñas nunca se almacenan en texto plano**
    - Para cualquier contraseña enviada, el valor en BD es diferente al texto plano y verificable con `Hash::check()`
    - Mínimo 100 iteraciones
    - **Valida: Requisito 5.10**

  - [ ]* 11.7 Escribir prueba de propiedad: formulario de edición pre-rellena datos (Propiedad 9)
    - En `tests/Feature/UserManagement/UserCrudPropertyTest.php`
    - **Propiedad 9: Formulario de edición pre-rellena datos del usuario**
    - Para cualquier usuario existente, GET /users/{user}/edit contiene nombre y email actuales en los campos del formulario
    - Mínimo 100 iteraciones
    - **Valida: Requisitos 6.1, 6.2**

  - [ ]* 11.8 Escribir prueba de propiedad: edición sin contraseña preserva la original (Propiedad 10)
    - En `tests/Feature/UserManagement/UserCrudPropertyTest.php`
    - **Propiedad 10: Edición sin contraseña preserva la contraseña original**
    - Para cualquier usuario existente, PUT /users/{user} con password vacío no modifica la contraseña almacenada
    - Mínimo 100 iteraciones
    - **Valida: Requisito 6.3**

  - [ ]* 11.9 Escribir prueba de propiedad: protección contra auto-eliminación (Propiedad 11)
    - En `tests/Feature/UserManagement/UserCrudPropertyTest.php`
    - **Propiedad 11: Protección contra auto-eliminación de usuario**
    - Para cualquier usuario autenticado con rol Administrador, DELETE /users/{propio_id} es rechazado con redirect y mensaje de error, sin eliminar el usuario
    - Mínimo 100 iteraciones
    - **Valida: Requisito 7.3**

  - [ ]* 11.10 Escribir prueba de propiedad: eliminación de usuario lo remueve de BD (Propiedad 12)
    - En `tests/Feature/UserManagement/UserCrudPropertyTest.php`
    - **Propiedad 12: Eliminación de usuario lo remueve de la base de datos**
    - Para cualquier usuario que no sea el autenticado, DELETE /users/{user} elimina el usuario de la tabla y redirige con éxito
    - Mínimo 100 iteraciones
    - **Valida: Requisito 7.1**

- [ ] 12. Pruebas de propiedades para CRUD de roles
  - [ ]* 12.1 Escribir prueba de propiedad: creación de rol usa guard_name 'web' (Propiedad 13)
    - Crear `tests/Feature/UserManagement/RoleCrudPropertyTest.php`
    - **Propiedad 13: Creación de rol con datos válidos usa guard_name 'web'**
    - Para cualquier nombre de rol único y permisos válidos, POST /roles crea el rol con `guard_name='web'` y los permisos asignados
    - Mínimo 100 iteraciones
    - **Valida: Requisitos 9.3, 9.6**

  - [ ]* 12.2 Escribir prueba de propiedad: unicidad de nombre de rol (Propiedad 14)
    - En `tests/Feature/UserManagement/RoleCrudPropertyTest.php`
    - **Propiedad 14: Unicidad de nombre de rol se aplica en creación y edición**
    - Para cualquier nombre de rol ya existente, crear o editar un rol diferente con ese nombre retorna error "El nombre del rol ya está en uso."
    - Mínimo 100 iteraciones
    - **Valida: Requisitos 9.5, 10.3**

  - [ ]* 12.3 Escribir prueba de propiedad: formulario de edición de rol pre-marca permisos (Propiedad 15)
    - En `tests/Feature/UserManagement/RoleCrudPropertyTest.php`
    - **Propiedad 15: Formulario de edición de rol pre-marca los permisos asignados**
    - Para cualquier rol con N permisos, GET /roles/{role}/edit contiene exactamente esos N permisos marcados como checked
    - Mínimo 100 iteraciones
    - **Valida: Requisito 10.1**

  - [ ]* 12.4 Escribir prueba de propiedad: protección contra eliminación de rol con usuarios (Propiedad 16)
    - En `tests/Feature/UserManagement/RoleCrudPropertyTest.php`
    - **Propiedad 16: Protección contra eliminación de rol con usuarios asignados**
    - Para cualquier rol con al menos un usuario asignado, DELETE /roles/{role} es rechazado con redirect y mensaje de error, sin eliminar el rol
    - Mínimo 100 iteraciones
    - **Valida: Requisito 11.3**

  - [ ]* 12.5 Escribir prueba de propiedad: eliminación de rol sin usuarios lo remueve de BD (Propiedad 17)
    - En `tests/Feature/UserManagement/RoleCrudPropertyTest.php`
    - **Propiedad 17: Eliminación de rol sin usuarios lo remueve de la base de datos**
    - Para cualquier rol sin usuarios asignados, DELETE /roles/{role} elimina el rol de la tabla y redirige con éxito
    - Mínimo 100 iteraciones
    - **Valida: Requisito 11.1**

- [x] 13. Checkpoint final — Verificar suite completa de pruebas
  - Asegurarse de que todas las pruebas pasan. Consultar al usuario si surgen dudas.

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia los requisitos específicos para trazabilidad
- Los checkpoints garantizan validación incremental antes de continuar
- Las pruebas de propiedades usan la librería `eris/eris` (ver sección de estrategia de pruebas en `design.md`)
- Las pruebas de ejemplo usan PHPUnit con los helpers de Laravel Feature Tests (`actingAs`, `assertRedirect`, `assertSee`, etc.)
- El orden de las tareas garantiza que no quede código huérfano: las rutas se registran antes de los controladores, los controladores antes de las vistas, y el layout se modifica antes del JS
