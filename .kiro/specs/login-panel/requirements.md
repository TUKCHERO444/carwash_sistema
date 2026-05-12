# Documento de Requisitos

## Introducción

Este documento describe los requisitos para el panel de login del sistema de gestión de taller. La funcionalidad incluye autenticación de usuarios mediante email y contraseña, gestión de dos roles (Administrador y Asistente) usando Spatie Laravel Permission, y redirección post-login según el rol asignado. El sistema ya cuenta con el modelo `User`, la migración de la tabla `users`, y la integración de Spatie Laravel Permission.

## Glosario

- **Sistema**: La aplicación Laravel de gestión de taller.
- **LoginController**: Controlador responsable de procesar las solicitudes de autenticación.
- **AuthSeeder**: Seeder responsable de crear los roles y el usuario administrador inicial.
- **Autenticador**: Componente de Laravel que verifica las credenciales del usuario contra la base de datos.
- **Sesión**: Registro de la sesión activa del usuario autenticado en el servidor.
- **Rol_Administrador**: Rol con acceso completo a todos los módulos del sistema.
- **Rol_Asistente**: Rol sin permisos específicos asignados en esta versión del sistema.
- **Panel_Login**: Vista Blade que presenta el formulario de autenticación al usuario.
- **Guard**: Mecanismo de Laravel que define cómo se autentican y persisten los usuarios.

---

## Requisitos

### Requisito 1: Formulario de Login

**Historia de usuario:** Como usuario del sistema, quiero acceder mediante un formulario de login con email y contraseña, para poder autenticarme de forma segura.

#### Criterios de Aceptación

1. THE Panel_Login SHALL mostrar un campo de entrada de tipo email con etiqueta "Correo electrónico".
2. THE Panel_Login SHALL mostrar un campo de entrada de tipo password con etiqueta "Contraseña".
3. THE Panel_Login SHALL mostrar un botón de envío con el texto "Iniciar sesión".
4. THE Panel_Login SHALL extender el layout `layouts.app` o un layout dedicado sin sidebar para páginas de autenticación.
5. WHEN el usuario accede a la ruta `/login`, THE Sistema SHALL mostrar el Panel_Login.
6. WHEN el usuario ya está autenticado y accede a la ruta `/login`, THE Sistema SHALL redirigirlo al dashboard.

---

### Requisito 2: Autenticación de Credenciales

**Historia de usuario:** Como usuario registrado, quiero que el sistema valide mis credenciales, para que solo usuarios autorizados puedan acceder.

#### Criterios de Aceptación

1. WHEN el usuario envía el formulario con un email y contraseña válidos, THE Autenticador SHALL verificar las credenciales contra la tabla `users`.
2. WHEN las credenciales son correctas, THE LoginController SHALL crear una Sesión autenticada para el usuario.
3. WHEN las credenciales son correctas, THE LoginController SHALL redirigir al usuario a la ruta `/dashboard`.
4. IF el email no existe en la base de datos, THEN THE LoginController SHALL retornar al Panel_Login con el mensaje de error "Las credenciales proporcionadas no coinciden con nuestros registros.".
5. IF la contraseña es incorrecta, THEN THE LoginController SHALL retornar al Panel_Login con el mensaje de error "Las credenciales proporcionadas no coinciden con nuestros registros.".
6. THE Panel_Login SHALL mostrar los mensajes de error de validación junto al campo correspondiente.
7. WHEN el formulario se envía con el campo email vacío, THE Sistema SHALL retornar un error de validación indicando que el campo es obligatorio.
8. WHEN el formulario se envía con el campo password vacío, THE Sistema SHALL retornar un error de validación indicando que el campo es obligatorio.
9. WHEN el formulario se envía con un valor en el campo email que no tiene formato de email válido, THE Sistema SHALL retornar un error de validación indicando el formato incorrecto.

---

### Requisito 3: Cierre de Sesión

**Historia de usuario:** Como usuario autenticado, quiero poder cerrar mi sesión, para que mi acceso quede revocado al terminar de usar el sistema.

#### Criterios de Aceptación

1. WHEN el usuario autenticado envía una solicitud POST a la ruta `/logout`, THE Sistema SHALL invalidar la Sesión activa del usuario.
2. WHEN la Sesión es invalidada, THE Sistema SHALL regenerar el token CSRF.
3. WHEN la Sesión es invalidada, THE Sistema SHALL redirigir al usuario a la ruta `/login`.
4. WHILE el usuario no está autenticado, THE Sistema SHALL denegar el acceso a la ruta `/dashboard` y redirigirlo a `/login`.

---

### Requisito 4: Gestión de Roles con Spatie Laravel Permission

**Historia de usuario:** Como administrador del sistema, quiero que existan dos roles definidos (Administrador y Asistente), para controlar el nivel de acceso de cada usuario.

#### Criterios de Aceptación

1. THE AuthSeeder SHALL crear el rol `Administrador` con el guard `web` en la tabla `roles` si no existe.
2. THE AuthSeeder SHALL crear el rol `Asistente` con el guard `web` en la tabla `roles` si no existe.
3. THE AuthSeeder SHALL crear un usuario administrador inicial con email `admin@sistema.com` y asignarle el rol `Administrador`.
4. WHEN el AuthSeeder se ejecuta más de una vez, THE AuthSeeder SHALL omitir la creación de registros que ya existen (operación idempotente).
5. THE User SHALL usar el trait `HasRoles` de Spatie Laravel Permission para gestionar la asignación de roles.
6. WHEN un usuario con rol `Administrador` inicia sesión, THE Sistema SHALL permitir el acceso a todos los módulos del sistema.
7. WHEN un usuario con rol `Asistente` inicia sesión, THE Sistema SHALL permitir el acceso al dashboard sin permisos adicionales asignados.

---

### Requisito 5: Protección de Rutas

**Historia de usuario:** Como administrador del sistema, quiero que las rutas del sistema estén protegidas por autenticación, para que solo usuarios con sesión activa puedan acceder.

#### Criterios de Aceptación

1. WHILE el usuario no está autenticado, THE Sistema SHALL redirigir cualquier acceso a rutas protegidas hacia `/login`.
2. THE Sistema SHALL aplicar el middleware `auth` a la ruta `/dashboard`.
3. WHEN el usuario autenticado accede a `/dashboard`, THE Sistema SHALL mostrar la vista del dashboard sin redireccionamiento.
4. IF el middleware `auth` detecta que la Sesión ha expirado, THEN THE Sistema SHALL redirigir al usuario a `/login`.
