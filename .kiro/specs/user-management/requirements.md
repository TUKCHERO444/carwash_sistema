# Documento de Requisitos: Gestión de Usuarios

## Introducción

El módulo de Gestión de Usuarios permite a los administradores del sistema crear, listar, editar y eliminar usuarios, así como gestionar los roles disponibles y los permisos asociados a cada rol. El módulo se integra con Spatie Laravel Permission v6 (ya instalado) y es accesible exclusivamente para el rol `Administrador`. La navegación hacia el módulo se expone en el sidebar existente (desktop) y en el bottom nav (móvil) mediante un botón con submenú desplegable.

---

## Glosario

- **Sistema**: La aplicación Laravel 12 en su conjunto.
- **Módulo_Usuarios**: El submódulo de listado y gestión de usuarios dentro de Gestión de Usuarios.
- **Módulo_Roles**: El submódulo de listado y gestión de roles dentro de Gestión de Usuarios.
- **Controlador_Usuarios**: El controlador Laravel responsable de las operaciones CRUD de usuarios (`UserController`).
- **Controlador_Roles**: El controlador Laravel responsable de las operaciones CRUD de roles (`RoleController`).
- **Formulario_Usuario**: El formulario Blade para crear o editar un usuario.
- **Formulario_Rol**: El formulario Blade para crear o editar un rol.
- **Sidebar**: El panel de navegación lateral visible en pantallas de escritorio (≥ 1024 px), implementado como `<aside>` en `layouts/app.blade.php`.
- **Bottom_Nav**: La barra de navegación fija en la parte inferior de la pantalla, visible en dispositivos móviles (< 1024 px), implementada como `<nav>` en `layouts/app.blade.php`.
- **Dropdown_Usuarios**: El submenú desplegable del botón "Gestión de usuarios" en el Sidebar (se despliega hacia abajo).
- **Dropup_Usuarios**: El submenú desplegable del botón "Gestión de usuarios" en el Bottom_Nav (se despliega hacia arriba).
- **Spatie**: La librería Spatie Laravel Permission v6, con los modelos `Role` y `Permission`.
- **Rol_Administrador**: El rol `Administrador` definido en el `AuthSeeder`, con acceso completo al módulo.
- **Rol_Asistente**: El rol `Asistente` definido en el `AuthSeeder`, sin acceso al módulo de Gestión de Usuarios.
- **Middleware_Rol**: El middleware `role:Administrador` de Spatie que protege las rutas del módulo.
- **Guard_Web**: El guard de autenticación `web` por defecto de Laravel.

---

## Requisitos

### Requisito 1: Protección de acceso al módulo

**User Story:** Como administrador del sistema, quiero que el módulo de Gestión de Usuarios sea accesible únicamente para el rol `Administrador`, de modo que los usuarios con rol `Asistente` no puedan acceder ni ver las opciones del módulo.

#### Criterios de Aceptación

1. WHEN un usuario autenticado con Rol_Asistente intenta acceder a cualquier ruta del módulo de Gestión de Usuarios, THEN el Sistema SHALL redirigir al usuario a la vista del dashboard con un mensaje de error HTTP 403.
2. WHEN un usuario no autenticado intenta acceder a cualquier ruta del módulo de Gestión de Usuarios, THEN el Sistema SHALL redirigir al usuario a la ruta `login`.
3. WHEN un usuario autenticado con Rol_Administrador accede a cualquier ruta del módulo de Gestión de Usuarios, THEN el Sistema SHALL permitir el acceso y renderizar la vista correspondiente.
4. THE Sistema SHALL proteger todas las rutas del módulo de Gestión de Usuarios con los middlewares `auth` y `role:Administrador` de Spatie.
5. WHILE el usuario autenticado tiene el Rol_Asistente, THE Sidebar SHALL ocultar el botón "Gestión de usuarios" y sus submenús.
6. WHILE el usuario autenticado tiene el Rol_Administrador, THE Sidebar SHALL mostrar el botón "Gestión de usuarios" con sus submenús.

---

### Requisito 2: Navegación — Botón "Gestión de usuarios" en el Sidebar (desktop)

**User Story:** Como administrador, quiero ver un botón "Gestión de usuarios" en el sidebar de escritorio que al hacer clic despliegue un submenú con las opciones "Usuarios" y "Roles", de modo que pueda navegar rápidamente a cada submódulo.

#### Criterios de Aceptación

1. THE Sidebar SHALL contener un botón con la etiqueta "Gestión de usuarios" visible para usuarios con Rol_Administrador.
2. WHEN el usuario hace clic en el botón "Gestión de usuarios" del Sidebar, THE Dropdown_Usuarios SHALL mostrarse debajo del botón con las opciones "Usuarios" y "Roles".
3. WHEN el Dropdown_Usuarios está visible y el usuario hace clic nuevamente en el botón "Gestión de usuarios", THE Dropdown_Usuarios SHALL ocultarse (comportamiento toggle).
4. THE Sidebar SHALL mostrar un indicador visual (ícono chevron) junto al botón "Gestión de usuarios" que rota 180° cuando el Dropdown_Usuarios está abierto.
5. WHEN el usuario hace clic en la opción "Usuarios" del Dropdown_Usuarios, THE Sistema SHALL navegar a la ruta `users.index`.
6. WHEN el usuario hace clic en la opción "Roles" del Dropdown_Usuarios, THE Sistema SHALL navegar a la ruta `roles.index`.
7. WHILE el usuario está en una ruta del Módulo_Usuarios o Módulo_Roles, THE Dropdown_Usuarios SHALL permanecer abierto y el botón "Gestión de usuarios" SHALL mostrar el estilo de elemento activo.
8. THE Dropdown_Usuarios SHALL implementarse con JavaScript vanilla o Alpine.js, sin jQuery ni CDN externos.

---

### Requisito 3: Navegación — Botón "Gestión de usuarios" en el Bottom Nav (móvil)

**User Story:** Como administrador usando un dispositivo móvil, quiero ver un botón "Gestión de usuarios" en la barra de navegación inferior que al hacer clic despliegue las opciones hacia arriba, de modo que pueda acceder a los submódulos sin que el menú quede oculto por el teclado o el borde inferior.

#### Criterios de Aceptación

1. THE Bottom_Nav SHALL contener un botón con la etiqueta "Gestión de usuarios" visible para usuarios con Rol_Administrador.
2. WHEN el usuario hace clic en el botón "Gestión de usuarios" del Bottom_Nav, THE Dropup_Usuarios SHALL mostrarse encima del Bottom_Nav con las opciones "Usuarios" y "Roles".
3. WHEN el Dropup_Usuarios está visible y el usuario hace clic nuevamente en el botón "Gestión de usuarios", THE Dropup_Usuarios SHALL ocultarse (comportamiento toggle).
4. THE Bottom_Nav SHALL mostrar un indicador visual (ícono chevron) junto al botón "Gestión de usuarios" que rota 180° cuando el Dropup_Usuarios está abierto.
5. WHEN el usuario hace clic en la opción "Usuarios" del Dropup_Usuarios, THE Sistema SHALL navegar a la ruta `users.index`.
6. WHEN el usuario hace clic en la opción "Roles" del Dropup_Usuarios, THE Sistema SHALL navegar a la ruta `roles.index`.
7. WHILE el usuario está en una ruta del Módulo_Usuarios o Módulo_Roles, THE Dropup_Usuarios SHALL permanecer abierto y el botón "Gestión de usuarios" SHALL mostrar el estilo de elemento activo.
8. THE Dropup_Usuarios SHALL implementarse con JavaScript vanilla o Alpine.js, sin jQuery ni CDN externos.

---

### Requisito 4: CRUD de Usuarios — Listado

**User Story:** Como administrador, quiero ver un listado paginado de todos los usuarios del sistema con su nombre, email y rol asignado, de modo que pueda tener una visión general de los usuarios existentes.

#### Criterios de Aceptación

1. WHEN el Controlador_Usuarios recibe una solicitud GET a la ruta `users.index`, THE Controlador_Usuarios SHALL recuperar todos los usuarios de la base de datos con su rol asociado y renderizar la vista de listado.
2. THE Módulo_Usuarios SHALL mostrar para cada usuario: nombre, email, rol asignado, y botones de acción "Editar" y "Eliminar".
3. THE Módulo_Usuarios SHALL paginar el listado de usuarios mostrando 15 registros por página.
4. WHEN no existen usuarios en la base de datos, THE Módulo_Usuarios SHALL mostrar un mensaje indicando que no hay usuarios registrados.
5. THE Módulo_Usuarios SHALL mostrar un botón "Crear usuario" que navega a la ruta `users.create`.

---

### Requisito 5: CRUD de Usuarios — Creación

**User Story:** Como administrador, quiero crear nuevos usuarios asignándoles nombre, email, contraseña y un rol, de modo que pueda incorporar nuevos miembros al sistema con los permisos adecuados.

#### Criterios de Aceptación

1. WHEN el Controlador_Usuarios recibe una solicitud GET a la ruta `users.create`, THE Controlador_Usuarios SHALL renderizar el Formulario_Usuario con la lista de roles disponibles.
2. THE Formulario_Usuario SHALL contener los campos: nombre (texto), email (email), contraseña (password), confirmación de contraseña (password), y selector de rol (select).
3. WHEN el Formulario_Usuario es enviado con datos válidos, THE Controlador_Usuarios SHALL crear el usuario en la base de datos, asignarle el rol seleccionado mediante Spatie, y redirigir a `users.index` con un mensaje de éxito.
4. IF el campo email ya existe en la base de datos, THEN THE Controlador_Usuarios SHALL retornar al Formulario_Usuario con el error de validación "El correo electrónico ya está en uso."
5. IF el campo contraseña tiene menos de 8 caracteres, THEN THE Controlador_Usuarios SHALL retornar al Formulario_Usuario con el error de validación correspondiente.
6. IF el campo contraseña no coincide con la confirmación de contraseña, THEN THE Controlador_Usuarios SHALL retornar al Formulario_Usuario con el error de validación correspondiente.
7. IF el campo nombre está vacío, THEN THE Controlador_Usuarios SHALL retornar al Formulario_Usuario con el error de validación correspondiente.
8. IF el campo email tiene formato inválido, THEN THE Controlador_Usuarios SHALL retornar al Formulario_Usuario con el error de validación correspondiente.
9. IF no se selecciona un rol, THEN THE Controlador_Usuarios SHALL retornar al Formulario_Usuario con el error de validación correspondiente.
10. THE Controlador_Usuarios SHALL almacenar la contraseña del usuario como hash bcrypt, nunca en texto plano.

---

### Requisito 6: CRUD de Usuarios — Edición

**User Story:** Como administrador, quiero editar los datos de un usuario existente (nombre, email, contraseña y rol), de modo que pueda mantener la información actualizada.

#### Criterios de Aceptación

1. WHEN el Controlador_Usuarios recibe una solicitud GET a la ruta `users.edit` con un ID de usuario válido, THE Controlador_Usuarios SHALL renderizar el Formulario_Usuario con los datos actuales del usuario y la lista de roles disponibles.
2. THE Formulario_Usuario en modo edición SHALL mostrar los campos nombre y email pre-rellenados con los valores actuales del usuario.
3. THE Formulario_Usuario en modo edición SHALL mostrar el campo contraseña vacío; si se deja vacío al guardar, THE Controlador_Usuarios SHALL conservar la contraseña actual sin modificarla.
4. WHEN el Formulario_Usuario de edición es enviado con datos válidos, THE Controlador_Usuarios SHALL actualizar el usuario en la base de datos, sincronizar el rol mediante Spatie, y redirigir a `users.index` con un mensaje de éxito.
5. IF el campo email ya existe en la base de datos para un usuario diferente, THEN THE Controlador_Usuarios SHALL retornar al Formulario_Usuario con el error de validación "El correo electrónico ya está en uso."
6. IF el campo contraseña no está vacío y tiene menos de 8 caracteres, THEN THE Controlador_Usuarios SHALL retornar al Formulario_Usuario con el error de validación correspondiente.
7. IF el campo contraseña no está vacío y no coincide con la confirmación, THEN THE Controlador_Usuarios SHALL retornar al Formulario_Usuario con el error de validación correspondiente.
8. IF el ID de usuario no existe en la base de datos, THEN THE Controlador_Usuarios SHALL retornar una respuesta HTTP 404.

---

### Requisito 7: CRUD de Usuarios — Eliminación

**User Story:** Como administrador, quiero eliminar usuarios del sistema, de modo que pueda retirar el acceso a usuarios que ya no deben estar en el sistema.

#### Criterios de Aceptación

1. WHEN el Controlador_Usuarios recibe una solicitud DELETE a la ruta `users.destroy` con un ID de usuario válido, THE Controlador_Usuarios SHALL eliminar el usuario de la base de datos y redirigir a `users.index` con un mensaje de éxito.
2. IF el ID de usuario a eliminar no existe en la base de datos, THEN THE Controlador_Usuarios SHALL retornar una respuesta HTTP 404.
3. IF el usuario a eliminar es el mismo usuario autenticado actualmente, THEN THE Controlador_Usuarios SHALL rechazar la operación y retornar a `users.index` con un mensaje de error indicando que no es posible eliminar el propio usuario.
4. THE Módulo_Usuarios SHALL solicitar confirmación al usuario antes de ejecutar la eliminación, mediante un diálogo de confirmación en el navegador.

---

### Requisito 8: CRUD de Roles — Listado

**User Story:** Como administrador, quiero ver un listado de todos los roles del sistema con los permisos asignados a cada uno, de modo que pueda tener una visión general de la configuración de acceso.

#### Criterios de Aceptación

1. WHEN el Controlador_Roles recibe una solicitud GET a la ruta `roles.index`, THE Controlador_Roles SHALL recuperar todos los roles de la base de datos con sus permisos asociados y renderizar la vista de listado.
2. THE Módulo_Roles SHALL mostrar para cada rol: nombre del rol, lista de permisos asignados, y botones de acción "Editar" y "Eliminar".
3. WHEN no existen roles en la base de datos, THE Módulo_Roles SHALL mostrar un mensaje indicando que no hay roles registrados.
4. THE Módulo_Roles SHALL mostrar un botón "Crear rol" que navega a la ruta `roles.create`.

---

### Requisito 9: CRUD de Roles — Creación

**User Story:** Como administrador, quiero crear nuevos roles y asignarles permisos específicos, de modo que pueda definir niveles de acceso personalizados para distintos tipos de usuarios.

#### Criterios de Aceptación

1. WHEN el Controlador_Roles recibe una solicitud GET a la ruta `roles.create`, THE Controlador_Roles SHALL renderizar el Formulario_Rol con la lista de permisos disponibles en el sistema.
2. THE Formulario_Rol SHALL contener: un campo de nombre del rol (texto) y una lista de checkboxes con todos los permisos disponibles.
3. WHEN el Formulario_Rol es enviado con datos válidos, THE Controlador_Roles SHALL crear el rol en la base de datos mediante Spatie, asignarle los permisos seleccionados, y redirigir a `roles.index` con un mensaje de éxito.
4. IF el campo nombre del rol está vacío, THEN THE Controlador_Roles SHALL retornar al Formulario_Rol con el error de validación correspondiente.
5. IF el nombre del rol ya existe en la base de datos, THEN THE Controlador_Roles SHALL retornar al Formulario_Rol con el error de validación "El nombre del rol ya está en uso."
6. THE Controlador_Roles SHALL crear el rol con el Guard_Web (`guard_name: 'web'`).

---

### Requisito 10: CRUD de Roles — Edición

**User Story:** Como administrador, quiero editar el nombre y los permisos de un rol existente, de modo que pueda ajustar los niveles de acceso según las necesidades del negocio.

#### Criterios de Aceptación

1. WHEN el Controlador_Roles recibe una solicitud GET a la ruta `roles.edit` con un ID de rol válido, THE Controlador_Roles SHALL renderizar el Formulario_Rol con el nombre actual del rol y los permisos actualmente asignados marcados.
2. WHEN el Formulario_Rol de edición es enviado con datos válidos, THE Controlador_Roles SHALL actualizar el nombre del rol y sincronizar los permisos mediante Spatie, y redirigir a `roles.index` con un mensaje de éxito.
3. IF el nombre del rol ya existe en la base de datos para un rol diferente, THEN THE Controlador_Roles SHALL retornar al Formulario_Rol con el error de validación "El nombre del rol ya está en uso."
4. IF el campo nombre del rol está vacío, THEN THE Controlador_Roles SHALL retornar al Formulario_Rol con el error de validación correspondiente.
5. IF el ID de rol no existe en la base de datos, THEN THE Controlador_Roles SHALL retornar una respuesta HTTP 404.

---

### Requisito 11: CRUD de Roles — Eliminación

**User Story:** Como administrador, quiero eliminar roles que ya no sean necesarios, de modo que pueda mantener limpia la configuración de acceso del sistema.

#### Criterios de Aceptación

1. WHEN el Controlador_Roles recibe una solicitud DELETE a la ruta `roles.destroy` con un ID de rol válido, THE Controlador_Roles SHALL eliminar el rol de la base de datos y redirigir a `roles.index` con un mensaje de éxito.
2. IF el ID de rol a eliminar no existe en la base de datos, THEN THE Controlador_Roles SHALL retornar una respuesta HTTP 404.
3. IF el rol a eliminar tiene usuarios asignados, THEN THE Controlador_Roles SHALL rechazar la operación y retornar a `roles.index` con un mensaje de error indicando que el rol tiene usuarios asignados y no puede eliminarse.
4. THE Módulo_Roles SHALL solicitar confirmación al usuario antes de ejecutar la eliminación, mediante un diálogo de confirmación en el navegador.

---

### Requisito 12: Consistencia visual y accesibilidad

**User Story:** Como usuario del sistema, quiero que el módulo de Gestión de Usuarios mantenga la misma apariencia visual que el resto de la aplicación, de modo que la experiencia sea coherente y accesible.

#### Criterios de Aceptación

1. THE Sistema SHALL implementar todas las vistas del módulo de Gestión de Usuarios extendiendo el layout `layouts.app` mediante `@extends('layouts.app')`.
2. THE Sistema SHALL usar exclusivamente Tailwind CSS v4 (compilado via Vite) para los estilos, sin CDN ni estilos en línea.
3. THE Formulario_Usuario y el Formulario_Rol SHALL mostrar los mensajes de error de validación junto al campo correspondiente.
4. THE Formulario_Usuario y el Formulario_Rol SHALL mostrar un mensaje de éxito tras una operación exitosa de creación o edición.
5. THE Sistema SHALL incluir atributos `aria-label` en los botones de acción (Editar, Eliminar, Crear) para garantizar accesibilidad.
6. THE Sistema SHALL incluir el token CSRF en todos los formularios mediante la directiva `@csrf`.
7. THE Sistema SHALL usar el método HTTP correcto para cada operación: GET para listado/formularios, POST para creación, PUT/PATCH para edición, DELETE para eliminación, usando la directiva `@method` de Blade donde sea necesario.
