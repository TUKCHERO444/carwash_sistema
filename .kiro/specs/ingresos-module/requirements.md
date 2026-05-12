# Requirements Document — ingresos-module

## Introduction

Este feature implementa el módulo completo de **Ingresos** para la aplicación Laravel + Blade + Bootstrap orientada a un taller mecánico. El módulo permite registrar el ingreso de un vehículo al taller: se identifica al cliente por su placa (obligatoria), se toma una foto opcional del vehículo, se asignan uno o varios trabajadores responsables, se seleccionan los servicios a realizar con sus precios, y se calcula el precio final con posibilidad de aplicar descuento.

El módulo involucra las entidades `ingresos` (cabecera), `ingreso_trabajadores` (pivote trabajadores) y `detalle_servicios` (pivote servicios). Las relaciones son: Ingreso → Cliente (N:1), Ingreso → Vehículo (N:1), Ingreso ↔ Trabajadores (N:N), Ingreso ↔ Servicios (N:N). El diseño visual y los patrones de código siguen exactamente los módulos ya implementados (ventas, clientes, vehículos, servicios, trabajadores, productos).

---

## Glossary

- **IngresoController**: Controlador Laravel que gestiona las operaciones del módulo de ingresos (index, create, store, edit, update, destroy, show, ticket).
- **Ingreso**: Entidad cabecera almacenada en la tabla `ingresos`, con campos `id`, `cliente_id`, `vehiculo_id`, `fecha`, `precio`, `total`, `foto`, `user_id` y `timestamps`.
- **DetalleServicio**: Entidad pivote almacenada en la tabla `detalle_servicios`, con campos `id`, `ingreso_id`, `servicio_id` y `timestamps`. Registra los servicios asignados a un ingreso.
- **IngresoTrabajador**: Entidad pivote almacenada en la tabla `ingreso_trabajadores`, con campos `id`, `ingreso_id`, `trabajador_id` y `timestamps`. Registra los trabajadores asignados a un ingreso.
- **Cliente**: Entidad existente en la tabla `clientes`. Los campos `nombre` y `dni` pasan a ser nullable; el campo `placa` es obligatorio y es el identificador principal en el flujo de ingreso.
- **Vehiculo**: Entidad existente en la tabla `vehiculos` con campos `nombre`, `descripcion` (nullable) y `precio` (precio base del vehículo).
- **Servicio**: Entidad existente en la tabla `servicios` con campos `nombre` y `precio`.
- **Trabajador**: Entidad existente en la tabla `trabajadores` con campos `nombre` y `estado`.
- **User**: Entidad existente en la tabla `users` que representa al usuario autenticado que registra el ingreso.
- **Precio base del vehículo**: Valor del campo `precio` del Vehículo asociado al ingreso.
- **Precio**: Campo decimal en `ingresos` que almacena la suma inalterable: precio base del vehículo + suma de precios de todos los servicios asignados.
- **Total**: Campo decimal en `ingresos` que almacena el precio final editable (puede incluir descuento sobre el Precio).
- **Descuento**: Reducción aplicada al Precio, expresada como monto editado manualmente o como porcentaje.
- **Porcentaje de descuento**: Valor numérico entre 0 y 100 que se aplica sobre el Precio para calcular el Total.
- **Foto del vehículo**: Imagen opcional capturada al momento del ingreso, almacenada en `storage/app/public` con `Storage::put()`. El campo `foto` en `ingresos` guarda la ruta relativa.
- **Formulario de ingreso**: Vista `ingresos.create` que permite registrar un ingreso siguiendo el flujo: cliente/placa → foto → trabajadores → servicios → precio/total.
- **Vista de detalle**: Vista `ingresos.show` que muestra todos los datos de un ingreso registrado.
- **Ticket de ingreso**: Vista `ingresos.ticket` imprimible con el formato estándar de orden de trabajo del taller.
- **Flash Message**: Mensaje de sesión de un solo uso mostrado tras una redirección para informar al usuario del resultado de una operación.
- **Route Model Binding**: Mecanismo de Laravel que resuelve automáticamente una instancia de modelo a partir del parámetro de ruta.
- **Usuario autenticado**: Cualquier usuario con sesión activa en el sistema que tiene acceso al módulo de ingresos.

---

## Requirements

### Requirement 1: Ajuste de tabla `clientes` — campos nullable

**User Story:** Como operador del taller, quiero registrar un cliente solo con su placa de vehículo, para agilizar el ingreso cuando no se dispone del nombre o DNI del cliente en el momento.

#### Acceptance Criteria

1. THE Sistema SHALL modificar la tabla `clientes` para que el campo `nombre` sea nullable (permite NULL).
2. THE Sistema SHALL modificar la tabla `clientes` para que el campo `dni` sea nullable (permite NULL) y elimine la restricción UNIQUE sobre `dni` cuando el valor sea NULL.
3. THE Sistema SHALL mantener el campo `placa` como obligatorio (NOT NULL) en la tabla `clientes`.
4. WHEN la migración de ajuste se revierte, THE Sistema SHALL restaurar los campos `nombre` y `dni` a su estado anterior (NOT NULL con UNIQUE en `dni`).

---

### Requirement 2: Ajuste de tabla `ingresos` — campos precio, total y user_id

**User Story:** Como desarrollador, quiero que la tabla `ingresos` tenga los campos necesarios para registrar el precio inalterable, el total con descuento y el usuario que registró el ingreso, para que el sistema pueda calcular y almacenar correctamente los montos del servicio.

#### Acceptance Criteria

1. THE Sistema SHALL añadir el campo `precio` (decimal 10,2, NOT NULL, default 0) a la tabla `ingresos` para almacenar la suma inalterable de precio base del vehículo más servicios.
2. THE Sistema SHALL añadir el campo `total` (decimal 10,2, NOT NULL, default 0) a la tabla `ingresos` para almacenar el precio final editable (con o sin descuento).
3. THE Sistema SHALL añadir el campo `user_id` (foreign key a `users`, NOT NULL) a la tabla `ingresos` para registrar el usuario autenticado que creó el ingreso.
4. THE Sistema SHALL mantener el campo `foto` (string 255, nullable) ya existente en la tabla `ingresos`.
5. THE Sistema SHALL mantener el campo `fecha` (date, NOT NULL) ya existente en la tabla `ingresos`.
6. WHEN la migración de ajuste se revierte, THE Sistema SHALL eliminar los campos `precio`, `total` y `user_id` de la tabla `ingresos` sin errores.

---

### Requirement 3: Modelo Ingreso

**User Story:** Como desarrollador, quiero que el modelo `Ingreso` exponga las relaciones y los casts correctos, para que el controlador pueda operar sobre los datos de forma segura y expresiva.

#### Acceptance Criteria

1. THE Modelo Ingreso SHALL declarar en `$fillable` los campos: `cliente_id`, `vehiculo_id`, `fecha`, `precio`, `total`, `foto`, `user_id`.
2. THE Modelo Ingreso SHALL declarar los casts: `fecha` como `date`, `precio` como `decimal:2`, `total` como `decimal:2`.
3. THE Modelo Ingreso SHALL exponer una relación `belongsTo` hacia el modelo `Cliente` a través del campo `cliente_id`.
4. THE Modelo Ingreso SHALL exponer una relación `belongsTo` hacia el modelo `Vehiculo` a través del campo `vehiculo_id`.
5. THE Modelo Ingreso SHALL exponer una relación `belongsTo` hacia el modelo `User` a través del campo `user_id`.
6. THE Modelo Ingreso SHALL exponer una relación `belongsToMany` hacia el modelo `Trabajador` a través de la tabla `ingreso_trabajadores`, con `withTimestamps()`.
7. THE Modelo Ingreso SHALL exponer una relación `belongsToMany` hacia el modelo `Servicio` a través de la tabla `detalle_servicios`, con `withTimestamps()`.

---

### Requirement 4: Modelo Cliente — campos nullable

**User Story:** Como desarrollador, quiero que el modelo `Cliente` refleje los campos nullable, para que el sistema pueda crear clientes solo con la placa sin requerir nombre ni DNI.

#### Acceptance Criteria

1. THE Modelo Cliente SHALL mantener en `$fillable` los campos: `dni`, `nombre`, `placa`.
2. THE Modelo Cliente SHALL declarar los casts: `dni` como `string` nullable, `nombre` como `string` nullable.
3. THE Modelo Cliente SHALL exponer una relación `hasMany` hacia el modelo `Ingreso`.

---

### Requirement 5: Listado paginado de ingresos

**User Story:** Como operador del taller, quiero ver la lista de todos los ingresos registrados con sus datos principales, para tener una visión general de los vehículos en el taller.

#### Acceptance Criteria

1. WHEN el usuario accede a la ruta `GET /ingresos`, THE IngresoController SHALL devolver la vista `ingresos.index` con los ingresos paginados de 15 en 15, ordenados por fecha de creación descendente.
2. THE Listado SHALL mostrar las columnas: Fecha, Cliente (placa o nombre si existe), Vehículo, Trabajadores asignados, Precio, Total y Acciones para cada ingreso.
3. THE Listado SHALL mostrar un botón "Nuevo ingreso" en el encabezado que enlaza a la ruta `ingresos.create`.
4. WHEN no existen ingresos registrados, THE Listado SHALL mostrar el mensaje "No hay ingresos registrados." en lugar de la tabla.
5. THE Listado SHALL mostrar los controles de paginación de Laravel debajo de la tabla cuando el total de ingresos supera 15.
6. THE Listado SHALL mostrar un botón "Ver detalle" por fila que enlaza a la vista de detalle del ingreso.
7. THE Listado SHALL mostrar un botón "Ticket" por fila que enlaza a la vista de ticket del ingreso.
8. THE Listado SHALL mostrar un botón "Editar" por fila que enlaza a la vista de edición del ingreso.
9. THE Listado SHALL mostrar un botón "Eliminar" por fila que, WHEN el usuario lo pulsa, THE Formulario SHALL mostrar un diálogo de confirmación antes de enviar la solicitud de eliminación.

---

### Requirement 6: Formulario de creación de ingreso — registro de cliente y vehículo

**User Story:** Como operador del taller, quiero registrar un ingreso identificando al cliente por su placa de vehículo, para iniciar el proceso de atención de forma rápida.

#### Acceptance Criteria

1. WHEN el usuario accede a la ruta `GET /ingresos/create`, THE IngresoController SHALL devolver la vista `ingresos.create` con la lista de vehículos disponibles y la lista de clientes existentes.
2. THE Formulario de ingreso SHALL incluir un campo de selección de vehículo (obligatorio) que muestre el nombre y precio base de cada vehículo disponible.
3. THE Formulario de ingreso SHALL incluir un campo de texto para la placa del cliente (obligatorio) con búsqueda o selección del cliente existente por placa.
4. THE Formulario de ingreso SHALL incluir campos opcionales para nombre y DNI del cliente, que se pre-rellenan si el cliente ya existe en la base de datos al ingresar la placa.
5. THE Formulario de ingreso SHALL incluir el campo `fecha` (date, obligatorio) con valor por defecto igual a la fecha actual.
6. WHEN el usuario selecciona un vehículo, THE Formulario SHALL actualizar automáticamente el precio base mostrado en la sección de totales.

---

### Requirement 7: Formulario de creación de ingreso — foto del vehículo

**User Story:** Como operador del taller, quiero tomar o subir una foto opcional del vehículo al momento del ingreso, para documentar el estado del vehículo antes de la intervención.

#### Acceptance Criteria

1. THE Formulario de ingreso SHALL incluir un campo de carga de archivo `foto` (opcional) que acepte imágenes en formato JPEG, PNG o WebP con tamaño máximo de 5 MB.
2. WHEN el usuario selecciona una imagen, THE Formulario SHALL mostrar una vista previa de la imagen seleccionada antes de guardar.
3. WHEN el usuario guarda el ingreso con foto, THE IngresoController SHALL almacenar la imagen en `storage/app/public` usando `Storage::put()` y guardar la ruta relativa en el campo `foto` de la tabla `ingresos`.
4. WHEN el usuario guarda el ingreso sin foto, THE IngresoController SHALL almacenar `NULL` en el campo `foto` de la tabla `ingresos`.
5. IF el archivo subido no es una imagen válida o supera 5 MB, THEN THE IngresoController SHALL rechazar la petición y mostrar el mensaje de error correspondiente.

---

### Requirement 8: Formulario de creación de ingreso — asignación de trabajadores

**User Story:** Como operador del taller, quiero asignar uno o varios trabajadores al ingreso, para registrar quiénes son responsables de atender el vehículo.

#### Acceptance Criteria

1. THE Formulario de ingreso SHALL mostrar la lista de trabajadores activos disponibles para selección múltiple (checkboxes o lista multi-select).
2. THE Formulario de ingreso SHALL requerir que se seleccione al menos un trabajador antes de guardar el ingreso.
3. WHEN el usuario guarda el ingreso, THE IngresoController SHALL persistir los trabajadores seleccionados en la tabla `ingreso_trabajadores` usando la relación `sync()` o `attach()`.
4. IF el usuario no selecciona ningún trabajador, THEN THE IngresoController SHALL rechazar la petición y mostrar el mensaje de error "Debe asignar al menos un trabajador al ingreso."

---

### Requirement 9: Formulario de creación de ingreso — asignación de servicios con cálculo en tiempo real

**User Story:** Como operador del taller, quiero seleccionar los servicios a realizar al vehículo y ver el precio total actualizarse en tiempo real, para conocer el costo total del servicio antes de confirmar el ingreso.

#### Acceptance Criteria

1. THE Formulario de ingreso SHALL mostrar la lista de servicios disponibles con su nombre y precio para selección múltiple (checkboxes).
2. WHEN el usuario marca o desmarca un servicio, THE Formulario SHALL recalcular automáticamente el campo `precio` como: precio base del vehículo seleccionado + suma de precios de los servicios marcados.
3. THE Formulario SHALL mostrar el campo `precio` como valor calculado (solo lectura) que refleja la suma inalterable.
4. THE Formulario SHALL mostrar el campo `total` como campo editable que inicialmente tiene el mismo valor que `precio`.
5. WHEN el usuario edita el campo `total` manualmente, THE Formulario SHALL conservar el valor ingresado como total final del ingreso.
6. THE Formulario SHALL incluir un toggle "Aplicar descuento por porcentaje" que, WHEN está activado, THE Formulario SHALL mostrar un campo numérico para ingresar el porcentaje de descuento.
7. WHEN el usuario ingresa un porcentaje de descuento, THE Formulario SHALL calcular automáticamente el total como `precio * (1 - porcentaje / 100)` y actualizar el campo `total`.
8. THE Formulario SHALL validar en el cliente que el porcentaje de descuento no exceda `100`.
9. IF el porcentaje de descuento excede `100`, THEN THE Formulario SHALL mostrar un mensaje de error inline y restablecer el porcentaje al valor máximo permitido `100`.
10. WHEN no se selecciona ningún servicio, THE Formulario SHALL mostrar el precio base del vehículo como valor de `precio`.

---

### Requirement 10: Confirmación y persistencia del ingreso

**User Story:** Como operador del taller, quiero confirmar el ingreso para que el sistema lo registre en la base de datos con todos sus datos, para mantener el historial de atenciones del taller.

#### Acceptance Criteria

1. WHEN el usuario envía el formulario con datos válidos mediante `POST /ingresos`, THE IngresoController SHALL persistir el Ingreso en la tabla `ingresos` con los campos `cliente_id`, `vehiculo_id`, `fecha`, `precio`, `total`, `foto` (nullable) y `user_id` (usuario autenticado).
2. WHEN el Ingreso se persiste, THE IngresoController SHALL persistir los trabajadores seleccionados en la tabla `ingreso_trabajadores`.
3. WHEN el Ingreso se persiste, THE IngresoController SHALL persistir los servicios seleccionados en la tabla `detalle_servicios`.
4. THE IngresoController SHALL ejecutar la persistencia del Ingreso, los IngresoTrabajador y los DetalleServicio dentro de una transacción de base de datos, de modo que IF cualquier operación falla, THEN THE Sistema SHALL revertir todos los cambios y no dejar datos parciales.
5. WHEN el ingreso se registra correctamente, THE IngresoController SHALL redirigir a `ingresos.show` con el id del ingreso creado y mostrar el Flash Message de éxito "Ingreso registrado correctamente."
6. THE IngresoController SHALL validar que el campo `vehiculo_id` existe en la tabla `vehiculos`.
7. THE IngresoController SHALL validar que el campo `placa` del cliente no está vacío.
8. THE IngresoController SHALL validar que el campo `total` es numérico y mayor que cero.
9. THE IngresoController SHALL validar que se ha seleccionado al menos un trabajador.
10. WHEN el cliente con la placa ingresada ya existe en la base de datos, THE IngresoController SHALL reutilizar el `cliente_id` existente en lugar de crear un duplicado.
11. WHEN el cliente con la placa ingresada no existe en la base de datos, THE IngresoController SHALL crear un nuevo registro en la tabla `clientes` con la placa proporcionada y los campos `nombre` y `dni` opcionales.

---

### Requirement 11: Formulario de edición de ingreso

**User Story:** Como operador del taller, quiero editar un ingreso ya registrado para corregir datos o actualizar el estado del servicio, para mantener la información del taller actualizada.

#### Acceptance Criteria

1. WHEN el usuario accede a la ruta `GET /ingresos/{ingreso}/edit`, THE IngresoController SHALL devolver la vista `ingresos.edit` con todos los datos del Ingreso pre-cargados, incluyendo los trabajadores y servicios asignados.
2. THE Formulario de edición SHALL pre-seleccionar los trabajadores actualmente asignados al ingreso.
3. THE Formulario de edición SHALL pre-seleccionar los servicios actualmente asignados al ingreso.
4. THE Formulario de edición SHALL pre-mostrar la foto actual del vehículo si existe, con opción de reemplazarla.
5. WHEN el usuario envía el formulario de edición mediante `PUT /ingresos/{ingreso}`, THE IngresoController SHALL actualizar el Ingreso y sincronizar los trabajadores y servicios usando `sync()`.
6. WHEN el usuario sube una nueva foto en la edición, THE IngresoController SHALL eliminar la foto anterior del storage y almacenar la nueva.
7. WHEN el usuario guarda la edición sin cambiar la foto, THE IngresoController SHALL mantener la foto existente sin modificaciones.
8. WHEN la edición se guarda correctamente, THE IngresoController SHALL redirigir a `ingresos.show` con el id del ingreso y mostrar el Flash Message de éxito "Ingreso actualizado correctamente."

---

### Requirement 12: Vista de detalle del ingreso

**User Story:** Como operador del taller, quiero ver el detalle completo de un ingreso registrado, para consultar los servicios asignados, los trabajadores responsables y el costo total.

#### Acceptance Criteria

1. WHEN el usuario accede a la ruta `GET /ingresos/{ingreso}`, THE IngresoController SHALL devolver la vista `ingresos.show` con todos los datos del Ingreso cargados mediante Route Model Binding.
2. THE Vista de detalle SHALL mostrar: fecha del ingreso (formato `d/m/Y`), cliente (placa y nombre si existe), vehículo (nombre y precio base), foto del vehículo (si existe), usuario que registró el ingreso.
3. THE Vista de detalle SHALL mostrar la lista de trabajadores asignados al ingreso.
4. THE Vista de detalle SHALL mostrar la tabla de servicios con las columnas: Nombre del servicio y Precio.
5. WHEN el Total del Ingreso es diferente al Precio, THE Vista de detalle SHALL mostrar el precio original (Precio), el total final y la diferencia calculada como "Descuento aplicado: S/ {diferencia}".
6. WHEN el Total del Ingreso es igual al Precio, THE Vista de detalle SHALL mostrar únicamente el total sin sección de descuento.
7. THE Vista de detalle SHALL incluir un botón "Generar ticket" que enlaza a la vista de ticket del ingreso.
8. THE Vista de detalle SHALL incluir un botón "Editar" que enlaza a `ingresos.edit`.
9. THE Vista de detalle SHALL incluir un botón "Volver al listado" que enlaza a `ingresos.index`.

---

### Requirement 13: Ticket / Orden de trabajo

**User Story:** Como operador del taller, quiero generar e imprimir un ticket de ingreso con todos los detalles del servicio, para entregar una orden de trabajo al cliente.

#### Acceptance Criteria

1. WHEN el usuario accede a la ruta `GET /ingresos/{ingreso}/ticket`, THE IngresoController SHALL devolver la vista `ingresos.ticket` con todos los datos del Ingreso cargados mediante Route Model Binding.
2. THE Ticket SHALL mostrar: nombre del taller en el encabezado, fecha del ingreso (formato `d/m/Y`), datos del cliente (placa, nombre si existe, DNI si existe).
3. THE Ticket SHALL mostrar el vehículo con su precio base.
4. THE Ticket SHALL mostrar la lista de trabajadores asignados.
5. THE Ticket SHALL mostrar la tabla de servicios con nombre y precio de cada servicio.
6. THE Ticket SHALL mostrar el precio base del vehículo, la suma de servicios, el precio total (inalterable) y, WHEN existe descuento, SHALL mostrar el monto del descuento y el total final; WHEN no existe descuento, SHALL mostrar únicamente el total.
7. THE Ticket SHALL mostrar la foto del vehículo WHEN el campo `foto` no es nulo.
8. THE Vista de ticket SHALL incluir un botón "Imprimir" que ejecuta `window.print()` para imprimir la página.
9. THE Vista de ticket SHALL aplicar estilos CSS de impresión (`@media print`) que oculten los elementos de navegación y el botón de imprimir, mostrando únicamente el contenido del ticket.
10. THE Vista de ticket SHALL seguir un formato estándar de orden de trabajo: ancho máximo de 400px centrado, tipografía sans-serif, separadores horizontales entre secciones.

---

### Requirement 14: Eliminación de ingreso

**User Story:** Como operador del taller, quiero poder eliminar un ingreso registrado, para corregir errores de registro o cancelar ingresos incorrectos.

#### Acceptance Criteria

1. WHEN el usuario confirma la eliminación de un Ingreso mediante `DELETE /ingresos/{ingreso}`, THE IngresoController SHALL eliminar el Ingreso de la base de datos.
2. THE Sistema SHALL eliminar en cascada los registros de `ingreso_trabajadores` y `detalle_servicios` asociados al Ingreso eliminado (comportamiento garantizado por las restricciones `onDelete('cascade')` en las migraciones).
3. WHEN el Ingreso tiene una foto almacenada, THE IngresoController SHALL eliminar el archivo de foto del storage antes de eliminar el registro.
4. WHEN el Ingreso se elimina correctamente, THE IngresoController SHALL redirigir a `ingresos.index` y mostrar el Flash Message de éxito "Ingreso eliminado correctamente."
5. IF ocurre un error durante la eliminación, THEN THE IngresoController SHALL redirigir al listado y mostrar el Flash Message de error "No se pudo eliminar el ingreso. Intente nuevamente."

---

### Requirement 15: Control de acceso y rutas

**User Story:** Como usuario autenticado, quiero que el módulo de ingresos esté protegido por autenticación, para que solo usuarios con sesión activa puedan registrar y consultar ingresos.

#### Acceptance Criteria

1. THE Sistema SHALL registrar las rutas del módulo de ingresos dentro del grupo de middleware `['auth']` en `routes/web.php`.
2. THE Sistema SHALL registrar las siguientes rutas resource: `GET /ingresos` (index), `GET /ingresos/create` (create), `POST /ingresos` (store), `GET /ingresos/{ingreso}` (show), `GET /ingresos/{ingreso}/edit` (edit), `PUT /ingresos/{ingreso}` (update), `DELETE /ingresos/{ingreso}` (destroy).
3. THE Sistema SHALL registrar la ruta adicional `GET /ingresos/{ingreso}/ticket` (ticket) dentro del mismo grupo de middleware `['auth']`.
4. WHEN un usuario no autenticado intenta acceder a cualquier ruta del módulo de ingresos, THE Sistema SHALL redirigirlo a la página de login.

---

### Requirement 16: Integración en el sidebar y navegación

**User Story:** Como usuario, quiero ver el enlace a Ingresos en el menú lateral y en la navegación móvil, para acceder al módulo desde cualquier dispositivo.

#### Acceptance Criteria

1. THE Sistema SHALL añadir el enlace "Ingresos" en el sidebar de escritorio del layout `layouts.app`.
2. THE Sistema SHALL añadir el enlace "Ingresos" en el bottom nav móvil del layout `layouts.app`.
3. WHEN el usuario está en cualquier ruta del módulo de ingresos, THE Sidebar SHALL mostrar el enlace "Ingresos" resaltado con las clases de estado activo del layout existente.
4. THE Sistema SHALL añadir una variable `$ingresosActive` en el layout `layouts.app` que se active cuando la ruta actual coincida con `ingresos.*`.

---

### Requirement 17: Consistencia visual con los módulos existentes

**User Story:** Como usuario, quiero que el módulo de ingresos tenga el mismo aspecto visual que los módulos de ventas, clientes, vehículos y servicios, para mantener una experiencia de usuario coherente en toda la aplicación.

#### Acceptance Criteria

1. THE Listado de ingresos SHALL extender el layout `layouts.app` y aplicar los mismos estilos de tabla: `divide-y divide-gray-200`, contenedor `bg-white rounded-lg border border-gray-200`.
2. THE Formulario de ingreso SHALL extender el layout `layouts.app` y aplicar el contenedor `bg-white rounded-lg border border-gray-200 p-6`.
3. THE Formulario de ingreso SHALL mostrar los errores de validación con las clases `border-red-400 bg-red-50` en el campo afectado y el mensaje con la clase `text-xs text-red-600`.
4. THE Listado SHALL mostrar el botón "Ver detalle" con clases `bg-gray-100 text-gray-700`, el botón "Ticket" con clases `bg-blue-100 text-blue-700` y el botón "Editar" con clases `bg-gray-100 text-gray-700`.
5. THE Listado y THE Formulario SHALL mostrar Flash Messages de éxito con clases `bg-green-100 text-green-800 border border-green-200` y Flash Messages de error con clases `bg-red-100 text-red-800 border border-red-200`.
6. THE Formulario de ingreso SHALL incluir un botón "Volver" en el encabezado que enlaza a `ingresos.index`, con las clases `bg-gray-100 text-gray-700`.
7. THE Sistema SHALL orientar el diseño principalmente a uso móvil, con campos de formulario de ancho completo y botones de acción accesibles en pantallas pequeñas.
