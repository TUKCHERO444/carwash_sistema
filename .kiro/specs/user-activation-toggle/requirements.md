# Requirements Document

## Introduction

Esta funcionalidad agrega un campo `activo` a la tabla `users` para controlar el estado de activación de cada usuario del sistema. Un usuario activo (`activo = 1`) puede iniciar sesión normalmente; un usuario inactivo (`activo = 0`) es bloqueado en el login. La activación e inactivación se gestiona exclusivamente desde la tabla de usuarios mediante un botón de acción rápida que opera vía AJAX, sin recargar la página. El formulario de edición de usuario no expone este campo.

## Glossary

- **User**: Entidad registrada en la tabla `users` que puede autenticarse en el sistema.
- **UserController**: Controlador Laravel que gestiona las operaciones CRUD sobre `User`.
- **LoginController**: Controlador Laravel que gestiona la autenticación de usuarios.
- **Toggle**: Acción que alterna el estado `activo` de un `User` entre `1` (activo) y `0` (inactivo).
- **ToggleController**: Controlador Laravel dedicado a procesar la acción de Toggle vía AJAX.
- **Campo_Activo**: Columna `activo` de tipo `tinyInteger` en la tabla `users`, donde `1` representa activo y `0` representa inactivo.
- **Tabla_Usuarios**: Vista Blade que lista los usuarios del sistema con sus acciones disponibles.
- **Boton_Toggle**: Elemento de interfaz en la `Tabla_Usuarios` que ejecuta el Toggle del usuario correspondiente sin recargar la página.
- **Sesion**: Instancia autenticada de un `User` en el sistema.

---

## Requirements

### Requirement 1: Campo de activación en la tabla de usuarios

**User Story:** Como administrador del sistema, quiero que cada usuario tenga un estado de activación, para poder controlar quién puede acceder al sistema sin necesidad de eliminar cuentas.

#### Acceptance Criteria

1. THE `UserController` SHALL asignar `activo = 1` al crear un nuevo `User`, sin requerir que el campo sea enviado desde el formulario de creación.
2. THE `User` SHALL exponer el `Campo_Activo` como atributo de masa asignable (`fillable`) con valor por defecto `1`.
3. WHEN se ejecuta la migración correspondiente, THE base de datos SHALL agregar la columna `activo` de tipo `tinyInteger` con valor por defecto `1` y restricción `NOT NULL` a la tabla `users`.
4. WHEN se ejecuta el rollback de la migración, THE base de datos SHALL eliminar la columna `activo` de la tabla `users`.

---

### Requirement 2: Toggle de activación vía AJAX desde la tabla de usuarios

**User Story:** Como administrador del sistema, quiero activar o inactivar un usuario directamente desde la tabla de usuarios con un solo clic, para gestionar el acceso sin interrumpir mi flujo de trabajo con recargas de página.

#### Acceptance Criteria

1. WHEN el administrador hace clic en el `Boton_Toggle` de un `User`, THE `ToggleController` SHALL alternar el `Campo_Activo` del `User`: si era `1` lo establece en `0`, y si era `0` lo establece en `1`.
2. WHEN el `ToggleController` procesa el Toggle exitosamente, THE `ToggleController` SHALL retornar una respuesta JSON con el nuevo valor del `Campo_Activo` y un mensaje de confirmación, con código HTTP 200.
3. WHEN el `ToggleController` recibe una solicitud para un `User` inexistente, THE `ToggleController` SHALL retornar una respuesta JSON con un mensaje de error, con código HTTP 404.
4. WHEN el administrador intenta hacer Toggle sobre su propio `User` autenticado, THE `ToggleController` SHALL rechazar la operación y retornar una respuesta JSON con un mensaje de error, con código HTTP 403.
5. WHEN el `ToggleController` retorna una respuesta exitosa, THE `Boton_Toggle` SHALL actualizar visualmente su estado en la `Tabla_Usuarios` sin recargar la página completa.
6. WHEN el `ToggleController` retorna un error, THE `Boton_Toggle` SHALL mostrar un mensaje de error al administrador sin modificar el estado visual del botón.
7. THE ruta del Toggle SHALL estar protegida por middleware de autenticación y por el permiso `editar usuarios`.

---

### Requirement 3: Bloqueo de login para usuarios inactivos

**User Story:** Como administrador del sistema, quiero que un usuario inactivo no pueda iniciar sesión, para garantizar que solo los usuarios habilitados accedan al sistema.

#### Acceptance Criteria

1. WHEN un `User` con `activo = 0` intenta autenticarse, THE `LoginController` SHALL rechazar el intento de autenticación y retornar al formulario de login con un mensaje de error indicando que la cuenta está inactiva.
2. WHEN un `User` con `activo = 1` intenta autenticarse con credenciales válidas, THE `LoginController` SHALL permitir el acceso y redirigir al dashboard.
3. IF las credenciales son inválidas independientemente del `Campo_Activo`, THEN THE `LoginController` SHALL retornar el mensaje de error de credenciales incorrectas existente, sin revelar el estado de activación.
4. WHEN un `User` con `activo = 0` tiene una `Sesion` activa al momento de ser inactivado, THE sistema SHALL invalidar esa `Sesion` en el siguiente request del `User`.

---

### Requirement 4: Visualización del estado en la tabla de usuarios

**User Story:** Como administrador del sistema, quiero ver claramente el estado de activación de cada usuario en la tabla, para identificar de un vistazo qué usuarios están habilitados.

#### Acceptance Criteria

1. THE `Tabla_Usuarios` SHALL mostrar el estado del `Campo_Activo` de cada `User` mediante una etiqueta visual diferenciada: una etiqueta de color verde con el texto "Activo" cuando `activo = 1`, y una etiqueta de color rojo con el texto "Inactivo" cuando `activo = 0`.
2. THE `Tabla_Usuarios` SHALL mostrar el `Boton_Toggle` en la columna de acciones de cada `User`, con una apariencia que indique la acción disponible: "Inactivar" cuando el usuario está activo, y "Activar" cuando el usuario está inactivo.
3. WHEN el Toggle se completa exitosamente vía AJAX, THE `Tabla_Usuarios` SHALL actualizar tanto la etiqueta de estado como el texto y apariencia del `Boton_Toggle` del `User` afectado sin recargar la página.
