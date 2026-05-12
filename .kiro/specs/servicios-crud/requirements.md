# Documento de Requisitos

## Introducción

Este módulo implementa el CRUD de Servicios dentro de la sección **Gestión Administrativa** del sistema de gestión de taller mecánico. Su propósito es permitir al Administrador registrar y mantener el catálogo de servicios disponibles para realizar a los vehículos en el taller, de modo que al registrar un ingreso se puedan seleccionar los servicios aplicados y calcular el costo correspondiente.

El módulo sigue el mismo patrón visual y de código que el CRUD de Vehículos: controlador resource de Laravel, vistas Blade con Tailwind CSS, y protección por rol `Administrador` mediante Spatie Permission. La tabla `servicios` y el modelo `Servicio` ya existen; no se requieren migraciones adicionales. El enlace "Servicios" se agrega dentro de la sección "Gestión Administrativa" ya existente en el layout, junto al enlace "Vehículos".

---

## Glosario

- **Servicio**: Entidad que representa un tipo de trabajo o intervención que el taller puede realizar a un vehículo (ej. "Cambio de aceite", "Alineación y balanceo"), con un precio unitario asociado.
- **ServicioController**: Controlador Laravel que gestiona las operaciones CRUD sobre la entidad Servicio.
- **Sistema**: La aplicación Laravel de gestión de taller mecánico.
- **Administrador**: Usuario autenticado con el rol `Administrador` asignado mediante Spatie Permission.
- **Precio unitario**: Precio de referencia asociado a un servicio, utilizado como valor base al registrar ingresos en el taller.
- **Gestión Administrativa**: Sección del menú lateral que agrupa los módulos de administración del negocio (Vehículos y Servicios).
- **Ingreso**: Registro de una visita o trabajo realizado en el taller, que puede incluir uno o más Servicios a través de la tabla pivote `detalle_servicios`.

---

## Requisitos

### Requisito 1: Listado de servicios

**User Story:** Como Administrador, quiero ver la lista de todos los servicios registrados, para tener una visión general del catálogo de servicios disponibles y sus precios.

#### Criterios de Aceptación

1. WHEN el Administrador accede a la ruta `/servicios`, THE ServicioController SHALL retornar la vista `servicios.index` con los servicios paginados de 15 en 15.
2. THE Sistema SHALL mostrar en la tabla las columnas: Nombre y Precio.
3. THE Sistema SHALL mostrar el precio con formato monetario de dos decimales (ej. `S/ 50.00`).
4. WHEN no existen servicios registrados, THE Sistema SHALL mostrar un mensaje de estado vacío: "No hay servicios registrados."
5. THE Sistema SHALL mostrar controles de paginación cuando el total de servicios supere los 15 registros.
6. THE Sistema SHALL mostrar botones de acción "Editar" y "Eliminar" por cada fila de la tabla.

---

### Requisito 2: Creación de servicio

**User Story:** Como Administrador, quiero registrar un nuevo servicio con su precio unitario, para que esté disponible al momento de registrar ingresos en el taller.

#### Criterios de Aceptación

1. WHEN el Administrador accede a la ruta `/servicios/create`, THE ServicioController SHALL retornar la vista `servicios.create` con el formulario de creación.
2. THE Sistema SHALL presentar los campos: Nombre (texto, obligatorio) y Precio (numérico decimal, obligatorio).
3. WHEN el Administrador envía el formulario con datos válidos, THE ServicioController SHALL persistir el nuevo Servicio en la base de datos y redirigir a `servicios.index` con el mensaje de éxito "Servicio creado correctamente."
4. IF el campo `nombre` está vacío o supera los 100 caracteres, THEN THE Sistema SHALL mostrar el error de validación correspondiente sin persistir el registro.
5. IF el campo `precio` no es un número mayor a 0, THEN THE Sistema SHALL mostrar el error de validación correspondiente sin persistir el registro.
6. WHEN la validación falla, THE Sistema SHALL repoblar los campos del formulario con los valores ingresados previamente (`old()`).

---

### Requisito 3: Edición de servicio

**User Story:** Como Administrador, quiero editar los datos de un servicio existente, para corregir su nombre o actualizar su precio unitario.

#### Criterios de Aceptación

1. WHEN el Administrador accede a la ruta `/servicios/{servicio}/edit`, THE ServicioController SHALL retornar la vista `servicios.edit` con los datos actuales del Servicio precargados en el formulario.
2. WHEN el Administrador envía el formulario de edición con datos válidos, THE ServicioController SHALL actualizar el Servicio en la base de datos y redirigir a `servicios.index` con el mensaje de éxito "Servicio actualizado correctamente."
3. IF el campo `nombre` está vacío o supera los 100 caracteres, THEN THE Sistema SHALL mostrar el error de validación correspondiente sin persistir los cambios.
4. IF el campo `precio` no es un número mayor a 0, THEN THE Sistema SHALL mostrar el error de validación correspondiente sin persistir los cambios.
5. WHEN la validación falla, THE Sistema SHALL repoblar los campos del formulario con los valores enviados previamente (`old()`).

---

### Requisito 4: Eliminación de servicio

**User Story:** Como Administrador, quiero eliminar un servicio que ya no se ofrezca en el taller, para mantener el catálogo limpio y actualizado.

#### Criterios de Aceptación

1. WHEN el Administrador confirma la eliminación de un Servicio, THE ServicioController SHALL eliminar el registro de la base de datos y redirigir a `servicios.index` con el mensaje de éxito "Servicio eliminado correctamente."
2. IF el Servicio tiene ingresos asociados a través de `detalle_servicios`, THEN THE ServicioController SHALL cancelar la eliminación y redirigir a `servicios.index` con el mensaje de error "No se puede eliminar el servicio porque tiene ingresos asociados."
3. THE Sistema SHALL solicitar confirmación al Administrador antes de ejecutar la eliminación mediante un diálogo de confirmación del navegador.

---

### Requisito 5: Control de acceso

**User Story:** Como Administrador, quiero que el módulo de servicios esté protegido por rol, para que solo los usuarios autorizados puedan gestionarlo.

#### Criterios de Aceptación

1. WHILE el usuario no está autenticado, THE Sistema SHALL redirigir cualquier acceso a las rutas de servicios hacia la página de login.
2. WHILE el usuario autenticado no tiene el rol `Administrador`, THE Sistema SHALL retornar una respuesta HTTP 403 al intentar acceder a cualquier ruta del recurso `servicios`.
3. THE Sistema SHALL registrar las rutas del recurso `servicios` bajo el middleware `['auth', 'role:Administrador']`.

---

### Requisito 6: Navegación — Sección Gestión Administrativa

**User Story:** Como Administrador, quiero acceder al módulo de servicios desde el menú lateral bajo la sección "Gestión Administrativa", para encontrarlo de forma intuitiva junto al módulo de Vehículos.

#### Criterios de Aceptación

1. THE Sistema SHALL mostrar el enlace "Servicios" dentro de la sección colapsable "Gestión Administrativa" del sidebar, visible únicamente para usuarios con el rol `Administrador`.
2. WHEN el Administrador expande la sección "Gestión Administrativa", THE Sistema SHALL mostrar los enlaces "Vehículos" y "Servicios" que navegan a sus respectivos índices.
3. WHILE el usuario se encuentra en cualquier ruta `servicios.*`, THE Sistema SHALL mantener la sección "Gestión Administrativa" expandida y resaltar el enlace "Servicios" como activo.
4. THE Sistema SHALL actualizar la variable `$gestionAdministrativaActive` del layout para que detecte tanto rutas `vehiculos.*` como rutas `servicios.*`.
5. THE Sistema SHALL replicar el enlace "Servicios" en la sección "Gestión Administrativa" de la barra de navegación inferior para dispositivos móviles.
