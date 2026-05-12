# Requirements Document — ventas-module

## Introduction

Este feature implementa el módulo completo de **Ventas** para la aplicación Laravel + Blade + Tailwind CSS. El módulo permite registrar ventas asociando uno o varios productos mediante un formulario interactivo con búsqueda dinámica Ajax, aplicación de descuentos, reducción automática de stock al confirmar la venta, visualización del detalle de cada venta y generación de un ticket/nota de venta imprimible.

El módulo involucra dos entidades principales: `ventas` (cabecera de la venta) y `detalle_ventas` (líneas de producto). La relación entre `ventas` y `productos` es N:N a través de `detalle_ventas`. El diseño visual y los patrones de código siguen exactamente los módulos `productos`, `trabajadores` y `usuarios` ya existentes en la aplicación.

---

## Glossary

- **VentaController**: Controlador Laravel que gestiona las operaciones del módulo de ventas (listado, creación, detalle, ticket).
- **Venta**: Entidad cabecera almacenada en la tabla `ventas`, con campos `id`, `correlativo`, `observacion`, `subtotal`, `total`, `user_id` y `timestamps`.
- **DetalleVenta**: Entidad línea almacenada en la tabla `detalle_ventas`, con campos `id`, `venta_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal` y `timestamps`.
- **Producto**: Entidad existente en la tabla `productos` con campos `nombre`, `precio_venta`, `stock`, entre otros.
- **User**: Entidad existente en la tabla `users` que representa al usuario autenticado que genera la venta.
- **Correlativo**: Número secuencial único que identifica una venta (p.ej. VTA-0001).
- **Subtotal**: Suma de los subtotales de todas las líneas de detalle antes de aplicar descuento.
- **Total**: Monto final de la venta después de aplicar el descuento. Acepta decimales con precisión de 2 dígitos.
- **Descuento**: Reducción aplicada al subtotal de la venta, expresada como monto editado manualmente o como porcentaje.
- **Porcentaje de descuento**: Valor numérico entre 0 y 100 que se aplica sobre el subtotal para calcular el total.
- **Stock**: Campo entero del Producto que representa las unidades disponibles para la venta.
- **Formulario de venta**: Vista `ventas.create` que permite construir una venta seleccionando productos mediante búsqueda Ajax.
- **Barra de búsqueda dinámica**: Campo de texto que consulta productos por nombre vía Ajax y muestra resultados en tiempo real.
- **Tabla de detalle**: Sección del formulario que lista los productos seleccionados con cantidad, precio unitario y subtotal por línea.
- **Vista de detalle**: Vista `ventas.show` que muestra todos los datos de una venta ya registrada.
- **Ticket de venta**: Vista `ventas.ticket` imprimible con el formato estándar de nota de venta.
- **Flash Message**: Mensaje de sesión de un solo uso mostrado tras una redirección para informar al usuario del resultado de una operación.
- **Route Model Binding**: Mecanismo de Laravel que resuelve automáticamente una instancia de modelo a partir del parámetro de ruta.
- **Usuario autenticado**: Cualquier usuario con sesión activa en el sistema, independientemente de su rol, que tiene acceso al módulo de ventas.

---

## Requirements

### Requirement 1: Estructura de base de datos — tabla `ventas`

**User Story:** Como desarrollador, quiero que la tabla `ventas` tenga los campos necesarios para registrar la cabecera de cada venta, para que el sistema pueda almacenar y consultar ventas correctamente.

#### Acceptance Criteria

1. THE Sistema SHALL definir la tabla `ventas` con las columnas: `id` (PK autoincremental), `correlativo` (string único, no nulo), `observacion` (text, nullable), `subtotal` (decimal 10,2, default 0), `total` (decimal 10,2, default 0), `user_id` (foreign key a `users`), `created_at` y `updated_at`. La columna `user_id` reemplaza a `cliente_id`; la migración existente debe actualizarse para usar `user_id` en lugar de `cliente_id`.
2. THE Sistema SHALL definir la columna `user_id` como clave foránea que referencia la tabla `users` con `onDelete('restrict')` para evitar eliminar usuarios con ventas asociadas.
3. THE Sistema SHALL definir un índice único sobre la columna `correlativo` para garantizar que no existan dos ventas con el mismo correlativo.
4. WHEN la migración se revierte con `php artisan migrate:rollback`, THE Sistema SHALL eliminar la tabla `ventas` sin errores.

---

### Requirement 2: Estructura de base de datos — tabla `detalle_ventas`

**User Story:** Como desarrollador, quiero que la tabla `detalle_ventas` registre cada línea de producto dentro de una venta, para que el sistema pueda calcular totales y reducir stock correctamente.

#### Acceptance Criteria

1. THE Sistema SHALL definir la tabla `detalle_ventas` con las columnas: `id` (PK autoincremental), `venta_id` (foreign key a `ventas`), `producto_id` (foreign key a `productos`), `cantidad` (integer, default 1), `precio_unitario` (decimal 10,2), `subtotal` (decimal 10,2), `created_at` y `updated_at`.
2. THE Sistema SHALL definir `venta_id` como clave foránea con `onDelete('cascade')` para que al eliminar una venta se eliminen automáticamente sus líneas de detalle.
3. THE Sistema SHALL definir `producto_id` como clave foránea con `onDelete('restrict')` para evitar eliminar productos con ventas asociadas.
4. WHEN la migración se revierte con `php artisan migrate:rollback`, THE Sistema SHALL eliminar la tabla `detalle_ventas` sin errores.

---

### Requirement 3: Modelo Venta

**User Story:** Como desarrollador, quiero que el modelo `Venta` exponga las relaciones y los casts correctos, para que el controlador pueda operar sobre los datos de forma segura y expresiva.

#### Acceptance Criteria

1. THE Modelo Venta SHALL declarar en `$fillable` los campos: `correlativo`, `observacion`, `subtotal`, `total`, `user_id`.
2. THE Modelo Venta SHALL declarar los casts: `subtotal` como `decimal:2`, `total` como `decimal:2`.
3. THE Modelo Venta SHALL exponer una relación `belongsTo` hacia el modelo `User` a través del campo `user_id`.
4. THE Modelo Venta SHALL exponer una relación `hasMany` hacia el modelo `DetalleVenta`.
5. THE Modelo Venta SHALL exponer una relación `belongsToMany` hacia el modelo `Producto` a través de la tabla `detalle_ventas`, con los pivotes `cantidad`, `precio_unitario` y `subtotal`.

---

### Requirement 4: Modelo DetalleVenta

**User Story:** Como desarrollador, quiero que el modelo `DetalleVenta` exponga las relaciones correctas, para que el sistema pueda acceder a los datos del producto y la venta desde cada línea de detalle.

#### Acceptance Criteria

1. THE Modelo DetalleVenta SHALL declarar en `$fillable` los campos: `venta_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal`.
2. THE Modelo DetalleVenta SHALL declarar los casts: `cantidad` como `integer`, `precio_unitario` como `decimal:2`, `subtotal` como `decimal:2`.
3. THE Modelo DetalleVenta SHALL exponer una relación `belongsTo` hacia el modelo `Venta`.
4. THE Modelo DetalleVenta SHALL exponer una relación `belongsTo` hacia el modelo `Producto`.

---

### Requirement 5: Generación automática de correlativo

**User Story:** Como usuario, quiero que cada venta reciba automáticamente un correlativo único y secuencial, para poder identificar y referenciar cada venta de forma inequívoca.

#### Acceptance Criteria

1. WHEN se crea una nueva Venta, THE VentaController SHALL generar el correlativo con el formato `VTA-XXXX`, donde `XXXX` es el número de la venta con cero-relleno a 4 dígitos (p.ej. `VTA-0001`, `VTA-0042`).
2. THE VentaController SHALL calcular el correlativo tomando el `id` máximo actual de la tabla `ventas` más uno, para garantizar unicidad incluso ante eliminaciones.
3. THE Sistema SHALL almacenar el correlativo generado en el campo `correlativo` de la Venta antes de persistirla.

---

### Requirement 6: Listado paginado de ventas

**User Story:** Como usuario, quiero ver la lista de todas las ventas registradas con sus datos principales, para tener una visión general de las ventas realizadas.

#### Acceptance Criteria

1. WHEN el usuario accede a la ruta `GET /ventas`, THE VentaController SHALL devolver la vista `ventas.index` con las ventas paginadas de 15 en 15, ordenadas por fecha de creación descendente.
2. THE Listado SHALL mostrar las columnas: Correlativo, Fecha, Usuario, Subtotal, Total y Acciones para cada venta.
3. THE Listado SHALL mostrar un botón "Nueva venta" en el encabezado que enlaza a la ruta `ventas.create`.
4. WHEN no existen ventas registradas, THE Listado SHALL mostrar el mensaje "No hay ventas registradas." en lugar de la tabla.
5. THE Listado SHALL mostrar los controles de paginación de Laravel debajo de la tabla cuando el total de ventas supera 15.
6. THE Listado SHALL mostrar un botón "Ver detalle" por fila que enlaza a la vista de detalle de la venta.
7. THE Listado SHALL mostrar un botón "Ticket" por fila que enlaza a la vista de ticket de la venta.
8. THE Listado SHALL mostrar un botón "Eliminar" por fila que, WHEN el usuario lo pulsa, THE Formulario SHALL mostrar un diálogo de confirmación antes de enviar la solicitud de eliminación.

---

### Requirement 7: Formulario de creación de venta — búsqueda dinámica de productos

**User Story:** Como usuario, quiero buscar productos por nombre en tiempo real dentro del formulario de venta, para agregar rápidamente los productos que deseo vender sin necesidad de recargar la página.

#### Acceptance Criteria

1. WHEN el usuario accede a la ruta `GET /ventas/create`, THE VentaController SHALL devolver la vista `ventas.create` con el formulario vacío.
2. THE Formulario de venta SHALL incluir una barra de búsqueda de texto que, WHEN el usuario escribe al menos 2 caracteres, THE Sistema SHALL enviar una petición Ajax a la ruta `GET /ventas/buscar-productos?q={termino}` y mostrar los resultados debajo del campo.
3. THE VentaController SHALL exponer el endpoint `GET /ventas/buscar-productos` que recibe el parámetro `q`, busca productos activos cuyo nombre contenga el término (búsqueda insensible a mayúsculas), y devuelve un JSON con los campos `id`, `nombre`, `precio_venta` y `stock` de cada coincidencia, limitado a 10 resultados.
4. WHEN el usuario selecciona un producto del resultado de búsqueda, THE Formulario SHALL agregar el producto a la Tabla de detalle con cantidad inicial `1`, precio unitario igual a `precio_venta` del producto y subtotal calculado automáticamente.
5. WHEN el usuario intenta agregar un producto que ya está en la Tabla de detalle, THE Formulario SHALL incrementar la cantidad del producto existente en `1` en lugar de añadir una fila duplicada.
6. THE Formulario SHALL permitir al usuario modificar la cantidad de cada producto en la Tabla de detalle mediante un campo numérico editable con valor mínimo `1`.
7. WHEN el usuario modifica la cantidad de un producto en la Tabla de detalle, THE Formulario SHALL recalcular automáticamente el subtotal de esa línea y el subtotal general de la venta.
8. THE Formulario SHALL permitir al usuario eliminar un producto de la Tabla de detalle mediante un botón de eliminación por fila.
9. WHEN el usuario elimina un producto de la Tabla de detalle, THE Formulario SHALL recalcular automáticamente el subtotal general de la venta.

---

### Requirement 8: Formulario de creación de venta — cálculo de totales y descuentos

**User Story:** Como usuario, quiero poder aplicar descuentos a la venta ya sea editando el total manualmente o ingresando un porcentaje, para registrar ventas con precio ajustado.

#### Acceptance Criteria

1. THE Formulario de venta SHALL mostrar el campo `subtotal` como valor calculado (solo lectura) que refleja la suma de los subtotales de todas las líneas de la Tabla de detalle.
2. THE Formulario de venta SHALL mostrar el campo `total` como campo editable que inicialmente tiene el mismo valor que el subtotal.
3. WHEN el usuario edita el campo `total` manualmente, THE Formulario SHALL conservar el valor ingresado como total final de la venta.
4. THE Formulario de venta SHALL incluir un toggle o checkbox "Aplicar descuento por porcentaje" que, WHEN está activado, THE Formulario SHALL mostrar un campo numérico para ingresar el porcentaje de descuento.
5. WHEN el usuario ingresa un porcentaje de descuento, THE Formulario SHALL calcular automáticamente el total como `subtotal * (1 - porcentaje / 100)` y actualizar el campo `total`.
6. THE Formulario SHALL validar en el cliente que el porcentaje de descuento no exceda `100`.
7. IF el porcentaje de descuento excede `100`, THEN THE Formulario SHALL mostrar un mensaje de error inline y restablecer el porcentaje al valor máximo permitido `100`.
8. THE Formulario de venta SHALL incluir un campo de texto `observacion` opcional para registrar notas generales de la venta.

---

### Requirement 9: Confirmación y persistencia de la venta

**User Story:** Como usuario, quiero confirmar la venta para que el sistema la registre en la base de datos y descuente el stock de los productos vendidos, para mantener el inventario actualizado.

#### Acceptance Criteria

1. WHEN el usuario envía el formulario con datos válidos mediante `POST /ventas`, THE VentaController SHALL persistir la Venta en la tabla `ventas` con los campos `correlativo`, `observacion`, `subtotal`, `total` y `user_id` (usuario autenticado).
2. WHEN la Venta se persiste, THE VentaController SHALL persistir cada línea de la Tabla de detalle en la tabla `detalle_ventas` con los campos `venta_id`, `producto_id`, `cantidad`, `precio_unitario` y `subtotal`.
3. WHEN la Venta se persiste, THE VentaController SHALL reducir el campo `stock` de cada Producto involucrado en la cantidad registrada en la línea de detalle correspondiente.
4. THE VentaController SHALL ejecutar la persistencia de la Venta, los DetalleVenta y la reducción de stock dentro de una transacción de base de datos, de modo que IF cualquier operación falla, THEN THE Sistema SHALL revertir todos los cambios y no dejar datos parciales.
5. WHEN la venta se registra correctamente, THE VentaController SHALL redirigir a `ventas.show` con el id de la venta creada y mostrar el Flash Message de éxito "Venta registrada correctamente."
6. THE VentaController SHALL validar que el formulario incluye al menos un producto en la Tabla de detalle; IF no hay productos, THEN THE VentaController SHALL redirigir al formulario con el mensaje de error "Debe agregar al menos un producto a la venta."
7. THE VentaController SHALL validar que el campo `total` es numérico y mayor que cero.
8. THE VentaController SHALL validar que cada `cantidad` en la Tabla de detalle es un entero mayor o igual a `1`.

---

### Requirement 10: Vista de detalle de venta

**User Story:** Como usuario, quiero ver el detalle completo de una venta registrada, para consultar los productos vendidos, el total y el usuario que realizó la venta.

#### Acceptance Criteria

1. WHEN el usuario accede a la ruta `GET /ventas/{venta}`, THE VentaController SHALL devolver la vista `ventas.show` con todos los datos de la Venta cargados mediante Route Model Binding.
2. THE Vista de detalle SHALL mostrar: correlativo, fecha de la venta (formato `d/m/Y`), nombre del usuario que realizó la venta, observación (si existe), subtotal y total.
3. THE Vista de detalle SHALL mostrar la tabla de productos con las columnas: Nombre del producto, Cantidad, Precio unitario y Subtotal por línea.
4. WHEN el total de la Venta es diferente al subtotal, THE Vista de detalle SHALL mostrar el precio original (subtotal), el total final y la diferencia calculada como "Descuento aplicado: S/ {diferencia}".
5. WHEN el total de la Venta es igual al subtotal, THE Vista de detalle SHALL mostrar únicamente el total sin sección de descuento.
6. THE Vista de detalle SHALL incluir un botón "Generar ticket" que enlaza a la vista de ticket de la venta.
7. THE Vista de detalle SHALL incluir un botón "Volver al listado" que enlaza a `ventas.index`.

---

### Requirement 11: Ticket / Nota de venta

**User Story:** Como usuario, quiero generar e imprimir un ticket de venta con todos los detalles del proceso, para entregar un comprobante al cliente.

#### Acceptance Criteria

1. WHEN el usuario accede a la ruta `GET /ventas/{venta}/ticket`, THE VentaController SHALL devolver la vista `ventas.ticket` con todos los datos de la Venta cargados mediante Route Model Binding.
2. THE Ticket SHALL mostrar: nombre o razón social del negocio en el encabezado, correlativo de la venta, fecha y hora de la venta (formato `d/m/Y H:i`), nombre del usuario que realizó la venta.
3. THE Ticket SHALL mostrar la tabla de productos con las columnas: Nombre del producto, Cantidad, Precio unitario y Subtotal por línea.
4. THE Ticket SHALL mostrar el subtotal, y WHEN existe descuento, SHALL mostrar el monto del descuento y el total final; WHEN no existe descuento, SHALL mostrar únicamente el total.
5. THE Ticket SHALL mostrar la observación de la venta WHEN el campo `observacion` no es nulo.
6. THE Vista de ticket SHALL incluir un botón "Imprimir" que ejecuta `window.print()` para imprimir la página.
7. THE Vista de ticket SHALL aplicar estilos CSS de impresión (`@media print`) que oculten los elementos de navegación y el botón de imprimir, mostrando únicamente el contenido del ticket.
8. THE Vista de ticket SHALL seguir un formato estándar de ticket de venta: ancho máximo de 400px centrado, tipografía monoespaciada o sans-serif, separadores horizontales entre secciones.

---

### Requirement 12: Reducción de stock al confirmar la venta

**User Story:** Como usuario, quiero que al confirmar una venta el stock de cada producto se reduzca automáticamente según la cantidad vendida, para mantener el inventario actualizado sin intervención manual.

#### Acceptance Criteria

1. WHEN la Venta se persiste correctamente, THE VentaController SHALL decrementar el campo `stock` de cada Producto en la cantidad registrada en la línea de DetalleVenta correspondiente, usando `decrement('stock', $cantidad)` o equivalente atómico.
2. THE Sistema SHALL ejecutar la reducción de stock dentro de la misma transacción de base de datos que persiste la Venta y los DetalleVenta (ver Requirement 9.4).
3. THE Sistema SHALL dejar el campo `inventario` del Producto sin modificar; la reducción aplica únicamente al campo `stock`.

---

### Requirement 13: Control de acceso y rutas

**User Story:** Como usuario autenticado, quiero que el módulo de ventas esté protegido por autenticación, para que cualquier usuario con sesión activa pueda registrar y consultar ventas independientemente de su rol.

#### Acceptance Criteria

1. THE Sistema SHALL registrar las rutas del módulo de ventas dentro del grupo de middleware `['auth']` en `routes/web.php`, sin restricción de rol adicional.
2. THE Sistema SHALL registrar las siguientes rutas: `GET /ventas` (index), `GET /ventas/create` (create), `POST /ventas` (store), `GET /ventas/{venta}` (show), `GET /ventas/{venta}/ticket` (ticket), `GET /ventas/buscar-productos` (búsqueda Ajax), `DELETE /ventas/{venta}` (destroy).
3. WHEN un usuario no autenticado intenta acceder a cualquier ruta del módulo de ventas, THE Sistema SHALL redirigirlo a la página de login.

---

### Requirement 14: Integración en el sidebar y bottom nav

**User Story:** Como usuario, quiero ver el enlace a Ventas en el menú lateral y en la navegación móvil, para acceder al módulo desde cualquier dispositivo.

#### Acceptance Criteria

1. THE Sistema SHALL añadir el enlace "Ventas" en el sidebar de escritorio dentro de un grupo desplegable apropiado (nuevo grupo "Ventas" o grupo existente según el diseño del layout).
2. THE Sistema SHALL añadir el enlace "Ventas" en el bottom nav móvil.
3. THE Sistema SHALL añadir una variable `$ventasActive` en el layout `layouts.app` que se active cuando la ruta actual coincida con `ventas.*`.
4. WHEN el usuario está en cualquier ruta del módulo de ventas, THE Sidebar SHALL mostrar el enlace "Ventas" resaltado con las clases `bg-gray-100 text-gray-900 font-semibold`.

---

### Requirement 15: Consistencia visual con los módulos existentes

**User Story:** Como usuario, quiero que el módulo de ventas tenga el mismo aspecto visual que los módulos de productos, trabajadores y usuarios, para mantener una experiencia de usuario coherente en toda la aplicación.

#### Acceptance Criteria

1. THE Listado de ventas SHALL extender el layout `layouts.app` y aplicar los estilos Tailwind CSS: tabla con `divide-y divide-gray-200`, contenedor con `bg-white rounded-lg border border-gray-200`.
2. THE Formulario de venta SHALL extender el layout `layouts.app` y aplicar el contenedor `bg-white rounded-lg border border-gray-200 p-6`.
3. THE Formulario de venta SHALL mostrar los errores de validación con las clases `border-red-400 bg-red-50` en el campo afectado y el mensaje con la clase `text-xs text-red-600`.
4. THE Listado SHALL mostrar el botón "Ver detalle" con clases `bg-gray-100 text-gray-700` y el botón "Ticket" con clases `bg-blue-100 text-blue-700`.
5. THE Listado y THE Formulario SHALL mostrar Flash Messages de éxito con clases `bg-green-100 text-green-800 border border-green-200` y Flash Messages de error con clases `bg-red-100 text-red-800 border border-red-200`.
6. THE Formulario de venta SHALL incluir un botón "Volver" en el encabezado que enlaza a `ventas.index`, con las clases `bg-gray-100 text-gray-700`.

---

### Requirement 16: Eliminación de venta con restauración de stock

**User Story:** Como usuario, quiero poder eliminar una venta registrada y que el stock de los productos involucrados se restaure automáticamente, para mantener el inventario correcto cuando una venta se anula.

#### Acceptance Criteria

1. WHEN el usuario confirma la eliminación de una Venta mediante `DELETE /ventas/{venta}`, THE VentaController SHALL restaurar el campo `stock` de cada Producto involucrado sumando de vuelta la cantidad registrada en la línea de DetalleVenta correspondiente, usando `increment('stock', $cantidad)` o equivalente atómico.
2. THE VentaController SHALL ejecutar la restauración de stock y la eliminación de la Venta dentro de una transacción de base de datos, de modo que IF cualquier operación falla, THEN THE Sistema SHALL revertir todos los cambios y no dejar datos parciales ni stock inconsistente.
3. WHEN la Venta se elimina correctamente, THE VentaController SHALL redirigir a `ventas.index` y mostrar el Flash Message de éxito "Venta eliminada y stock restaurado correctamente."
4. IF ocurre un error durante la eliminación, THEN THE VentaController SHALL revertir la transacción, redirigir al listado y mostrar el Flash Message de error "No se pudo eliminar la venta. Intente nuevamente."
5. THE Sistema SHALL eliminar en cascada los registros de `detalle_ventas` asociados a la Venta eliminada (comportamiento garantizado por la restricción `onDelete('cascade')` definida en Requirement 2.2).
