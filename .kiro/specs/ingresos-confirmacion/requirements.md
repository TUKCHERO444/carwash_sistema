# Documento de Requisitos

## Introducción

Esta feature divide el flujo de registro de ingresos del taller mecánico en dos etapas separadas: **registro** y **confirmación**. Actualmente, el formulario de creación de un ingreso exige completar datos de pago en el mismo paso. Con este cambio, el operario primero registra los datos del vehículo, cliente, trabajadores y servicios (dejando el ingreso en estado `pendiente`), y luego, desde una nueva tabla de pendientes, abre el ticket de ingreso para completar el flujo de pago y confirmar el ingreso. La tabla de ingresos confirmados (la vista actual) se mueve a una ruta secundaria y se accede desde un botón en la nueva vista principal.

---

## Glosario

- **Sistema**: La aplicación web del taller mecánico construida en Laravel.
- **Ingreso**: Registro de la entrada de un vehículo al taller, con sus datos de cliente, trabajadores asignados y servicios a realizar.
- **Ingreso_Pendiente**: Ingreso en estado `pendiente` — datos registrados pero pago aún no confirmado.
- **Ingreso_Confirmado**: Ingreso en estado `confirmado` — pago completado y proceso cerrado con éxito.
- **Tabla_Pendientes**: Nueva vista principal que lista todos los `Ingreso_Pendiente`.
- **Tabla_Confirmados**: Vista existente (actualmente `ingresos.index`) que lista todos los `Ingreso_Confirmado`, accesible desde la `Tabla_Pendientes`.
- **Panel_Confirmacion**: Panel de confirmación de pago que se abre desde la `Tabla_Pendientes` al presionar "Abrir ticket de ingreso". Contiene el flujo de pago existente más las acciones de eliminar, actualizar y confirmar.
- **Operario**: Usuario autenticado del sistema con permisos para gestionar ingresos.
- **Placa**: Identificador único del vehículo del cliente (máximo 7 caracteres).

---

## Requisitos

### Requisito 1: Registro de Ingreso en Estado Pendiente

**User Story:** Como operario, quiero registrar los datos de un ingreso (vehículo, cliente, trabajadores y servicios) sin necesidad de completar el pago en ese momento, para poder atender al cliente rápidamente y confirmar el pago después.

#### Criterios de Aceptación

1. THE Sistema SHALL almacenar un campo `estado` en la tabla `ingresos` con los valores posibles `pendiente` y `confirmado`.
2. WHEN el Operario envía el formulario de registro de ingreso, THE Sistema SHALL crear el Ingreso con estado `pendiente` y redirigir a la Tabla_Pendientes.
3. THE Sistema SHALL requerir los campos `vehiculo_id`, `placa`, `fecha` y al menos un `trabajador_id` para guardar un Ingreso_Pendiente.
4. THE Sistema SHALL permitir los campos `nombre`, `dni`, `foto` y `servicios` como opcionales al registrar un Ingreso_Pendiente.
5. IF el Operario envía el formulario de registro sin los campos requeridos, THEN THE Sistema SHALL mostrar mensajes de error de validación por campo y no crear el ingreso.
6. THE Sistema SHALL omitir los campos de pago (`metodo_pago`, `total`, `precio`, `monto_efectivo`, `monto_yape`, `monto_izipay`) del formulario de registro de Ingreso_Pendiente.

---

### Requisito 2: Tabla de Ingresos Pendientes (Vista Principal)

**User Story:** Como operario, quiero ver una tabla con todos los ingresos pendientes como vista principal, para tener visibilidad inmediata de los vehículos que están esperando confirmación de pago.

#### Criterios de Aceptación

1. THE Sistema SHALL servir la Tabla_Pendientes en la ruta `/ingresos` como vista principal del módulo de ingresos.
2. THE Sistema SHALL mostrar en la Tabla_Pendientes únicamente los ingresos con estado `pendiente`, ordenados del más reciente al más antiguo.
3. THE Sistema SHALL mostrar en cada fila de la Tabla_Pendientes los campos: fecha, placa del cliente, nombre del cliente (si existe), tipo de vehículo y trabajadores asignados.
4. THE Sistema SHALL mostrar en la parte superior derecha de la Tabla_Pendientes un botón "Listado de ingresos culminados" que enlaza a la Tabla_Confirmados.
5. THE Sistema SHALL mostrar en cada fila de la Tabla_Pendientes un botón "Abrir ticket de ingreso" que lleva al Panel_Confirmacion del ingreso correspondiente.
6. THE Sistema SHALL mostrar en la Tabla_Pendientes un botón "Nuevo ingreso" que lleva al formulario de registro.
7. IF no existen ingresos con estado `pendiente`, THEN THE Sistema SHALL mostrar un mensaje indicando que no hay ingresos pendientes.

---

### Requisito 3: Panel de Confirmación (Ticket de Ingreso)

**User Story:** Como operario, quiero abrir el panel de confirmación de un ingreso pendiente para revisar, ajustar y completar el pago antes de confirmar el ingreso.

#### Criterios de Aceptación

1. WHEN el Operario presiona "Abrir ticket de ingreso" en la Tabla_Pendientes, THE Sistema SHALL mostrar el Panel_Confirmacion del Ingreso_Pendiente seleccionado.
2. THE Panel_Confirmacion SHALL mostrar en la parte superior la placa del vehículo y la lista de servicios asignados al ingreso.
3. THE Panel_Confirmacion SHALL incluir el flujo de pago existente con los campos `vehiculo_id`, `placa`, `nombre`, `dni`, `fecha`, `foto`, `trabajador_id`, `servicios`, `precio`, `total`, `metodo_pago` y los campos de pago mixto.
4. THE Panel_Confirmacion SHALL mostrar un botón "Confirmar ingreso" que ejecuta la confirmación del pago y cierra el proceso.
5. THE Panel_Confirmacion SHALL mostrar un botón "Actualizar ingreso" que permite guardar cambios (servicios, trabajador, precios) sin confirmar el pago.
6. THE Panel_Confirmacion SHALL mostrar un botón "Eliminar ingreso" que cancela y elimina el ingreso pendiente.

---

### Requisito 4: Confirmación del Ingreso

**User Story:** Como operario, quiero confirmar el pago de un ingreso pendiente desde el panel de confirmación, para cerrar el proceso y registrar el ingreso como culminado.

#### Criterios de Aceptación

1. WHEN el Operario presiona "Confirmar ingreso" en el Panel_Confirmacion, THE Sistema SHALL validar que los campos de pago (`total`, `metodo_pago`) estén completos y sean válidos.
2. WHEN la validación de pago es exitosa, THE Sistema SHALL actualizar el estado del ingreso a `confirmado` y almacenar los datos de pago.
3. WHEN el ingreso es confirmado, THE Sistema SHALL redirigir al Operario a la Tabla_Pendientes con un mensaje de éxito.
4. IF el Operario intenta confirmar un ingreso con `total` igual a cero o negativo, THEN THE Sistema SHALL mostrar un error de validación y no confirmar el ingreso.
5. IF el Operario selecciona método de pago `mixto` y la suma de los montos parciales no coincide con el `total`, THEN THE Sistema SHALL mostrar una alerta de advertencia antes de permitir la confirmación.
6. THE Sistema SHALL aceptar los métodos de pago `efectivo`, `yape`, `izipay` y `mixto` al confirmar un ingreso.

---

### Requisito 5: Actualización del Ingreso Pendiente

**User Story:** Como operario, quiero actualizar los datos de un ingreso pendiente desde el panel de confirmación, para corregir o ajustar servicios, trabajador y precios antes de confirmar el pago.

#### Criterios de Aceptación

1. WHEN el Operario presiona "Actualizar ingreso" en el Panel_Confirmacion, THE Sistema SHALL guardar los cambios en los campos `vehiculo_id`, `placa`, `nombre`, `dni`, `fecha`, `foto`, `trabajador_id` y `servicios` sin cambiar el estado del ingreso.
2. WHEN la actualización es exitosa, THE Sistema SHALL redirigir al Operario de vuelta al Panel_Confirmacion del mismo ingreso con un mensaje de éxito.
3. IF el Operario intenta actualizar un ingreso sin `trabajador_id`, THEN THE Sistema SHALL mostrar un error de validación y no guardar los cambios.
4. THE Sistema SHALL permitir añadir y quitar servicios del ingreso durante la actualización.
5. THE Sistema SHALL permitir cambiar el trabajador asignado durante la actualización.
6. THE Sistema SHALL recalcular el campo `precio` automáticamente al añadir o quitar servicios durante la actualización.

---

### Requisito 6: Eliminación del Ingreso Pendiente

**User Story:** Como operario, quiero eliminar un ingreso pendiente desde el panel de confirmación, para cancelar el proceso cuando el cliente decide no continuar.

#### Criterios de Aceptación

1. WHEN el Operario presiona "Eliminar ingreso" en el Panel_Confirmacion, THE Sistema SHALL solicitar confirmación antes de proceder con la eliminación.
2. WHEN el Operario confirma la eliminación, THE Sistema SHALL eliminar el ingreso y sus registros relacionados (`ingreso_trabajadores`, `detalle_servicios`) de la base de datos.
3. WHEN la eliminación es exitosa, THE Sistema SHALL redirigir al Operario a la Tabla_Pendientes con un mensaje de éxito.
4. IF el ingreso tiene una foto almacenada, THEN THE Sistema SHALL eliminar el archivo de foto del almacenamiento al eliminar el ingreso.
5. IF ocurre un error durante la eliminación, THEN THE Sistema SHALL redirigir al Operario al Panel_Confirmacion con un mensaje de error y mantener el ingreso sin cambios.

---

### Requisito 7: Tabla de Ingresos Confirmados (Vista Secundaria)

**User Story:** Como operario, quiero acceder a la lista de ingresos ya confirmados desde la tabla de pendientes, para consultar el historial de ingresos culminados.

#### Criterios de Aceptación

1. THE Sistema SHALL servir la Tabla_Confirmados en una ruta separada (por ejemplo `/ingresos/confirmados`).
2. THE Sistema SHALL mostrar en la Tabla_Confirmados únicamente los ingresos con estado `confirmado`, ordenados del más reciente al más antiguo.
3. THE Sistema SHALL mantener en la Tabla_Confirmados todas las columnas y acciones que existen actualmente en la vista `ingresos.index` (ver detalle, ticket, editar, eliminar).
4. THE Sistema SHALL mostrar en la Tabla_Confirmados un botón "Volver a pendientes" que enlaza de regreso a la Tabla_Pendientes.
5. THE Sistema SHALL aplicar paginación de 15 registros por página en la Tabla_Confirmados.

---

### Requisito 8: Migración de Datos Existentes

**User Story:** Como administrador del sistema, quiero que los ingresos ya registrados en la base de datos sean compatibles con el nuevo campo de estado, para que el sistema funcione correctamente sin pérdida de datos.

#### Criterios de Aceptación

1. THE Sistema SHALL agregar el campo `estado` a la tabla `ingresos` mediante una migración de base de datos.
2. THE Sistema SHALL asignar el valor `confirmado` al campo `estado` de todos los ingresos existentes en la base de datos al ejecutar la migración.
3. THE Sistema SHALL definir `pendiente` como el valor por defecto del campo `estado` para nuevos registros.
4. IF la migración falla, THEN THE Sistema SHALL revertir los cambios sin modificar los datos existentes.
