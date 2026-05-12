# Documento de Requisitos

## Introducción

Este documento describe los requisitos para el **modal de actualización de stock** en la tabla de listado de productos. La funcionalidad permite al administrador ingresar una cantidad adicional de stock directamente desde la tabla, sin necesidad de editar el producto completo. La lógica de negocio establece que el nuevo stock es la suma del stock actual más la cantidad ingresada, y ese resultado se asigna simultáneamente tanto al campo `stock` como al campo `inventario`, marcando así el inicio de un nuevo ciclo de inventario.

## Glosario

- **Producto**: Entidad existente en el sistema que representa un artículo con nombre, precios, stock e inventario.
- **Stock**: Campo del producto que refleja las unidades disponibles actualmente. Se descuenta con cada venta o cambio de aceite.
- **Inventario**: Campo del producto que registra el stock inicial del ciclo vigente. Se usa como referencia de auditoría y permanece intacto hasta la próxima renovación de stock.
- **Cantidad_Adicional**: Número entero positivo ingresado por el administrador que representa las unidades a sumar al stock actual.
- **Nuevo_Stock**: Resultado de la operación `stock_actual + Cantidad_Adicional`. Se asigna tanto a `stock` como a `inventario`.
- **Modal_Stock**: Componente de interfaz (ventana modal) que aparece sobre la tabla de productos y permite ingresar la Cantidad_Adicional para un producto específico.
- **Gestor_Stock**: Módulo del sistema responsable de procesar la actualización de stock desde el modal.

---

## Requisitos

### Requisito 1: Apertura del modal desde la tabla de productos

**User Story:** Como administrador, quiero abrir un modal de actualización de stock directamente desde la fila del producto en la tabla, para poder actualizar el stock sin abandonar el listado.

#### Criterios de Aceptación

1. THE Gestor_Stock SHALL mostrar un botón de acción de actualización de stock en cada fila de la tabla de listado de productos.
2. WHEN el administrador hace clic en el botón de actualización de stock de un producto, THE Modal_Stock SHALL abrirse mostrando el nombre del producto, el stock actual y un campo de entrada para la Cantidad_Adicional.
3. WHEN el Modal_Stock se abre, THE Modal_Stock SHALL inicializar el campo de Cantidad_Adicional vacío o en cero.
4. WHEN el Modal_Stock se abre, THE Modal_Stock SHALL mostrar el valor actual de `stock` del producto seleccionado como referencia visual.

---

### Requisito 2: Validación de la cantidad adicional

**User Story:** Como administrador, quiero que el sistema valide la cantidad ingresada antes de actualizar el stock, para evitar datos incorrectos o inconsistentes en el inventario.

#### Criterios de Aceptación

1. IF el administrador envía el formulario del Modal_Stock con el campo Cantidad_Adicional vacío, THEN THE Gestor_Stock SHALL rechazar la solicitud y mostrar un mensaje de error indicando que el campo es obligatorio.
2. IF el administrador ingresa un valor no numérico en el campo Cantidad_Adicional, THEN THE Gestor_Stock SHALL rechazar la solicitud y mostrar un mensaje de error indicando que el valor debe ser un número entero.
3. IF el administrador ingresa un valor menor o igual a cero en el campo Cantidad_Adicional, THEN THE Gestor_Stock SHALL rechazar la solicitud y mostrar un mensaje de error indicando que la cantidad debe ser mayor a cero.
4. IF el administrador ingresa un valor que supera 9999 unidades en el campo Cantidad_Adicional, THEN THE Gestor_Stock SHALL rechazar la solicitud y mostrar un mensaje de error indicando que la cantidad máxima permitida es 9999.

---

### Requisito 3: Actualización atómica de stock e inventario

**User Story:** Como administrador, quiero que al confirmar la actualización el sistema calcule el nuevo stock y lo asigne también al inventario, para registrar correctamente el inicio del nuevo ciclo de inventario.

#### Criterios de Aceptación

1. WHEN el administrador confirma la actualización con una Cantidad_Adicional válida, THE Gestor_Stock SHALL calcular el Nuevo_Stock como la suma del `stock` actual del producto más la Cantidad_Adicional.
2. WHEN el Gestor_Stock calcula el Nuevo_Stock, THE Gestor_Stock SHALL asignar el Nuevo_Stock tanto al campo `stock` como al campo `inventario` del producto en la misma operación de base de datos.
3. WHILE el Gestor_Stock procesa la actualización, THE Gestor_Stock SHALL ejecutar la escritura de `stock` e `inventario` dentro de una transacción de base de datos para garantizar consistencia.
4. IF ocurre un error durante la transacción de actualización, THEN THE Gestor_Stock SHALL revertir todos los cambios y devolver un mensaje de error al administrador.

---

### Requisito 4: Retroalimentación al usuario tras la actualización

**User Story:** Como administrador, quiero recibir confirmación visual inmediata tras actualizar el stock, para saber que la operación se realizó correctamente.

#### Criterios de Aceptación

1. WHEN la actualización de stock se completa exitosamente, THE Modal_Stock SHALL cerrarse automáticamente.
2. WHEN la actualización de stock se completa exitosamente, THE Gestor_Stock SHALL mostrar un mensaje de confirmación indicando que el stock fue actualizado correctamente.
3. WHEN la actualización de stock se completa exitosamente, THE Gestor_Stock SHALL reflejar el nuevo valor de `stock` en la fila correspondiente de la tabla de productos sin requerir recarga manual de la página completa.
4. WHEN el administrador cancela o cierra el Modal_Stock sin confirmar, THE Modal_Stock SHALL cerrarse sin realizar ningún cambio en el producto.

---

### Requisito 5: Seguridad y control de acceso

**User Story:** Como administrador, quiero que la actualización de stock esté protegida por autenticación y autorización, para que solo usuarios con permisos puedan modificar el inventario.

#### Criterios de Aceptación

1. WHEN una solicitud de actualización de stock llega al servidor, THE Gestor_Stock SHALL verificar que el usuario está autenticado antes de procesar la operación.
2. IF el usuario no tiene el rol de Administrador, THEN THE Gestor_Stock SHALL rechazar la solicitud con un código de respuesta HTTP 403.
3. WHEN el Gestor_Stock recibe el identificador del producto a actualizar, THE Gestor_Stock SHALL verificar que el producto existe en la base de datos antes de procesar la actualización.
4. IF el producto no existe en la base de datos, THEN THE Gestor_Stock SHALL devolver un código de respuesta HTTP 404.
