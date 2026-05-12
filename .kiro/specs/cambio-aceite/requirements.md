# Requirements Document — cambio-aceite

## Introduction

Este feature implementa el módulo completo de **Cambio de Aceite** para la aplicación Laravel + Blade + Bootstrap orientada a un taller mecánico. El módulo permite registrar cambios de aceite: se identifica al cliente por su placa (obligatoria, con upsert igual que en ingresos), se asigna un único trabajador responsable, se seleccionan los productos utilizados en el cambio con sus cantidades, y se calcula el precio total en tiempo real con posibilidad de aplicar descuento.

El módulo involucra dos entidades principales: `cambio_aceites` (cabecera del registro) y `cambio_productos` (pivote N:N entre cambio_aceites y productos). Las relaciones son: CambioAceite → Cliente (N:1), CambioAceite → Trabajador (N:1), CambioAceite → User (N:1), CambioAceite ↔ Productos (N:N a través de `cambio_productos`). El diseño visual y los patrones de código siguen exactamente los módulos ya implementados (`ingresos`, `ventas`).

---

## Glossary

- **CambioAceiteController**: Controlador Laravel que gestiona las operaciones del módulo de cambio de aceite (index, create, store, show, edit, update, destroy, ticket).
- **CambioAceite**: Entidad cabecera almacenada en la tabla `cambio_aceites`, con campos `id`, `cliente_id`, `trabajador_id`, `fecha`, `precio`, `total`, `descripcion`, `user_id` y `timestamps`.
- **CambioProducto**: Entidad pivote almacenada en la tabla `cambio_productos`, con campos `id`, `cambio_aceite_id`, `producto_id`, `cantidad`, `precio`, `total` y `timestamps`. Registra los productos utilizados en un cambio de aceite con su precio unitario y subtotal al momento del registro.
- **Cliente**: Entidad existente en la tabla `clientes`. El campo `placa` es obligatorio y es el identificador principal en el flujo de registro. Los campos `nombre` y `dni` son nullable (ya ajustados por el módulo de ingresos).
- **Trabajador**: Entidad existente en la tabla `trabajadores` con campos `nombre` y `estado`. En este módulo se asigna un único trabajador por cambio de aceite.
- **Producto**: Entidad existente en la tabla `productos` con campos `nombre`, `precio_venta` y `stock`.
- **User**: Entidad existente en la tabla `users` que representa al usuario autenticado que registra el cambio de aceite.
- **Precio**: Campo decimal en `cambio_aceites` que almacena la suma inalterable de (cantidad × precio_unitario) de todos los productos seleccionados. No puede ser editado manualmente.
- **Total**: Campo decimal en `cambio_aceites` que almacena el precio final editable (puede incluir descuento sobre el Precio).
- **Descripcion**: Campo text nullable en `cambio_aceites` para registrar observaciones opcionales sobre el cambio de aceite.
- **Precio unitario del producto**: Valor del campo `precio_venta` del Producto al momento del registro del cambio de aceite, almacenado en el campo `precio` de `cambio_productos`.
- **Subtotal de línea**: Resultado de multiplicar `cantidad × precio` en `cambio_productos`, almacenado en el campo `total` de `cambio_productos`.
- **Descuento**: Reducción aplicada al Precio, expresada como monto editado manualmente o como porcentaje.
- **Porcentaje de descuento**: Valor numérico entre 0 y 100 que se aplica sobre el Precio para calcular el Total.
- **Formulario de cambio de aceite**: Vista `cambio-aceite.create` que permite registrar un cambio de aceite siguiendo el flujo: cliente/placa → trabajador → productos → precio/total.
- **Vista de detalle**: Vista `cambio-aceite.show` que muestra todos los datos de un cambio de aceite registrado.
- **Ticket de cambio de aceite**: Vista `cambio-aceite.ticket` imprimible con el formato estándar de orden de trabajo del taller.
- **Flash Message**: Mensaje de sesión de un solo uso mostrado tras una redirección para informar al usuario del resultado de una operación.
- **Route Model Binding**: Mecanismo de Laravel que resuelve automáticamente una instancia de modelo a partir del parámetro de ruta.
- **Usuario autenticado**: Cualquier usuario con sesión activa en el sistema que tiene acceso al módulo de cambio de aceite.
- **Upsert de cliente**: Operación `firstOrCreate` que reutiliza el cliente existente si la placa ya está registrada, o crea uno nuevo si no existe.

---

## Requirements

### Requirement 1: Ajuste de tabla `cambio_aceites` — campos precio, total, descripcion y user_id

**User Story:** Como desarrollador, quiero que la tabla `cambio_aceites` tenga los campos necesarios para registrar el precio inalterable, el total con descuento, la descripción opcional y el usuario que registró el cambio, para que el sistema pueda calcular y almacenar correctamente los montos del servicio.

#### Acceptance Criteria

1. THE Sistema SHALL añadir el campo `precio` (decimal 10,2, NOT NULL, default 0) a la tabla `cambio_aceites` para almacenar la suma inalterable de (cantidad × precio_unitario) de todos los productos seleccionados.
2. THE Sistema SHALL añadir el campo `total` (decimal 10,2, NOT NULL, default 0) a la tabla `cambio_aceites` para almacenar el precio final editable (con o sin descuento).
3. THE Sistema SHALL añadir el campo `descripcion` (text, nullable) a la tabla `cambio_aceites` para registrar observaciones opcionales del cambio de aceite.
4. THE Sistema SHALL añadir el campo `user_id` (foreign key a `users`, NOT NULL) a la tabla `cambio_aceites` para registrar el usuario autenticado que creó el registro.
5. THE Sistema SHALL mantener los campos `cliente_id`, `trabajador_id` y `fecha` ya existentes en la tabla `cambio_aceites` sin modificaciones.
6. WHEN la migración de ajuste se revierte, THE Sistema SHALL eliminar los campos `precio`, `total`, `descripcion` y `user_id` de la tabla `cambio_aceites` sin errores.

---

### Requirement 2: Ajuste de tabla `cambio_productos` — campos precio y total

**User Story:** Como desarrollador, quiero que la tabla `cambio_productos` almacene el precio unitario y el subtotal de cada línea de producto, para que el sistema pueda recalcular totales y mostrar el desglose correcto en el detalle y el ticket.

#### Acceptance Criteria

1. THE Sistema SHALL añadir el campo `precio` (decimal 10,2, NOT NULL) a la tabla `cambio_productos` para almacenar el precio unitario del producto al momento del registro del cambio de aceite.
2. THE Sistema SHALL añadir el campo `total` (decimal 10,2, NOT NULL) a la tabla `cambio_productos` para almacenar el subtotal de la línea calculado como `cantidad × precio`.
3. THE Sistema SHALL mantener los campos `cambio_aceite_id`, `producto_id`, `cantidad` y el índice único `(cambio_aceite_id, producto_id)` ya existentes en la tabla `cambio_productos` sin modificaciones.
4. WHEN la migración de ajuste se revierte, THE Sistema SHALL eliminar los campos `precio` y `total` de la tabla `cambio_productos` sin errores.

---

### Requirement 3: Modelo CambioAceite

**User Story:** Como desarrollador, quiero que el modelo `CambioAceite` exponga las relaciones y los casts correctos, para que el controlador pueda operar sobre los datos de forma segura y expresiva.

#### Acceptance Criteria

1. THE Modelo CambioAceite SHALL declarar en `$fillable` los campos: `cliente_id`, `trabajador_id`, `fecha`, `precio`, `total`, `descripcion`, `user_id`.
2. THE Modelo CambioAceite SHALL declarar los casts: `fecha` como `date`, `precio` como `decimal:2`, `total` como `decimal:2`.
3. THE Modelo CambioAceite SHALL exponer una relación `belongsTo` hacia el modelo `Cliente` a través del campo `cliente_id`.
4. THE Modelo CambioAceite SHALL exponer una relación `belongsTo` hacia el modelo `Trabajador` a través del campo `trabajador_id`.
5. THE Modelo CambioAceite SHALL exponer una relación `belongsTo` hacia el modelo `User` a través del campo `user_id`.
6. THE Modelo CambioAceite SHALL exponer una relación `belongsToMany` hacia el modelo `Producto` a través de la tabla `cambio_productos`, con los pivotes `cantidad`, `precio` y `total`, y con `withTimestamps()`.

---

### Requirement 4: Modelo CambioProducto

**User Story:** Como desarrollador, quiero que el modelo `CambioProducto` exponga los campos y relaciones correctos, para que el sistema pueda acceder al desglose de productos desde cada cambio de aceite.

#### Acceptance Criteria

1. THE Modelo CambioProducto SHALL declarar en `$fillable` los campos: `cambio_aceite_id`, `producto_id`, `cantidad`, `precio`, `total`.
2. THE Modelo CambioProducto SHALL declarar los casts: `cantidad` como `integer`, `precio` como `decimal:2`, `total` como `decimal:2`.
3. THE Modelo CambioProducto SHALL exponer una relación `belongsTo` hacia el modelo `CambioAceite`.
4. THE Modelo CambioProducto SHALL exponer una relación `belongsTo` hacia el modelo `Producto`.

---

### Requirement 5: Listado paginado de cambios de aceite

**User Story:** Como operador del taller, quiero ver la lista de todos los cambios de aceite registrados con sus datos principales, para tener una visión general de los servicios realizados.

#### Acceptance Criteria

1. WHEN el usuario accede a la ruta `GET /cambio-aceite`, THE CambioAceiteController SHALL devolver la vista `cambio-aceite.index` con los registros paginados de 15 en 15, ordenados por fecha de creación descendente.
2. THE Listado SHALL mostrar las columnas: Fecha, Cliente (placa o nombre si existe), Trabajador, Precio, Total y Acciones para cada registro.
3. THE Listado SHALL mostrar un botón "Nuevo cambio de aceite" en el encabezado que enlaza a la ruta `cambio-aceite.create`.
4. WHEN no existen cambios de aceite registrados, THE Listado SHALL mostrar el mensaje "No hay cambios de aceite registrados." en lugar de la tabla.
5. THE Listado SHALL mostrar los controles de paginación de Laravel debajo de la tabla cuando el total de registros supera 15.
6. THE Listado SHALL mostrar un botón "Ver detalle" por fila que enlaza a la vista de detalle del registro.
7. THE Listado SHALL mostrar un botón "Ticket" por fila que enlaza a la vista de ticket del registro.
8. THE Listado SHALL mostrar un botón "Editar" por fila que enlaza a la vista de edición del registro.
9. THE Listado SHALL mostrar un botón "Eliminar" por fila que, WHEN el usuario lo pulsa, THE Formulario SHALL mostrar un diálogo de confirmación antes de enviar la solicitud de eliminación.

---

### Requirement 6: Formulario de creación — registro de cliente por placa

**User Story:** Como operador del taller, quiero registrar un cambio de aceite identificando al cliente por su placa de vehículo, para iniciar el proceso de atención de forma rápida sin requerir nombre ni DNI obligatorios.

#### Acceptance Criteria

1. WHEN el usuario accede a la ruta `GET /cambio-aceite/create`, THE CambioAceiteController SHALL devolver la vista `cambio-aceite.create` con la lista de trabajadores activos y la lista de productos disponibles.
2. THE Formulario de cambio de aceite SHALL incluir un campo de texto para la placa del cliente (obligatorio).
3. THE Formulario de cambio de aceite SHALL incluir campos opcionales para nombre y DNI del cliente, que se pre-rellenan si el cliente ya existe en la base de datos al ingresar la placa.
4. THE Formulario de cambio de aceite SHALL incluir el campo `fecha` (date, obligatorio) con valor por defecto igual a la fecha actual.
5. THE Formulario de cambio de aceite SHALL incluir un campo de texto `descripcion` (opcional) para registrar observaciones del cambio de aceite.

---

### Requirement 7: Formulario de creación — asignación de trabajador

**User Story:** Como operador del taller, quiero asignar un único trabajador responsable al cambio de aceite, para registrar quién realizó el servicio.

#### Acceptance Criteria

1. THE Formulario de cambio de aceite SHALL mostrar un campo de selección (`select`) con la lista de trabajadores activos disponibles.
2. THE Formulario de cambio de aceite SHALL requerir que se seleccione exactamente un trabajador antes de guardar el registro.
3. IF el usuario no selecciona ningún trabajador, THEN THE CambioAceiteController SHALL rechazar la petición y mostrar el mensaje de error "Debe asignar un trabajador al cambio de aceite."

---

### Requirement 8: Formulario de creación — selección de productos con cálculo en tiempo real

**User Story:** Como operador del taller, quiero seleccionar los productos utilizados en el cambio de aceite con sus cantidades y ver el precio total actualizarse en tiempo real, para conocer el costo total del servicio antes de confirmar el registro.

#### Acceptance Criteria

1. THE Formulario de cambio de aceite SHALL mostrar la lista de productos disponibles con su nombre y precio unitario para selección múltiple (checkboxes o lista multi-select con cantidad editable).
2. WHEN el usuario marca o desmarca un producto, THE Formulario SHALL recalcular automáticamente el campo `precio` como la suma de (cantidad × precio_unitario) de todos los productos marcados.
3. WHEN el usuario modifica la cantidad de un producto seleccionado, THE Formulario SHALL recalcular automáticamente el campo `precio`.
4. THE Formulario SHALL mostrar el campo `precio` como valor calculado (solo lectura) que refleja la suma inalterable de los productos seleccionados.
5. THE Formulario SHALL mostrar el campo `total` como campo editable que inicialmente tiene el mismo valor que `precio`.
6. WHEN el usuario edita el campo `total` manualmente, THE Formulario SHALL conservar el valor ingresado como total final del registro.
7. THE Formulario SHALL incluir un toggle "Aplicar descuento por porcentaje" que, WHEN está activado, THE Formulario SHALL mostrar un campo numérico para ingresar el porcentaje de descuento.
8. WHEN el usuario ingresa un porcentaje de descuento, THE Formulario SHALL calcular automáticamente el total como `precio * (1 - porcentaje / 100)` y actualizar el campo `total`.
9. THE Formulario SHALL validar en el cliente que el porcentaje de descuento no exceda `100`.
10. IF el porcentaje de descuento excede `100`, THEN THE Formulario SHALL mostrar un mensaje de error inline y restablecer el porcentaje al valor máximo permitido `100`.
11. WHEN no se selecciona ningún producto, THE Formulario SHALL mostrar `0.00` como valor de `precio`.

---

### Requirement 9: Confirmación y persistencia del cambio de aceite

**User Story:** Como operador del taller, quiero confirmar el cambio de aceite para que el sistema lo registre en la base de datos con todos sus datos, para mantener el historial de servicios del taller.

#### Acceptance Criteria

1. WHEN el usuario envía el formulario con datos válidos mediante `POST /cambio-aceite`, THE CambioAceiteController SHALL persistir el CambioAceite en la tabla `cambio_aceites` con los campos `cliente_id`, `trabajador_id`, `fecha`, `precio`, `total`, `descripcion` (nullable) y `user_id` (usuario autenticado).
2. WHEN el CambioAceite se persiste, THE CambioAceiteController SHALL persistir cada línea de producto en la tabla `cambio_productos` con los campos `cambio_aceite_id`, `producto_id`, `cantidad`, `precio` (precio unitario al momento del registro) y `total` (cantidad × precio).
3. THE CambioAceiteController SHALL ejecutar la persistencia del CambioAceite y los CambioProducto dentro de una transacción de base de datos, de modo que IF cualquier operación falla, THEN THE Sistema SHALL revertir todos los cambios y no dejar datos parciales.
4. WHEN el cambio de aceite se registra correctamente, THE CambioAceiteController SHALL redirigir a `cambio-aceite.show` con el id del registro creado y mostrar el Flash Message de éxito "Cambio de aceite registrado correctamente."
5. THE CambioAceiteController SHALL validar que el campo `placa` del cliente no está vacío.
6. THE CambioAceiteController SHALL validar que el campo `trabajador_id` existe en la tabla `trabajadores`.
7. THE CambioAceiteController SHALL validar que el campo `total` es numérico y mayor que cero.
8. THE CambioAceiteController SHALL validar que se ha seleccionado al menos un producto.
9. WHEN el cliente con la placa ingresada ya existe en la base de datos, THE CambioAceiteController SHALL reutilizar el `cliente_id` existente en lugar de crear un duplicado.
10. WHEN el cliente con la placa ingresada no existe en la base de datos, THE CambioAceiteController SHALL crear un nuevo registro en la tabla `clientes` con la placa proporcionada y los campos `nombre` y `dni` opcionales.

---

### Requirement 10: Formulario de edición del cambio de aceite

**User Story:** Como operador del taller, quiero editar un cambio de aceite ya registrado para corregir datos o actualizar la información del servicio, para mantener el historial del taller actualizado.

#### Acceptance Criteria

1. WHEN el usuario accede a la ruta `GET /cambio-aceite/{cambioAceite}/edit`, THE CambioAceiteController SHALL devolver la vista `cambio-aceite.edit` con todos los datos del CambioAceite pre-cargados, incluyendo los productos asignados con sus cantidades.
2. THE Formulario de edición SHALL pre-seleccionar el trabajador actualmente asignado al registro.
3. THE Formulario de edición SHALL pre-seleccionar los productos actualmente asignados con sus cantidades y precios.
4. WHEN el usuario envía el formulario de edición mediante `PUT /cambio-aceite/{cambioAceite}`, THE CambioAceiteController SHALL actualizar el CambioAceite y sincronizar los productos usando `sync()` con los nuevos valores de `cantidad`, `precio` y `total` por línea.
5. WHEN la edición se guarda correctamente, THE CambioAceiteController SHALL redirigir a `cambio-aceite.show` con el id del registro y mostrar el Flash Message de éxito "Cambio de aceite actualizado correctamente."

---

### Requirement 11: Vista de detalle del cambio de aceite

**User Story:** Como operador del taller, quiero ver el detalle completo de un cambio de aceite registrado, para consultar los productos utilizados, el trabajador responsable y el costo total.

#### Acceptance Criteria

1. WHEN el usuario accede a la ruta `GET /cambio-aceite/{cambioAceite}`, THE CambioAceiteController SHALL devolver la vista `cambio-aceite.show` con todos los datos del CambioAceite cargados mediante Route Model Binding.
2. THE Vista de detalle SHALL mostrar: fecha del registro (formato `d/m/Y`), cliente (placa y nombre si existe), trabajador asignado, descripción (si existe), usuario que registró el cambio de aceite.
3. THE Vista de detalle SHALL mostrar la tabla de productos con las columnas: Nombre del producto, Cantidad, Precio unitario y Subtotal por línea.
4. WHEN el Total del CambioAceite es diferente al Precio, THE Vista de detalle SHALL mostrar el precio original (Precio), el total final y la diferencia calculada como "Descuento aplicado: S/ {diferencia}".
5. WHEN el Total del CambioAceite es igual al Precio, THE Vista de detalle SHALL mostrar únicamente el total sin sección de descuento.
6. THE Vista de detalle SHALL incluir un botón "Generar ticket" que enlaza a la vista de ticket del registro.
7. THE Vista de detalle SHALL incluir un botón "Editar" que enlaza a `cambio-aceite.edit`.
8. THE Vista de detalle SHALL incluir un botón "Volver al listado" que enlaza a `cambio-aceite.index`.

---

### Requirement 12: Ticket / Comprobante imprimible

**User Story:** Como operador del taller, quiero generar e imprimir un ticket del cambio de aceite con todos los detalles del servicio, para entregar un comprobante al cliente.

#### Acceptance Criteria

1. WHEN el usuario accede a la ruta `GET /cambio-aceite/{cambioAceite}/ticket`, THE CambioAceiteController SHALL devolver la vista `cambio-aceite.ticket` con todos los datos del CambioAceite cargados mediante Route Model Binding.
2. THE Ticket SHALL mostrar: nombre del taller en el encabezado, fecha del registro (formato `d/m/Y`), datos del cliente (placa, nombre si existe, DNI si existe).
3. THE Ticket SHALL mostrar el trabajador responsable del cambio de aceite.
4. THE Ticket SHALL mostrar la tabla de productos con las columnas: Nombre del producto, Cantidad, Precio unitario y Subtotal por línea.
5. THE Ticket SHALL mostrar el precio total (inalterable) y, WHEN existe descuento, SHALL mostrar el monto del descuento y el total final; WHEN no existe descuento, SHALL mostrar únicamente el total.
6. THE Ticket SHALL mostrar la descripción del cambio de aceite WHEN el campo `descripcion` no es nulo.
7. THE Vista de ticket SHALL incluir un botón "Imprimir" que ejecuta `window.print()` para imprimir la página.
8. THE Vista de ticket SHALL aplicar estilos CSS de impresión (`@media print`) que oculten los elementos de navegación y el botón de imprimir, mostrando únicamente el contenido del ticket.
9. THE Vista de ticket SHALL seguir un formato estándar de orden de trabajo: ancho máximo de 400px centrado, tipografía sans-serif, separadores horizontales entre secciones.

---

### Requirement 13: Eliminación del cambio de aceite

**User Story:** Como operador del taller, quiero poder eliminar un cambio de aceite registrado, para corregir errores de registro o cancelar registros incorrectos.

#### Acceptance Criteria

1. WHEN el usuario confirma la eliminación de un CambioAceite mediante `DELETE /cambio-aceite/{cambioAceite}`, THE CambioAceiteController SHALL eliminar el CambioAceite de la base de datos.
2. THE Sistema SHALL eliminar en cascada los registros de `cambio_productos` asociados al CambioAceite eliminado (comportamiento garantizado por la restricción `onDelete('cascade')` en la migración).
3. WHEN el CambioAceite se elimina correctamente, THE CambioAceiteController SHALL redirigir a `cambio-aceite.index` y mostrar el Flash Message de éxito "Cambio de aceite eliminado correctamente."
4. IF ocurre un error durante la eliminación, THEN THE CambioAceiteController SHALL redirigir al listado y mostrar el Flash Message de error "No se pudo eliminar el cambio de aceite. Intente nuevamente."

---

### Requirement 14: Control de acceso y rutas

**User Story:** Como usuario autenticado, quiero que el módulo de cambio de aceite esté protegido por autenticación, para que solo usuarios con sesión activa puedan registrar y consultar cambios de aceite.

#### Acceptance Criteria

1. THE Sistema SHALL registrar las rutas del módulo de cambio de aceite dentro del grupo de middleware `['auth']` en `routes/web.php`.
2. THE Sistema SHALL registrar las siguientes rutas resource: `GET /cambio-aceite` (index), `GET /cambio-aceite/create` (create), `POST /cambio-aceite` (store), `GET /cambio-aceite/{cambioAceite}` (show), `GET /cambio-aceite/{cambioAceite}/edit` (edit), `PUT /cambio-aceite/{cambioAceite}` (update), `DELETE /cambio-aceite/{cambioAceite}` (destroy).
3. THE Sistema SHALL registrar la ruta adicional `GET /cambio-aceite/{cambioAceite}/ticket` (ticket) dentro del mismo grupo de middleware `['auth']`.
4. WHEN un usuario no autenticado intenta acceder a cualquier ruta del módulo de cambio de aceite, THE Sistema SHALL redirigirlo a la página de login.

---

### Requirement 15: Integración en el sidebar y navegación móvil

**User Story:** Como usuario, quiero ver el enlace a Cambio de Aceite en el menú lateral y en la navegación móvil, para acceder al módulo desde cualquier dispositivo.

#### Acceptance Criteria

1. THE Sistema SHALL añadir el enlace "Cambio de Aceite" en el sidebar de escritorio del layout `layouts.app`.
2. THE Sistema SHALL añadir el enlace "Cambio de Aceite" en el bottom nav móvil del layout `layouts.app`.
3. WHEN el usuario está en cualquier ruta del módulo de cambio de aceite, THE Sidebar SHALL mostrar el enlace "Cambio de Aceite" resaltado con las clases de estado activo del layout existente.
4. THE Sistema SHALL añadir una variable `$cambioAceiteActive` en el layout `layouts.app` que se active cuando la ruta actual coincida con `cambio-aceite.*`.

---

### Requirement 16: Consistencia visual con los módulos existentes

**User Story:** Como usuario, quiero que el módulo de cambio de aceite tenga el mismo aspecto visual que los módulos de ingresos y ventas, para mantener una experiencia de usuario coherente en toda la aplicación.

#### Acceptance Criteria

1. THE Listado de cambios de aceite SHALL extender el layout `layouts.app` y aplicar los mismos estilos de tabla que los módulos de ingresos y ventas.
2. THE Formulario de cambio de aceite SHALL extender el layout `layouts.app` y aplicar el contenedor `bg-white rounded-lg border border-gray-200 p-6`.
3. THE Formulario de cambio de aceite SHALL mostrar los errores de validación con las clases `border-red-400 bg-red-50` en el campo afectado y el mensaje con la clase `text-xs text-red-600`.
4. THE Listado SHALL mostrar el botón "Ver detalle" con clases `bg-gray-100 text-gray-700`, el botón "Ticket" con clases `bg-blue-100 text-blue-700` y el botón "Editar" con clases `bg-gray-100 text-gray-700`.
5. THE Listado y THE Formulario SHALL mostrar Flash Messages de éxito con clases `bg-green-100 text-green-800 border border-green-200` y Flash Messages de error con clases `bg-red-100 text-red-800 border border-red-200`.
6. THE Formulario de cambio de aceite SHALL incluir un botón "Volver" en el encabezado que enlaza a `cambio-aceite.index`, con las clases `bg-gray-100 text-gray-700`.
7. THE Sistema SHALL orientar el diseño principalmente a uso móvil, con campos de formulario de ancho completo y botones de acción accesibles en pantallas pequeñas.
