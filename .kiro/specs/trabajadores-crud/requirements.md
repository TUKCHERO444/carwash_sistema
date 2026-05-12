# Requirements Document

## Introduction

Este feature añade un CRUD completo para la entidad **Trabajador** dentro de la sección de gestión de usuarios del sistema. El módulo permite a los administradores listar, crear, editar y eliminar trabajadores, respetando las relaciones existentes con `CambioAceite` e `Ingreso`. El diseño visual y los patrones de código siguen exactamente los módulos `users` y `roles` ya existentes en la aplicación Laravel + Blade + Tailwind CSS.

## Glossary

- **TrabajadorController**: Controlador Laravel que gestiona las operaciones CRUD sobre la entidad Trabajador.
- **Trabajador**: Entidad del dominio almacenada en la tabla `trabajadores`, con campos `id`, `nombre`, `estado` y `timestamps`.
- **Listado**: Vista `trabajadores.index` que muestra todos los trabajadores paginados.
- **Formulario**: Vistas `trabajadores.create` y `trabajadores.edit` que permiten crear y editar trabajadores.
- **Estado**: Campo booleano del Trabajador que indica si está activo (`true`) o inactivo (`false`).
- **Badge**: Elemento visual inline que muestra el estado del trabajador con color verde (activo) o rojo (inactivo).
- **Administrador**: Rol del sistema con acceso exclusivo al módulo de gestión de trabajadores.
- **CambioAceite**: Entidad relacionada con Trabajador mediante `hasMany`; su existencia bloquea la eliminación del trabajador.
- **Ingreso**: Entidad relacionada con Trabajador mediante `belongsToMany` a través de `ingreso_trabajadores`; su existencia bloquea la eliminación del trabajador.
- **Route Model Binding**: Mecanismo de Laravel que resuelve automáticamente una instancia de modelo a partir del parámetro de ruta.
- **Flash Message**: Mensaje de sesión de un solo uso mostrado tras una redirección para informar al usuario del resultado de una operación.

---

## Requirements

### Requirement 1: Listado paginado de trabajadores

**User Story:** Como Administrador, quiero ver la lista de todos los trabajadores con su nombre y estado, para tener una visión general del personal registrado en el sistema.

#### Acceptance Criteria

1. WHEN el Administrador accede a la ruta `GET /trabajadores`, THE TrabajadorController SHALL devolver la vista `trabajadores.index` con los trabajadores paginados de 15 en 15.
2. THE Listado SHALL mostrar las columnas: Nombre, Estado y Acciones para cada trabajador.
3. THE Listado SHALL mostrar el Estado de cada Trabajador como un Badge verde con el texto "Activo" cuando `estado` es `true`, y un Badge rojo con el texto "Inactivo" cuando `estado` es `false`.
4. THE Listado SHALL mostrar un botón "Crear trabajador" en el encabezado que enlaza a la ruta `trabajadores.create`.
5. WHEN no existen trabajadores registrados, THE Listado SHALL mostrar el mensaje "No hay trabajadores registrados." en lugar de la tabla.
6. THE Listado SHALL mostrar los controles de paginación de Laravel debajo de la tabla cuando el total de trabajadores supera 15.

---

### Requirement 2: Creación de trabajador

**User Story:** Como Administrador, quiero crear un nuevo trabajador con nombre y estado, para registrar personal en el sistema.

#### Acceptance Criteria

1. WHEN el Administrador accede a la ruta `GET /trabajadores/create`, THE TrabajadorController SHALL devolver la vista `trabajadores.create` con el formulario vacío.
2. WHEN el Administrador envía el formulario con datos válidos mediante `POST /trabajadores`, THE TrabajadorController SHALL persistir el nuevo Trabajador en la base de datos y redirigir a `trabajadores.index` con el Flash Message de éxito "Trabajador creado correctamente."
3. THE TrabajadorController SHALL validar que el campo `nombre` es requerido, de tipo string y con un máximo de 100 caracteres.
4. THE TrabajadorController SHALL validar que el campo `nombre` es único en la tabla `trabajadores`.
5. THE TrabajadorController SHALL validar que el campo `estado` es de tipo booleano.
6. IF la validación falla, THEN THE TrabajadorController SHALL redirigir de vuelta al formulario conservando los valores introducidos y mostrando los mensajes de error inline junto a cada campo.
7. THE Formulario de creación SHALL incluir un campo de texto para `nombre` y un selector (`<select>`) para `estado` con las opciones "Activo" (valor `1`) e "Inactivo" (valor `0`).
8. THE Formulario de creación SHALL tener el atributo `novalidate` para delegar la validación al servidor.

---

### Requirement 3: Edición de trabajador

**User Story:** Como Administrador, quiero editar el nombre y el estado de un trabajador existente, para mantener los datos del personal actualizados.

#### Acceptance Criteria

1. WHEN el Administrador accede a la ruta `GET /trabajadores/{trabajador}/edit`, THE TrabajadorController SHALL devolver la vista `trabajadores.edit` con los datos actuales del Trabajador precargados en el formulario mediante Route Model Binding.
2. WHEN el Administrador envía el formulario con datos válidos mediante `PUT /trabajadores/{trabajador}`, THE TrabajadorController SHALL actualizar el Trabajador en la base de datos y redirigir a `trabajadores.index` con el Flash Message de éxito "Trabajador actualizado correctamente."
3. THE TrabajadorController SHALL validar que el campo `nombre` es requerido, de tipo string y con un máximo de 100 caracteres.
4. THE TrabajadorController SHALL validar que el campo `nombre` es único en la tabla `trabajadores`, ignorando el registro del Trabajador que se está editando.
5. THE TrabajadorController SHALL validar que el campo `estado` es de tipo booleano.
6. IF la validación falla, THEN THE TrabajadorController SHALL redirigir de vuelta al formulario conservando los valores introducidos y mostrando los mensajes de error inline junto a cada campo.
7. THE Formulario de edición SHALL incluir un campo de texto para `nombre` y un selector (`<select>`) para `estado` con las opciones "Activo" (valor `1`) e "Inactivo" (valor `0`), con la opción correspondiente al estado actual preseleccionada.

---

### Requirement 4: Eliminación protegida de trabajador

**User Story:** Como Administrador, quiero eliminar un trabajador, pero quiero que el sistema me impida hacerlo si el trabajador tiene registros asociados, para preservar la integridad referencial de los datos.

#### Acceptance Criteria

1. WHEN el Administrador envía `DELETE /trabajadores/{trabajador}` y el Trabajador no tiene CambioAceites ni Ingresos asociados, THE TrabajadorController SHALL eliminar el Trabajador de la base de datos y redirigir a `trabajadores.index` con el Flash Message de éxito "Trabajador eliminado correctamente."
2. WHEN el Administrador envía `DELETE /trabajadores/{trabajador}` y el Trabajador tiene al menos un CambioAceite asociado, THE TrabajadorController SHALL cancelar la eliminación y redirigir a `trabajadores.index` con el Flash Message de error "No se puede eliminar el trabajador porque tiene cambios de aceite asociados."
3. WHEN el Administrador envía `DELETE /trabajadores/{trabajador}` y el Trabajador tiene al menos un Ingreso asociado, THE TrabajadorController SHALL cancelar la eliminación y redirigir a `trabajadores.index` con el Flash Message de error "No se puede eliminar el trabajador porque tiene ingresos asociados."
4. THE Listado SHALL mostrar un botón de eliminación por fila que solicita confirmación al usuario antes de enviar la petición `DELETE`.

---

### Requirement 5: Control de acceso y rutas

**User Story:** Como Administrador, quiero que el módulo de trabajadores esté protegido por autenticación y rol, para que solo los administradores puedan gestionarlo.

#### Acceptance Criteria

1. THE Sistema SHALL registrar la ruta resource `Route::resource('trabajadores', TrabajadorController::class)->except(['show'])` dentro del grupo de middleware `['auth', 'role:Administrador']` en `routes/web.php`.
2. WHEN un usuario no autenticado intenta acceder a cualquier ruta del módulo de trabajadores, THE Sistema SHALL redirigirlo a la página de login.
3. WHEN un usuario autenticado sin el rol Administrador intenta acceder a cualquier ruta del módulo de trabajadores, THE Sistema SHALL denegar el acceso con el comportamiento estándar del middleware de roles.

---

### Requirement 6: Consistencia visual con el módulo de usuarios

**User Story:** Como Administrador, quiero que el módulo de trabajadores tenga el mismo aspecto visual que el módulo de usuarios, para mantener una experiencia de usuario coherente en toda la sección de gestión.

#### Acceptance Criteria

1. THE Listado SHALL extender el layout `layouts.app` y aplicar los estilos Tailwind CSS: tabla con `divide-y divide-gray-200`, contenedor con `bg-white rounded-lg border border-gray-200`.
2. THE Formulario SHALL extender el layout `layouts.app` y aplicar el contenedor `bg-white rounded-lg border border-gray-200 p-6 max-w-lg`.
3. THE Formulario SHALL mostrar los errores de validación con las clases `border-red-400 bg-red-50` en el campo afectado y el mensaje con la clase `text-xs text-red-600`.
4. THE Listado SHALL mostrar el botón de editar con clases `bg-gray-100 text-gray-700` y el botón de eliminar con clases `bg-red-100 text-red-700`, siguiendo el patrón de `users.index`.
5. THE Listado y THE Formulario SHALL mostrar Flash Messages de éxito con clases `bg-green-100 text-green-800 border border-green-200` y Flash Messages de error con clases `bg-red-100 text-red-800 border border-red-200`.
6. THE Formulario SHALL incluir un botón "Volver" en el encabezado que enlaza a `trabajadores.index`, con las clases `bg-gray-100 text-gray-700`.
