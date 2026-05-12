# Documento de Requisitos

## Introducción

Esta feature refactoriza el flujo del módulo de cambio de aceite para replicar exactamente el patrón de dos etapas ya implementado en el módulo de ingresos vehicular. El flujo se divide en **registro** y **confirmación**: el operario primero registra los datos del cliente, el trabajador asignado y los productos del cambio (dejando el ticket en estado `pendiente`), y luego, desde una nueva tabla de pendientes, abre el ticket para completar el pago y confirmar el servicio.

La diferencia estructural respecto al módulo de ingresos es que cambio de aceite usa `trabajador_id` (relación N:1 directa), `productos` (N:N con cantidad y precio unitario a través de `cambio_productos`), y un campo `descripcion` opcional, en lugar de `vehiculo_id`, `servicios` y `trabajadores` (N:N).

---

## Glosario

- **Sistema**: La aplicación web del taller mecánico construida en Laravel.
- **CambioAceite**: Registro de un servicio de cambio de aceite, con datos del cliente, trabajador asignado, productos utilizados y, al confirmar, datos de pago.
- **CambioAceite_Pendiente**: CambioAceite en estado `pendiente` — datos del servicio registrados pero pago aún no confirmado.
- **CambioAceite_Confirmado**: CambioAceite en estado `confirmado` — pago completado y proceso cerrado con éxito.
- **Tabla_Pendientes**: Nueva vista principal del módulo que lista todos los `CambioAceite_Pendiente`.
- **Tabla_Confirmados**: Vista que lista todos los `CambioAceite_Confirmado`, accesible desde la `Tabla_Pendientes`.
- **Panel_Confirmacion**: Vista dedicada que se abre desde la `Tabla_Pendientes` al presionar "Abrir ticket". Contiene el formulario completo del servicio más los campos de pago y los botones de acción (confirmar, actualizar, eliminar).
- **Operario**: Usuario autenticado del sistema con permisos para gestionar cambios de aceite.
- **Placa**: Identificador único del vehículo del cliente (máximo 7 caracteres). Campo obligatorio para identificar al cliente.
- **Producto**: Ítem de inventario utilizado en el cambio de aceite, con nombre, precio unitario y stock.
- **CambioProducto**: Registro pivote que vincula un CambioAceite con un Producto, almacenando cantidad, precio unitario y subtotal de línea.
- **Precio**: Suma inalterable de (cantidad × precio_unitario) de todos los productos seleccionados. Se calcula automáticamente en el frontend y se recalcula en el servidor al confirmar.
- **Total**: Precio final del servicio, que puede incluir un descuento sobre el Precio.
- **Descripcion**: Campo de texto opcional para registrar observaciones del cambio de aceite.

---

## Requisitos

### Requisito 1: Registro del Ticket de Cambio de Aceite en Estado Pendiente

**User Story:** Como operario, quiero registrar los datos de un cambio de aceite (cliente, trabajador y productos) sin necesidad de completar el pago en ese momento, para poder atender al cliente rápidamente y confirmar el pago después.

#### Criterios de Aceptación

1. THE Sistema SHALL almacenar un campo `estado` en la tabla `cambio_aceites` con los valores posibles `pendiente` y `confirmado`.
2. WHEN el Operario envía el formulario de registro de cambio de aceite, THE Sistema SHALL crear el CambioAceite con estado `pendiente` y redirigir a la Tabla_Pendientes.
3. THE Sistema SHALL requerir los campos `placa`, `fecha`, `trabajador_id` y al menos un producto para guardar un CambioAceite_Pendiente.
4. THE Sistema SHALL permitir los campos `nombre`, `dni`, `descripcion` y `foto` como opcionales al registrar un CambioAceite_Pendiente.
5. IF el Operario envía el formulario de registro sin los campos requeridos, THEN THE Sistema SHALL mostrar mensajes de error de validación por campo y no crear el ticket.
6. THE Sistema SHALL omitir los campos de pago (`metodo_pago`, `total`, `precio`, `monto_efectivo`, `monto_yape`, `monto_izipay`) del formulario de registro del CambioAceite_Pendiente.
7. WHEN el CambioAceite_Pendiente se crea, THE Sistema SHALL calcular el campo `precio` en el servidor como la suma de (cantidad × precio_unitario) de todos los productos seleccionados y almacenarlo en la tabla `cambio_aceites`.
8. WHEN el CambioAceite_Pendiente se crea, THE Sistema SHALL persistir cada línea de producto en la tabla `cambio_productos` con los campos `cambio_aceite_id`, `producto_id`, `cantidad`, `precio` (precio unitario al momento del registro) y `total` (cantidad × precio).
9. WHEN el CambioAceite_Pendiente se crea, THE Sistema SHALL decrementar el stock de cada producto seleccionado en la cantidad indicada dentro de una transacción de base de datos.

---

### Requisito 2: Tabla de Cambios de Aceite Pendientes (Vista Principal)

**User Story:** Como operario, quiero ver una tabla con todos los cambios de aceite pendientes como vista principal del módulo, para tener visibilidad inmediata de los servicios que están esperando confirmación de pago.

#### Criterios de Aceptación

1. THE Sistema SHALL servir la Tabla_Pendientes en la ruta `/cambio-aceite` como vista principal del módulo.
2. THE Sistema SHALL mostrar en la Tabla_Pendientes únicamente los CambioAceite con estado `pendiente`, ordenados del más reciente al más antiguo.
3. THE Sistema SHALL mostrar en cada fila de la Tabla_Pendientes los campos: fecha, placa del cliente, nombre del cliente (si existe) y trabajador asignado.
4. THE Sistema SHALL mostrar en la parte superior derecha de la Tabla_Pendientes un botón "Listado de cambios culminados" que enlaza a la Tabla_Confirmados.
5. THE Sistema SHALL mostrar en cada fila de la Tabla_Pendientes un botón "Abrir ticket" que lleva al Panel_Confirmacion del CambioAceite correspondiente.
6. THE Sistema SHALL mostrar en la Tabla_Pendientes un botón "Nuevo cambio de aceite" que lleva al formulario de registro.
7. IF no existen CambioAceite con estado `pendiente`, THEN THE Sistema SHALL mostrar un mensaje indicando que no hay cambios de aceite pendientes.

---

### Requisito 3: Panel de Confirmación (Ticket de Cambio de Aceite)

**User Story:** Como operario, quiero abrir el panel de confirmación de un cambio de aceite pendiente para revisar, ajustar y completar el pago antes de confirmar el servicio.

#### Criterios de Aceptación

1. WHEN el Operario presiona "Abrir ticket" en la Tabla_Pendientes, THE Sistema SHALL mostrar el Panel_Confirmacion del CambioAceite_Pendiente seleccionado.
2. THE Panel_Confirmacion SHALL mostrar en la parte superior la placa del vehículo y la lista de productos asignados al ticket.
3. THE Panel_Confirmacion SHALL incluir el formulario completo con los campos `placa`, `nombre`, `dni`, `fecha`, `descripcion`, `foto`, `trabajador_id`, `productos` (con cantidad editable), `precio`, `total`, `metodo_pago` y los campos de pago mixto.
4. THE Panel_Confirmacion SHALL mostrar un botón "Confirmar cambio de aceite" que ejecuta la confirmación del pago y cierra el proceso.
5. THE Panel_Confirmacion SHALL mostrar un botón "Actualizar ticket" que permite guardar cambios (productos, trabajador, descripción) sin confirmar el pago.
6. THE Panel_Confirmacion SHALL mostrar un botón "Eliminar ticket" que cancela y elimina el CambioAceite_Pendiente.
7. IF el Operario intenta acceder al Panel_Confirmacion de un CambioAceite ya confirmado, THEN THE Sistema SHALL redirigir a la Tabla_Confirmados con un mensaje informativo.

---

### Requisito 4: Confirmación del Cambio de Aceite

**User Story:** Como operario, quiero confirmar el pago de un cambio de aceite pendiente desde el panel de confirmación, para cerrar el proceso y registrar el servicio como culminado.

#### Criterios de Aceptación

1. WHEN el Operario presiona "Confirmar cambio de aceite" en el Panel_Confirmacion, THE Sistema SHALL validar que los campos de pago (`total`, `metodo_pago`) estén completos y sean válidos.
2. WHEN la validación de pago es exitosa, THE Sistema SHALL actualizar el estado del CambioAceite a `confirmado` y almacenar los datos de pago (`precio`, `total`, `metodo_pago`, `caja_id`).
3. WHEN el CambioAceite es confirmado, THE Sistema SHALL redirigir al Operario a la Tabla_Pendientes con un mensaje de éxito.
4. IF el Operario intenta confirmar un CambioAceite con `total` igual a cero o negativo, THEN THE Sistema SHALL mostrar un error de validación y no confirmar el ticket.
5. IF el Operario selecciona método de pago `mixto` y la suma de los montos parciales no coincide con el `total`, THEN THE Sistema SHALL mostrar una alerta de advertencia antes de permitir la confirmación.
6. THE Sistema SHALL aceptar los métodos de pago `efectivo`, `yape`, `izipay` y `mixto` al confirmar un CambioAceite.
7. IF no existe una sesión de caja activa al intentar confirmar, THEN THE Sistema SHALL mostrar un modal de advertencia indicando que la caja está cerrada y no confirmar el ticket.
8. WHEN el CambioAceite es confirmado, THE Sistema SHALL sincronizar los productos del ticket con los valores finales de cantidad, precio y total en la tabla `cambio_productos`.

---

### Requisito 5: Actualización del Ticket Pendiente

**User Story:** Como operario, quiero actualizar los datos de un cambio de aceite pendiente desde el panel de confirmación, para corregir o ajustar productos, trabajador y descripción antes de confirmar el pago.

#### Criterios de Aceptación

1. WHEN el Operario presiona "Actualizar ticket" en el Panel_Confirmacion, THE Sistema SHALL guardar los cambios en los campos `placa`, `nombre`, `dni`, `fecha`, `descripcion`, `foto` y `trabajador_id` sin cambiar el estado del CambioAceite.
2. WHEN la actualización es exitosa, THE Sistema SHALL redirigir al Operario de vuelta al Panel_Confirmacion del mismo CambioAceite con un mensaje de éxito.
3. IF el Operario intenta actualizar un ticket sin `trabajador_id`, THEN THE Sistema SHALL mostrar un error de validación y no guardar los cambios.
4. IF el Operario intenta actualizar un ticket sin al menos un producto, THEN THE Sistema SHALL mostrar un error de validación y no guardar los cambios.
5. THE Sistema SHALL permitir añadir y quitar productos del ticket durante la actualización.
6. THE Sistema SHALL permitir cambiar las cantidades de los productos durante la actualización.
7. THE Sistema SHALL recalcular el campo `precio` automáticamente al añadir, quitar o modificar la cantidad de productos durante la actualización.
8. WHEN el Operario actualiza los productos del ticket, THE Sistema SHALL restaurar el stock de los productos anteriores e incrementar el stock de los productos nuevos dentro de una transacción de base de datos.

---

### Requisito 6: Eliminación del Ticket Pendiente

**User Story:** Como operario, quiero eliminar un cambio de aceite pendiente desde el panel de confirmación, para cancelar el proceso cuando el cliente decide no continuar.

#### Criterios de Aceptación

1. WHEN el Operario presiona "Eliminar ticket" en el Panel_Confirmacion, THE Sistema SHALL solicitar confirmación antes de proceder con la eliminación.
2. WHEN el Operario confirma la eliminación, THE Sistema SHALL eliminar el CambioAceite y restaurar el stock de todos los productos asociados dentro de una transacción de base de datos.
3. WHEN la eliminación es exitosa, THE Sistema SHALL redirigir al Operario a la Tabla_Pendientes con un mensaje de éxito.
4. IF el CambioAceite tiene una foto almacenada, THEN THE Sistema SHALL eliminar el archivo de foto del almacenamiento al eliminar el ticket.
5. IF ocurre un error durante la eliminación, THEN THE Sistema SHALL redirigir al Operario al Panel_Confirmacion con un mensaje de error y mantener el ticket sin cambios.

---

### Requisito 7: Tabla de Cambios de Aceite Confirmados (Vista Secundaria)

**User Story:** Como operario, quiero acceder a la lista de cambios de aceite ya confirmados desde la tabla de pendientes, para consultar el historial de servicios culminados.

#### Criterios de Aceptación

1. THE Sistema SHALL servir la Tabla_Confirmados en la ruta `/cambio-aceite/confirmados`.
2. THE Sistema SHALL mostrar en la Tabla_Confirmados únicamente los CambioAceite con estado `confirmado`, ordenados del más reciente al más antiguo.
3. THE Sistema SHALL mantener en la Tabla_Confirmados todas las columnas y acciones que existen actualmente en la vista `cambio-aceite.index` (ver detalle, ticket, editar, eliminar).
4. THE Sistema SHALL mostrar en la Tabla_Confirmados un botón "Volver a pendientes" que enlaza de regreso a la Tabla_Pendientes.
5. THE Sistema SHALL aplicar paginación de 15 registros por página en la Tabla_Confirmados.

---

### Requisito 8: Búsqueda de Productos en el Panel de Confirmación

**User Story:** Como operario, quiero buscar y agregar productos al ticket desde el panel de confirmación, para seleccionar los ítems utilizados en el cambio de aceite de forma rápida.

#### Criterios de Aceptación

1. THE Panel_Confirmacion SHALL mostrar un campo de búsqueda de productos con autocompletado que filtra por nombre.
2. WHEN el Operario escribe en el campo de búsqueda, THE Sistema SHALL consultar la ruta `GET /cambio-aceite/buscar-productos` y mostrar los resultados con nombre, precio unitario y stock disponible.
3. WHEN el Operario selecciona un producto del autocompletado, THE Panel_Confirmacion SHALL agregar el producto a la tabla de productos con cantidad inicial de 1.
4. IF el producto ya está en la tabla, THEN THE Panel_Confirmacion SHALL ignorar la selección duplicada sin agregar una segunda fila.
5. THE Panel_Confirmacion SHALL mostrar en la tabla de productos las columnas: nombre del producto, cantidad (editable), precio unitario y subtotal de línea.
6. WHEN el Operario modifica la cantidad de un producto en la tabla, THE Panel_Confirmacion SHALL recalcular automáticamente el subtotal de esa línea y el campo `precio` total.
7. THE Panel_Confirmacion SHALL mostrar un botón de eliminar por fila que remueve el producto de la tabla y recalcula el `precio` total.

---

### Requisito 9: Migración de Datos Existentes

**User Story:** Como administrador del sistema, quiero que los cambios de aceite ya registrados en la base de datos sean compatibles con el nuevo campo de estado, para que el sistema funcione correctamente sin pérdida de datos.

#### Criterios de Aceptación

1. THE Sistema SHALL agregar el campo `estado` a la tabla `cambio_aceites` mediante una migración de base de datos.
2. THE Sistema SHALL asignar el valor `confirmado` al campo `estado` de todos los cambios de aceite existentes en la base de datos al ejecutar la migración.
3. THE Sistema SHALL definir `pendiente` como el valor por defecto del campo `estado` para nuevos registros.
4. IF la migración falla, THEN THE Sistema SHALL revertir los cambios sin modificar los datos existentes.
