# Requirements Document

## Introduction

Este feature añade un CRUD completo para la entidad **Producto** dentro de la sección de gestión de usuarios del sistema. El módulo permite a los administradores listar, crear, editar y eliminar productos, incluyendo la gestión de imágenes asociadas y la desactivación lógica mediante el campo `activo`. Se añaden dos nuevos campos a la tabla `productos`: `activo` (tinyint, default 1) y `foto` (string, nullable). El diseño visual y los patrones de código siguen exactamente los módulos `trabajadores`, `users` y `roles` ya existentes en la aplicación Laravel + Blade + Tailwind CSS.

## Glossary

- **ProductoController**: Controlador Laravel que gestiona las operaciones CRUD sobre la entidad Producto.
- **Producto**: Entidad del dominio almacenada en la tabla `productos`, con campos `id`, `nombre`, `precio_compra`, `precio_venta`, `stock`, `inventario`, `activo`, `foto` y `timestamps`.
- **Listado**: Vista `productos.index` que muestra todos los productos paginados con miniatura de imagen.
- **Formulario**: Vistas `productos.create` y `productos.edit` que permiten crear y editar productos.
- **Activo**: Campo tinyint del Producto que indica si está activo (`1`) o inactivo (`0`). Valor por defecto: `1`.
- **Foto**: Campo string nullable del Producto que almacena la ruta relativa de la imagen dentro de `storage/app/public/images/productos/`.
- **Badge**: Elemento visual inline que muestra el estado del producto con color verde (activo) o rojo (inactivo).
- **Miniatura**: Imagen reducida mostrada en el Listado para identificar visualmente el producto.
- **Preview en tiempo real**: Visualización inmediata de la imagen nueva seleccionada en el formulario de edición, junto a la imagen anterior, sin necesidad de enviar el formulario.
- **Administrador**: Rol del sistema con acceso exclusivo al módulo de gestión de productos.
- **Route Model Binding**: Mecanismo de Laravel que resuelve automáticamente una instancia de modelo a partir del parámetro de ruta.
- **Flash Message**: Mensaje de sesión de un solo uso mostrado tras una redirección para informar al usuario del resultado de una operación.
- **Storage Link**: Enlace simbólico de Laravel que expone `storage/app/public` como `public/storage`.
- **Migración de alteración**: Migración Laravel que añade columnas a una tabla existente sin recrearla.

---

## Requirements

### Requirement 1: Migración para añadir campos `activo` y `foto`

**User Story:** Como Administrador, quiero que la tabla `productos` tenga los campos `activo` y `foto`, para poder gestionar la activación lógica y las imágenes de los productos.

#### Acceptance Criteria

1. THE Sistema SHALL generar una Migración de alteración que añada la columna `activo` de tipo `tinyInteger` con valor por defecto `1` a la tabla `productos`.
2. THE Sistema SHALL generar una Migración de alteración que añada la columna `foto` de tipo `string` nullable a la tabla `productos`.
3. WHEN la migración se ejecuta con `php artisan migrate`, THE Sistema SHALL añadir ambas columnas a la tabla `productos` sin eliminar ni modificar los registros existentes.
4. WHEN la migración se revierte con `php artisan migrate:rollback`, THE Sistema SHALL eliminar las columnas `activo` y `foto` de la tabla `productos`.

---

### Requirement 2: Actualización del seeder ProductoSeeder

**User Story:** Como Administrador, quiero que el seeder de productos incluya los nuevos campos, para que los datos de prueba sean coherentes con la estructura actual de la tabla.

#### Acceptance Criteria

1. THE ProductoSeeder SHALL incluir el campo `activo` con valor `1` en cada registro creado.
2. THE ProductoSeeder SHALL incluir el campo `foto` con valor `null` en cada registro creado.
3. WHEN el ProductoSeeder se ejecuta, THE Sistema SHALL crear los registros sin errores de integridad de datos.

---

### Requirement 3: Listado paginado de productos

**User Story:** Como Administrador, quiero ver la lista de todos los productos con sus datos principales y miniatura de imagen, para tener una visión general del catálogo registrado en el sistema.

#### Acceptance Criteria

1. WHEN el Administrador accede a la ruta `GET /productos`, THE ProductoController SHALL devolver la vista `productos.index` con los productos paginados de 15 en 15.
2. THE Listado SHALL mostrar las columnas: Foto, Nombre, Precio Compra, Precio Venta, Stock, Activo y Acciones para cada producto.
3. THE Listado SHALL mostrar la Miniatura del producto en la columna Foto cuando el campo `foto` no es nulo; WHEN el campo `foto` es nulo, THE Listado SHALL mostrar un placeholder visual en su lugar.
4. THE Listado SHALL mostrar el campo `activo` de cada Producto como un Badge verde con el texto "Activo" cuando `activo` es `1`, y un Badge rojo con el texto "Inactivo" cuando `activo` es `0`.
5. THE Listado SHALL mostrar un botón "Crear producto" en el encabezado que enlaza a la ruta `productos.create`.
6. WHEN no existen productos registrados, THE Listado SHALL mostrar el mensaje "No hay productos registrados." en lugar de la tabla.
7. THE Listado SHALL mostrar los controles de paginación de Laravel debajo de la tabla cuando el total de productos supera 15.

---

### Requirement 4: Creación de producto

**User Story:** Como Administrador, quiero crear un nuevo producto con todos sus campos incluyendo imagen y estado, para registrar artículos en el catálogo del sistema.

#### Acceptance Criteria

1. WHEN el Administrador accede a la ruta `GET /productos/create`, THE ProductoController SHALL devolver la vista `productos.create` con el formulario vacío.
2. WHEN el Administrador envía el formulario con datos válidos mediante `POST /productos`, THE ProductoController SHALL persistir el nuevo Producto en la base de datos y redirigir a `productos.index` con el Flash Message de éxito "Producto creado correctamente."
3. THE ProductoController SHALL validar que el campo `nombre` es requerido, de tipo string y con un máximo de 150 caracteres.
4. THE ProductoController SHALL validar que el campo `precio_compra` es requerido, de tipo numérico y mayor que cero.
5. THE ProductoController SHALL validar que el campo `precio_venta` es requerido, de tipo numérico y mayor que cero.
6. THE ProductoController SHALL validar que el campo `stock` es requerido, de tipo entero y mayor o igual a cero.
7. THE ProductoController SHALL validar que el campo `inventario` es requerido, de tipo entero y mayor o igual a cero.
8. THE ProductoController SHALL validar que el campo `activo` es de tipo booleano.
9. THE ProductoController SHALL validar que el campo `foto`, cuando está presente, es un archivo de imagen con extensiones permitidas `jpg`, `jpeg`, `png`, `webp` y tamaño máximo de 2 MB.
10. WHEN el formulario incluye una imagen válida, THE ProductoController SHALL almacenar el archivo en `storage/app/public/images/productos/` y guardar la ruta relativa en el campo `foto` del Producto.
11. WHEN el formulario no incluye imagen, THE ProductoController SHALL asignar `null` al campo `foto` del Producto.
12. THE Formulario de creación SHALL asignar el valor por defecto `1` al campo `activo` al renderizar el formulario.
13. IF la validación falla, THEN THE ProductoController SHALL redirigir de vuelta al formulario conservando los valores introducidos y mostrando los mensajes de error inline junto a cada campo.
14. THE Formulario de creación SHALL tener el atributo `enctype="multipart/form-data"` y el atributo `novalidate`.

---

### Requirement 5: Edición de producto

**User Story:** Como Administrador, quiero editar los datos de un producto existente, incluyendo la posibilidad de cambiar o conservar su imagen, para mantener el catálogo actualizado.

#### Acceptance Criteria

1. WHEN el Administrador accede a la ruta `GET /productos/{producto}/edit`, THE ProductoController SHALL devolver la vista `productos.edit` con los datos actuales del Producto precargados en el formulario mediante Route Model Binding.
2. WHEN el Administrador envía el formulario con datos válidos mediante `PUT /productos/{producto}`, THE ProductoController SHALL actualizar el Producto en la base de datos y redirigir a `productos.index` con el Flash Message de éxito "Producto actualizado correctamente."
3. THE ProductoController SHALL aplicar las mismas reglas de validación que en la creación para los campos `nombre`, `precio_compra`, `precio_venta`, `stock`, `inventario`, `activo` y `foto`.
4. WHEN el formulario de edición no incluye una imagen nueva, THE ProductoController SHALL conservar la ruta de imagen anterior en el campo `foto` del Producto sin modificarla.
5. WHEN el formulario de edición incluye una imagen nueva válida, THE ProductoController SHALL almacenar el nuevo archivo en `storage/app/public/images/productos/`, actualizar el campo `foto` del Producto con la nueva ruta y eliminar el archivo de imagen anterior del sistema de archivos si existía.
6. THE Formulario de edición SHALL mostrar la imagen actual del producto en un espacio generoso cuando el campo `foto` no es nulo.
7. WHEN el Administrador selecciona una imagen nueva en el formulario de edición, THE Formulario SHALL mostrar en tiempo real mediante JavaScript: la imagen anterior con la leyenda "Imagen actual" y la imagen nueva con la leyenda "Nueva imagen".
8. WHEN el campo `foto` del Producto es nulo, THE Formulario de edición SHALL mostrar un placeholder en lugar de la imagen actual.
9. IF la validación falla, THEN THE ProductoController SHALL redirigir de vuelta al formulario conservando los valores introducidos y mostrando los mensajes de error inline junto a cada campo.

---

### Requirement 6: Eliminación de producto con borrado de imagen

**User Story:** Como Administrador, quiero eliminar un producto y que su imagen asociada también se elimine del sistema de archivos, para no acumular archivos huérfanos en el servidor.

#### Acceptance Criteria

1. WHEN el Administrador envía `DELETE /productos/{producto}` y el Producto no tiene registros relacionados que bloqueen la eliminación, THE ProductoController SHALL eliminar el Producto de la base de datos y redirigir a `productos.index` con el Flash Message de éxito "Producto eliminado correctamente."
2. WHEN el Producto eliminado tiene un valor no nulo en el campo `foto`, THE ProductoController SHALL eliminar el archivo de imagen correspondiente del sistema de archivos antes de eliminar el registro.
3. WHEN el Producto eliminado tiene el campo `foto` nulo, THE ProductoController SHALL eliminar únicamente el registro de la base de datos sin intentar borrar ningún archivo.
4. THE Listado SHALL mostrar un botón de eliminación por fila que solicita confirmación al usuario antes de enviar la petición `DELETE`.

---

### Requirement 7: Control de acceso y rutas

**User Story:** Como Administrador, quiero que el módulo de productos esté protegido por autenticación y rol, para que solo los administradores puedan gestionarlo.

#### Acceptance Criteria

1. THE Sistema SHALL registrar la ruta resource `Route::resource('productos', ProductoController::class)->except(['show'])` dentro del grupo de middleware `['auth', 'role:Administrador']` en `routes/web.php`.
2. WHEN un usuario no autenticado intenta acceder a cualquier ruta del módulo de productos, THE Sistema SHALL redirigirlo a la página de login.
3. WHEN un usuario autenticado sin el rol Administrador intenta acceder a cualquier ruta del módulo de productos, THE Sistema SHALL denegar el acceso con el comportamiento estándar del middleware de roles.

---

### Requirement 8: Integración en el sidebar y bottom nav

**User Story:** Como Administrador, quiero ver el enlace a Productos en el menú lateral y en la navegación móvil dentro de una sección propia llamada "Gestión de productos", separada de "Gestión de usuarios", para acceder al módulo desde cualquier dispositivo.

#### Acceptance Criteria

1. THE Sistema SHALL añadir un nuevo grupo desplegable independiente llamado "Gestión de productos" en el sidebar de escritorio, separado del grupo "Gestión de usuarios", con el enlace "Productos" dentro de él.
2. THE Sistema SHALL añadir un nuevo grupo desplegable independiente llamado "Gestión de productos" en el bottom nav móvil, separado del grupo "Gestión de usuarios", con el enlace "Productos" dentro de él.
3. THE Sistema SHALL añadir una variable `$productManagementActive` en el layout `layouts.app` que se active cuando la ruta actual coincida con `productos.*`.
4. WHEN el Administrador está en cualquier ruta del módulo de productos, THE Sidebar SHALL mostrar el grupo "Gestión de productos" expandido y el enlace "Productos" resaltado con las clases `bg-gray-100 text-gray-900 font-semibold`.
5. THE grupo "Gestión de usuarios" SHALL permanecer sin cambios y NO SHALL incluir el enlace "Productos".

---

### Requirement 9: Consistencia visual con el módulo de trabajadores

**User Story:** Como Administrador, quiero que el módulo de productos tenga el mismo aspecto visual que el módulo de trabajadores, para mantener una experiencia de usuario coherente en toda la sección de gestión.

#### Acceptance Criteria

1. THE Listado SHALL extender el layout `layouts.app` y aplicar los estilos Tailwind CSS: tabla con `divide-y divide-gray-200`, contenedor con `bg-white rounded-lg border border-gray-200`.
2. THE Formulario SHALL extender el layout `layouts.app` y aplicar el contenedor `bg-white rounded-lg border border-gray-200 p-6`.
3. THE Formulario SHALL mostrar los errores de validación con las clases `border-red-400 bg-red-50` en el campo afectado y el mensaje con la clase `text-xs text-red-600`.
4. THE Listado SHALL mostrar el botón de editar con clases `bg-gray-100 text-gray-700` y el botón de eliminar con clases `bg-red-100 text-red-700`.
5. THE Listado y THE Formulario SHALL mostrar Flash Messages de éxito con clases `bg-green-100 text-green-800 border border-green-200` y Flash Messages de error con clases `bg-red-100 text-red-800 border border-red-200`.
6. THE Formulario SHALL incluir un botón "Volver" en el encabezado que enlaza a `productos.index`, con las clases `bg-gray-100 text-gray-700`.
