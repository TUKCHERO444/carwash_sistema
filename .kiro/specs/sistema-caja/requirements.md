# Documento de Requisitos — Sistema de Caja

## Introducción

El Sistema de Caja es un módulo de gestión de flujo de efectivo para la aplicación Laravel existente. Permite aperturar y cerrar una sesión de caja diaria, registrar egresos manuales, y consolidar automáticamente los ingresos provenientes de los módulos de Ventas, Cambio de Aceite e Ingresos Vehiculares. El sistema garantiza que ningún proceso de cobro pueda registrarse sin una caja activa, y genera un resumen de cierre con el balance final del día.

---

## Glosario

- **Caja**: Sesión de trabajo diaria que registra el flujo de dinero desde su apertura hasta su cierre.
- **Apertura de Caja**: Acción de iniciar una sesión de caja con un monto inicial declarado.
- **Cierre de Caja**: Acción de finalizar la sesión activa, generando un resumen de balance.
- **Monto Inicial**: Dinero en efectivo declarado al aperturar la caja.
- **Ingreso de Caja**: Monto monetario que entra a la caja, proveniente de Ventas, Cambio de Aceite o Ingresos Vehiculares confirmados.
- **Egreso de Caja**: Retiro de dinero registrado manualmente desde el panel de caja, con descripción y tipo de pago.
- **Panel de Caja**: Vista principal del módulo que muestra el estado actual de la caja y el detalle de movimientos.
- **Fuente de Ingreso**: Módulo origen de un ingreso de caja (Venta, Cambio_de_Aceite, Ingreso_Vehicular).
- **Modo de Pago**: Forma en que se realizó el cobro: efectivo, yape, izipay o mixto.
- **Pago Mixto**: Cobro dividido entre dos o más modos de pago, cuyos montos parciales se distribuyen a cada modo correspondiente.
- **Sistema_Caja**: El módulo de caja descrito en este documento.
- **Módulos_de_Cobro**: Los tres módulos que generan ingresos de caja: Ventas, Cambio_de_Aceite e Ingresos_Vehiculares.
- **Usuario**: Persona autenticada que opera el sistema.

---

## Requisitos

### Requisito 1: Acceso al Panel de Caja desde el Sidebar

**User Story:** Como usuario, quiero acceder al panel de caja desde un enlace directo en el sidebar, para poder gestionar la caja sin navegar por submenús.

#### Criterios de Aceptación

1. THE Sistema_Caja SHALL mostrar un enlace directo "Caja" en el sidebar de navegación, visible para todos los usuarios autenticados.
2. WHEN el usuario hace clic en el enlace "Caja" del sidebar, THE Sistema_Caja SHALL redirigir al usuario al Panel de Caja.
3. WHILE el usuario se encuentra en el Panel de Caja, THE Sistema_Caja SHALL resaltar el enlace "Caja" en el sidebar como activo.

---

### Requisito 2: Apertura de Caja

**User Story:** Como usuario, quiero aperturar la caja ingresando un monto inicial, para registrar el dinero disponible al inicio del turno.

#### Criterios de Aceptación

1. WHEN el usuario accede al Panel de Caja sin una caja activa, THE Sistema_Caja SHALL mostrar el botón "Iniciar Caja" habilitado en la parte superior derecha del panel.
2. WHEN el usuario presiona el botón "Iniciar Caja", THE Sistema_Caja SHALL mostrar un modal solicitando el monto inicial de caja.
3. THE Sistema_Caja SHALL requerir que el monto inicial sea un valor numérico mayor a cero para confirmar la apertura.
4. WHEN el usuario confirma el monto inicial en el modal, THE Sistema_Caja SHALL crear un registro de caja con estado "abierta", asociado al usuario autenticado y con la fecha y hora de apertura.
5. WHEN existe una caja con estado "abierta", THE Sistema_Caja SHALL deshabilitar el botón "Iniciar Caja" para impedir aperturas duplicadas.
6. THE Sistema_Caja SHALL permitir únicamente una caja con estado "abierta" en simultáneo.

---

### Requisito 3: Panel de Caja — Resumen de Movimientos

**User Story:** Como usuario, quiero ver un resumen visual de los montos de la caja activa, para conocer el estado financiero del turno en tiempo real.

#### Criterios de Aceptación

1. WHILE existe una caja con estado "abierta", THE Sistema_Caja SHALL mostrar tres tarjetas de resumen: "Monto Inicial", "Total Ingresos" y "Total Egresos".
2. THE Sistema_Caja SHALL calcular el "Total Ingresos" como la suma de los campos `total` de todos los registros de Ventas, Cambio_de_Aceite e Ingresos_Vehiculares confirmados asociados a la caja activa.
3. THE Sistema_Caja SHALL calcular el "Total Egresos" como la suma de todos los egresos manuales registrados durante la caja activa.
4. WHILE existe una caja con estado "abierta", THE Sistema_Caja SHALL mostrar el balance neto calculado como: Monto Inicial + Total Ingresos − Total Egresos.

---

### Requisito 4: Detalle de Ingresos por Fuente y Modo de Pago

**User Story:** Como usuario, quiero ver el detalle de cada ingreso agrupado por fuente y modo de pago, para auditar el origen y la forma de cobro de cada transacción.

#### Criterios de Aceptación

1. WHILE existe una caja con estado "abierta", THE Sistema_Caja SHALL mostrar un listado de ingresos detallado debajo de las tarjetas de resumen, agrupado por Fuente de Ingreso (Venta, Cambio_de_Aceite, Ingreso_Vehicular).
2. THE Sistema_Caja SHALL mostrar para cada ingreso: la fuente, el monto total, y el modo de pago utilizado.
3. WHEN el modo de pago de un ingreso es "efectivo", "yape" o "izipay", THE Sistema_Caja SHALL registrar el monto total del ingreso bajo ese modo de pago.
4. WHEN el modo de pago de un ingreso es "mixto", THE Sistema_Caja SHALL distribuir los montos parciales (`monto_efectivo`, `monto_yape`, `monto_izipay`) a sus respectivos modos de pago en el detalle.
5. THE Sistema_Caja SHALL mostrar subtotales por modo de pago (efectivo, yape, izipay) dentro de cada grupo de Fuente de Ingreso.

---

### Requisito 5: Registro de Egresos Manuales

**User Story:** Como usuario, quiero registrar egresos de caja con descripción y tipo de pago, para documentar los retiros de dinero realizados durante el turno.

#### Criterios de Aceptación

1. WHILE existe una caja con estado "abierta", THE Sistema_Caja SHALL mostrar un botón "Registrar Egreso" en la parte inferior del Panel de Caja.
2. WHEN el usuario presiona el botón "Registrar Egreso", THE Sistema_Caja SHALL mostrar un modal solicitando: monto del egreso, descripción y tipo de pago (yape o efectivo).
3. THE Sistema_Caja SHALL requerir que el monto del egreso sea un valor numérico mayor a cero.
4. THE Sistema_Caja SHALL requerir que la descripción del egreso no esté vacía.
5. THE Sistema_Caja SHALL requerir que el tipo de pago del egreso sea "yape" o "efectivo".
6. WHEN el usuario confirma el egreso, THE Sistema_Caja SHALL guardar el registro de egreso asociado a la caja activa y actualizar el "Total Egresos" en las tarjetas de resumen.
7. WHEN el egreso es registrado, THE Sistema_Caja SHALL añadir el egreso al listado de egresos del panel con su monto, descripción y tipo de pago.

---

### Requisito 6: Restricción de Registro sin Caja Activa

**User Story:** Como usuario, quiero que el sistema me impida registrar ventas, cambios de aceite o ingresos vehiculares sin una caja abierta, para garantizar que todos los cobros queden asociados a una sesión de caja.

#### Criterios de Aceptación

1. WHEN un usuario intenta guardar un registro en Ventas, Cambio_de_Aceite o Ingresos_Vehiculares y no existe una caja con estado "abierta", THE Sistema_Caja SHALL mostrar un modal informando que se requiere abrir la caja.
2. WHEN el modal de caja requerida es mostrado, THE Sistema_Caja SHALL ofrecer un botón que redirija al usuario al Panel de Caja para aperturarla.
3. IF no existe una caja con estado "abierta" al momento de confirmar el formulario de un Módulo_de_Cobro, THEN THE Sistema_Caja SHALL rechazar el guardado del registro y mostrar el mensaje de caja requerida.

---

### Requisito 7: Cierre de Caja

**User Story:** Como usuario, quiero cerrar la caja al finalizar el turno, para generar un resumen del día y dejar el sistema listo para el siguiente turno.

#### Criterios de Aceptación

1. WHILE existe una caja con estado "abierta", THE Sistema_Caja SHALL mostrar el botón "Cerrar Caja" habilitado en el Panel de Caja.
2. WHEN el usuario presiona el botón "Cerrar Caja", THE Sistema_Caja SHALL mostrar un resumen completo de la sesión antes de confirmar el cierre.
3. THE Sistema_Caja SHALL incluir en el resumen de cierre: monto inicial, listado de ingresos por fuente y modo de pago, listado de egresos con descripción, total de ingresos, total de egresos y balance final (Monto Inicial + Total Ingresos − Total Egresos).
4. WHEN el usuario confirma el cierre, THE Sistema_Caja SHALL actualizar el estado de la caja a "cerrada" y registrar la fecha y hora de cierre.
5. WHEN la caja es cerrada, THE Sistema_Caja SHALL deshabilitar el botón "Cerrar Caja" y habilitar nuevamente el botón "Iniciar Caja" para permitir una nueva apertura.
6. WHEN la caja es cerrada, THE Sistema_Caja SHALL impedir que se registren nuevos ingresos o egresos en esa sesión.

---

### Requisito 8: Asociación Automática de Transacciones a la Caja Activa

**User Story:** Como usuario, quiero que cada venta, cambio de aceite e ingreso vehicular confirmado quede automáticamente asociado a la caja activa, para que el flujo de ingresos sea trazable sin intervención manual.

#### Criterios de Aceptación

1. WHEN se guarda un registro en Ventas, Cambio_de_Aceite o Ingresos_Vehiculares y existe una caja con estado "abierta", THE Sistema_Caja SHALL asociar automáticamente ese registro a la caja activa mediante su identificador.
2. THE Sistema_Caja SHALL utilizar el campo `total` del registro guardado como el monto de ingreso a sumar a la caja.
3. WHEN un registro de Ingreso_Vehicular pasa de estado "pendiente" a "confirmado", THE Sistema_Caja SHALL asociar ese registro a la caja activa en el momento de la confirmación.

---

### Requisito 9: Historial de Cajas Cerradas

**User Story:** Como administrador, quiero consultar el historial de cajas cerradas, para auditar el desempeño financiero de días anteriores.

#### Criterios de Aceptación

1. THE Sistema_Caja SHALL almacenar todos los registros de caja con estado "cerrada" de forma persistente.
2. WHEN un administrador accede al historial de cajas, THE Sistema_Caja SHALL mostrar la lista de cajas cerradas ordenadas por fecha de cierre descendente.
3. THE Sistema_Caja SHALL mostrar para cada caja cerrada: fecha de apertura, fecha de cierre, monto inicial, total de ingresos, total de egresos y balance final.
4. WHERE el usuario tiene el rol "Administrador", THE Sistema_Caja SHALL permitir acceder al detalle completo de cualquier caja cerrada.
