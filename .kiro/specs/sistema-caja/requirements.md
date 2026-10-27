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

1. WHERE el usuario tiene el permiso `gestionar-caja`, WHEN accede al Panel de Caja sin una caja activa, THE Sistema_Caja SHALL mostrar el botón "Iniciar Caja" habilitado en la parte superior derecha del panel. Los demás roles SHALL ver un mensaje indicando que un administrador debe aperturar la caja.
2. WHEN el usuario presiona el botón "Iniciar Caja", THE Sistema_Caja SHALL mostrar un modal solicitando el monto inicial de caja.
3. THE Sistema_Caja SHALL requerir que el monto inicial sea un valor numérico mayor a cero para confirmar la apertura.
4. WHEN el usuario confirma el monto inicial en el modal, THE Sistema_Caja SHALL crear un registro de caja con estado "abierta", asociado al usuario autenticado y con la fecha y hora de apertura.
5. WHEN existe una caja con estado "abierta", THE Sistema_Caja SHALL deshabilitar el botón "Iniciar Caja" para impedir aperturas duplicadas.
6. THE Sistema_Caja SHALL permitir únicamente una caja con estado "abierta" en simultáneo.

---

### Requisito 3: Panel de Caja — Resumen de Movimientos

**User Story:** Como administrador, quiero ver un resumen visual de los montos de la caja activa, para conocer el estado financiero del turno en tiempo real.

#### Criterios de Aceptación

1. WHERE el usuario tiene el permiso `ver-resumen-caja`, WHILE existe una caja con estado "abierta", THE Sistema_Caja SHALL mostrar las tarjetas "Monto Inicial", "Total Ingresos", "Total Egresos" y el balance neto.
2. THE Sistema_Caja SHALL calcular el "Total Ingresos" como la suma de los campos `total` de todos los registros de Ventas, Cambio_de_Aceite e Ingresos_Vehiculares confirmados asociados a la caja activa.
3. THE Sistema_Caja SHALL calcular el "Total Egresos" como la suma de todos los egresos manuales registrados durante la caja activa.
4. WHILE existe una caja con estado "abierta", THE Sistema_Caja SHALL mostrar el balance neto calculado como: Monto Inicial + Total Ingresos − Total Egresos.

---

### Requisito 4: Detalle de Ingresos por Fuente y Modo de Pago

**User Story:** Como usuario, quiero ver el detalle de cada ingreso agrupado por fuente y modo de pago, para auditar el origen y la forma de cobro de cada transacción.

#### Criterios de Aceptación

1. WHERE el usuario tiene el permiso `ver-resumen-caja`, WHILE existe una caja con estado "abierta", THE Sistema_Caja SHALL mostrar un listado de ingresos detallado debajo de las tarjetas de resumen, agrupado por Fuente de Ingreso (Venta, Cambio_de_Aceite, Ingreso_Vehicular).
2. THE Sistema_Caja SHALL mostrar para cada ingreso: la fuente, el monto total, y el modo de pago utilizado.
3. WHEN el modo de pago de un ingreso es "efectivo", "yape" o "izipay", THE Sistema_Caja SHALL registrar el monto total del ingreso bajo ese modo de pago.
4. WHEN el modo de pago de un ingreso es "mixto", THE Sistema_Caja SHALL distribuir los montos parciales (`monto_efectivo`, `monto_yape`, `monto_izipay`) a sus respectivos modos de pago en el detalle.
5. THE Sistema_Caja SHALL mostrar subtotales por modo de pago (efectivo, yape, izipay) dentro de cada grupo de Fuente de Ingreso.

---

### Requisito 5: Registro de Egresos Manuales

**User Story:** Como usuario, quiero registrar egresos de caja con descripción y tipo de pago, para documentar los retiros de dinero realizados durante el turno.

#### Criterios de Aceptación

1. WHERE el usuario tiene el permiso `registrar-egresos-caja`, WHILE existe una caja con estado "abierta", THE Sistema_Caja SHALL mostrar un botón "Registrar Egreso" en el Panel de Caja. El panel operativo de los demás roles SHALL limitarse a estado, total de ingresos, total de egresos y este botón cuando corresponda.
2. WHEN el usuario presiona el botón "Registrar Egreso", THE Sistema_Caja SHALL mostrar un modal solicitando: monto del egreso, descripción y tipo de pago (yape o efectivo).
3. THE Sistema_Caja SHALL requerir que el monto del egreso sea un valor numérico mayor a cero.
4. THE Sistema_Caja SHALL requerir que la descripción del egreso no esté vacía.
5. THE Sistema_Caja SHALL requerir que el tipo de pago del egreso sea "yape" o "efectivo".
6. WHEN el usuario confirma el egreso, THE Sistema_Caja SHALL guardar el registro de egreso asociado a la caja activa y actualizar el "Total Egresos" en las tarjetas de resumen.
7. WHEN el egreso es registrado, THE Sistema_Caja SHALL añadir el egreso al listado de egresos del panel con su monto, descripción y tipo de pago.

### Requisito 11: Egresos clasificados por categoría de gasto

**User Story:** Como responsable, quiero clasificar cada egreso por categoría de gasto (y trabajador cuando corresponda), para saber en qué se utilizó el dinero sin crear un módulo de gastos.

#### Criterios de Aceptación

1. THE Sistema_Caja SHALL exigir la categoría en todo egreso manual, del catálogo único `EgresoCaja::CATEGORIAS` (insumos, mantenimiento, servicios básicos, transporte/movilidad, pago a trabajador, otros); `compra_abastecimiento` queda reservada al flujo de Compras y se rechaza en el formulario manual.
2. THE Sistema_Caja SHALL permitir asociar un trabajador (opcional) al egreso, sin modificar el módulo de trabajadores.
3. THE recepción de una compra SHALL clasificar su egreso como `compra_abastecimiento` (mismo egreso del flujo, sin duplicar la operación).
4. THE clasificación SHALL ser solo informativa: mismo impacto en saldo, método de pago, usuario, fecha, caja, historial y balance; sin tocar inventario ni Kardex; sin rutas de edición/eliminación de egresos.
5. WHEN se consulta el panel, detalle, historial o reporte de caja, THE Sistema_Caja SHALL mostrar la categoría de cada egreso y el agregado por categoría.
6. THE catálogo SHALL ampliarse editando solo `EgresoCaja::CATEGORIAS` (validación, formularios y reportes lo leen de ahí).

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

1. WHERE el usuario tiene el permiso `gestionar-caja`, WHILE existe una caja con estado "abierta", THE Sistema_Caja SHALL mostrar el botón "Cerrar Caja" habilitado en el Panel de Caja.
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
4. WHERE el usuario tiene el permiso `historial-caja`, THE Sistema_Caja SHALL permitir acceder al historial y al detalle completo de cualquier caja cerrada. Este permiso se asigna únicamente al rol Administrador por defecto.

---

### Requisito 10: Arqueo de caja antes del cierre

**User Story:** Como responsable de caja, quiero contar el efectivo físico y compararlo con el esperado por el sistema antes de cerrar, para dejar registrada cualquier diferencia (sobrante/faltante) con su responsable y observación.

#### Criterios de Aceptación

1. WHEN el usuario abre el modal de cierre, THE Sistema_Caja SHALL mostrar el efectivo esperado calculado por el sistema, un campo para el efectivo contado y la diferencia (contado − esperado) calculada automáticamente, más una observación.
2. THE efectivo esperado SHALL ser `monto_inicial + ingresos en efectivo − egresos en efectivo` (reutiliza `calcularResumen`; Yape/Izipay/transferencia/tarjeta no son físico y se excluyen).
3. THE diferencia SHALL ser `contado − esperado` (positiva = sobrante, negativa = faltante, cero = coincide) y nunca editable manualmente.
4. IF la diferencia es distinta de cero y no hay observación, THEN THE Sistema_Caja SHALL rechazar el cierre hasta registrarla.
5. WHEN el cierre se confirma, THE Sistema_Caja SHALL guardar en la misma transacción: estado `cerrada`, `fecha_cierre`, esperado, contado, diferencia, responsable, fecha de arqueo y observación; el cierre queda auditado con la acción `cerrar caja`.
6. THE Sistema_Caja SHALL impedir cerrar sin arqueo y modificar el arqueo de una caja cerrada.
7. WHEN se consulta el historial/detalle, THE Sistema_Caja SHALL mostrar la sección de arqueo (esperado, contado, diferencia, responsable, fecha/hora, observación) o la nota de régimen anterior si la caja se cerró sin arqueo.

---

### Requisito 12: Separación entre caja general y caja chica operativa

**User Story:** Como administrador, quiero que el resumen general, la apertura, el cierre y el historial queden reservados a mi rol, mientras el personal solo consulta el estado y los totales de la caja activa y registra egresos, para proteger la información financiera sin crear una segunda caja.

#### Criterios de Aceptación

1. THE Sistema_Caja SHALL mantener una única sesión de caja activa compartida; "caja general" y "caja chica" son vistas y permisos sobre la misma caja, no cuentas ni saldos independientes.
2. THE permiso `gestionar-caja` SHALL controlar la apertura y el cierre; se asigna por defecto solo al rol Administrador.
3. THE permiso `ver-resumen-caja` SHALL controlar el monto inicial, el balance, los ingresos por método de pago, los ingresos por origen y el detalle de movimientos; se asigna por defecto solo al rol Administrador.
4. THE permiso `registrar-egresos-caja` SHALL permitir registrar egresos manuales; se asigna por defecto al Administrador y al Vendedor.
5. THE permiso `historial-caja` SHALL controlar el historial y el detalle de cajas cerradas; se asigna por defecto solo al rol Administrador.
6. WHERE el usuario tiene `acceso-caja` pero no `ver-resumen-caja`, THE Sistema_Caja SHALL mostrar únicamente el estado de la caja, el total de ingresos, el total de egresos y, si corresponde, el botón "Registrar Egreso".
7. WHERE el usuario no tiene `ver-resumen-caja`, THE Sistema_Caja SHALL omitir monto inicial, balance, métodos de pago, orígenes, detalle de ingresos y detalle de egresos.
8. WHERE no existe una caja activa y el usuario carece de `gestionar-caja`, THE Sistema_Caja SHALL mostrar un mensaje que indique que un administrador debe aperturar la caja, sin ofrecer el botón "Iniciar Caja".
9. THE controlador SHALL calcular solo los totales mínimos para el panel operativo y reservar la carga de relaciones y el cálculo del resumen completo para `ver-resumen-caja`.

---

### Requisito 13: Advertencia al cerrar la sesión de trabajo con caja abierta

**User Story:** Como responsable de caja, quiero que el sistema me advierta antes de cerrar la pestaña o el navegador mientras la caja sigue abierta, para evitar dejar el turno sin cierre por un cierre accidental.

#### Criterios de Aceptación

1. WHERE el usuario tiene `acceso-caja` y existe una caja con estado "abierta", THE Sistema_Caja SHALL marcar el layout administrativo con `data-caja-abierta="1"` y `data-caja-url` para activar la guardia de salida y ofrecer el retorno a Caja.
2. WHERE el usuario no tiene `acceso-caja` o no existe una caja activa, THE Sistema_Caja SHALL marcar `data-caja-abierta="0"` y no activar la guardia.
3. WHEN el usuario intenta cerrar la pestaña o el navegador, recargar la página o usar atrás/adelante, THE Sistema_Caja SHALL solicitar confirmación mediante `beforeunload`. IF el usuario cancela esa confirmación, THEN THE Sistema_Caja SHALL mostrar un modal no descartable con una única acción "Volver a Caja".
4. THE guardia SHALL permitir sin advertencia la navegación interna del panel, los envíos normales de formularios y el envío del formulario de "Cerrar Caja".
5. WHEN el usuario intenta cerrar sesión con una caja abierta, THE Sistema_Caja SHALL bloquear el envío del formulario de logout y mostrar el modal con la única acción "Volver a Caja"; la sesión SHALL permanecer activa.
6. THE guardia SHALL usar el estado de caja calculado en el servidor al renderizar la página; no se garantiza sincronización inmediata si la caja se abre o cierra en otra pestaña.
7. IF el usuario confirma "Salir" en el diálogo nativo del navegador, THEN la pestaña puede cerrarse y la web no puede impedirlo. En móviles el diálogo puede no mostrarse. La guardia es una advertencia y un retorno forzado dentro de la aplicación, no un bloqueo absoluto del cierre de la pestaña.
