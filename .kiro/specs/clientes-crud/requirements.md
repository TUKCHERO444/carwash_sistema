# Documento de Requisitos

## Introducción

Este módulo implementa el CRUD de Clientes dentro de la sección **Gestión Administrativa** del sistema de gestión de taller mecánico. Su propósito es permitir al administrador registrar y mantener los datos de los clientes del taller (DNI, nombre y placa de su vehículo), de modo que puedan ser referenciados al registrar ingresos, ventas y cambios de aceite.

El módulo sigue el mismo patrón visual y de código que los CRUDs existentes (Vehículos, Servicios, Productos, Trabajadores), usa Blade templates con Tailwind CSS, y está protegido por el rol `Administrador` mediante Spatie Permission. La tabla `clientes` y el modelo `Cliente` ya existen en el proyecto; este módulo únicamente agrega la capa de controlador, rutas y vistas.

---

## Glosario

- **Cliente**: Entidad que representa a una persona que lleva su vehículo al taller. Posee un DNI único, un nombre y la placa de su vehículo.
- **ClienteController**: Controlador Laravel que gestiona las operaciones CRUD sobre la entidad Cliente.
- **Sistema**: La aplicación Laravel de gestión de taller mecánico.
- **Administrador**: Usuario autenticado con el rol `Administrador` asignado mediante Spatie Permission.
- **DNI**: Documento Nacional de Identidad del cliente. Cadena de exactamente 8 caracteres numéricos, único en el sistema.
- **Placa**: Identificador del vehículo del cliente. Cadena de hasta 7 caracteres alfanuméricos.
- **Gestión Administrativa**: Sección del menú lateral que agrupa los módulos de administración del negocio (Vehículos, Servicios, Clientes).
- **Registro asociado**: Cualquier ingreso, venta o cambio de aceite vinculado a un Cliente mediante clave foránea.

---

## Requisitos

### Requisito 1: Listado de clientes

**User Story:** Como Administrador, quiero ver la lista de todos los clientes registrados, para tener una visión general de los clientes del taller y sus datos de contacto.

#### Criterios de Aceptación

1. WHEN el Administrador accede a la ruta `/clientes`, THE ClienteController SHALL retornar la vista `clientes.index` con los clientes paginados de 15 en 15.
2. THE Sistema SHALL mostrar en la tabla las columnas: DNI, Nombre y Placa.
3. WHEN no existen clientes registrados, THE Sistema SHALL mostrar un mensaje de estado vacío: "No hay clientes registrados."
4. THE Sistema SHALL mostrar controles de paginación cuando el total de clientes supere los 15 registros.
5. THE Sistema SHALL mostrar botones de acción "Editar" y "Eliminar" por cada fila de la tabla.

---

### Requisito 2: Creación de cliente

**User Story:** Como Administrador, quiero registrar un nuevo cliente con su DNI, nombre y placa, para que esté disponible al momento de registrar ingresos, ventas o cambios de aceite en el taller.

#### Criterios de Aceptación

1. WHEN el Administrador accede a la ruta `/clientes/create`, THE ClienteController SHALL retornar la vista `clientes.create` con el formulario de creación.
2. THE Sistema SHALL presentar los campos: DNI (texto, obligatorio), Nombre (texto, obligatorio) y Placa (texto, obligatorio).
3. WHEN el Administrador envía el formulario con datos válidos, THE ClienteController SHALL persistir el nuevo Cliente en la base de datos y redirigir a `clientes.index` con el mensaje de éxito "Cliente creado correctamente."
4. IF el campo `dni` está vacío, no tiene exactamente 8 caracteres, contiene caracteres no numéricos, o ya existe en la base de datos, THEN THE Sistema SHALL mostrar el error de validación correspondiente sin persistir el registro.
5. IF el campo `nombre` está vacío o supera los 100 caracteres, THEN THE Sistema SHALL mostrar el error de validación correspondiente sin persistir el registro.
6. IF el campo `placa` está vacío o supera los 7 caracteres, THEN THE Sistema SHALL mostrar el error de validación correspondiente sin persistir el registro.
7. WHEN la validación falla, THE Sistema SHALL repoblar los campos del formulario con los valores ingresados previamente (`old()`).

---

### Requisito 3: Edición de cliente

**User Story:** Como Administrador, quiero editar los datos de un cliente existente, para corregir información desactualizada o errónea.

#### Criterios de Aceptación

1. WHEN el Administrador accede a la ruta `/clientes/{cliente}/edit`, THE ClienteController SHALL retornar la vista `clientes.edit` con los datos actuales del Cliente precargados en el formulario.
2. WHEN el Administrador envía el formulario de edición con datos válidos, THE ClienteController SHALL actualizar el Cliente en la base de datos y redirigir a `clientes.index` con el mensaje de éxito "Cliente actualizado correctamente."
3. IF el campo `dni` está vacío, no tiene exactamente 8 caracteres, contiene caracteres no numéricos, o ya existe en la base de datos para un Cliente distinto, THEN THE Sistema SHALL mostrar el error de validación correspondiente sin persistir los cambios.
4. IF el campo `nombre` está vacío o supera los 100 caracteres, THEN THE Sistema SHALL mostrar el error de validación correspondiente sin persistir los cambios.
5. IF el campo `placa` está vacío o supera los 7 caracteres, THEN THE Sistema SHALL mostrar el error de validación correspondiente sin persistir los cambios.
6. WHEN la validación falla, THE Sistema SHALL repoblar los campos del formulario con los valores enviados previamente (`old()`).

---

### Requisito 4: Eliminación de cliente

**User Story:** Como Administrador, quiero eliminar un cliente que ya no sea relevante, para mantener el registro de clientes limpio y actualizado.

#### Criterios de Aceptación

1. WHEN el Administrador confirma la eliminación de un Cliente, THE ClienteController SHALL eliminar el registro de la base de datos y redirigir a `clientes.index` con el mensaje de éxito "Cliente eliminado correctamente."
2. IF el Cliente tiene ingresos asociados, THEN THE ClienteController SHALL cancelar la eliminación y redirigir a `clientes.index` con el mensaje de error "No se puede eliminar el cliente porque tiene ingresos asociados."
3. IF el Cliente tiene ventas asociadas, THEN THE ClienteController SHALL cancelar la eliminación y redirigir a `clientes.index` con el mensaje de error "No se puede eliminar el cliente porque tiene ventas asociadas."
4. IF el Cliente tiene cambios de aceite asociados, THEN THE ClienteController SHALL cancelar la eliminación y redirigir a `clientes.index` con el mensaje de error "No se puede eliminar el cliente porque tiene cambios de aceite asociados."
5. THE Sistema SHALL solicitar confirmación al Administrador antes de ejecutar la eliminación mediante un diálogo de confirmación del navegador.

---

### Requisito 5: Control de acceso

**User Story:** Como Administrador, quiero que el módulo de clientes esté protegido por rol, para que solo los usuarios autorizados puedan gestionarlo.

#### Criterios de Aceptación

1. WHILE el usuario no está autenticado, THE Sistema SHALL redirigir cualquier acceso a las rutas de clientes hacia la página de login.
2. WHILE el usuario autenticado no tiene el rol `Administrador`, THE Sistema SHALL retornar una respuesta HTTP 403 al intentar acceder a cualquier ruta del recurso `clientes`.
3. THE Sistema SHALL registrar las rutas del recurso `clientes` bajo el middleware `['auth', 'role:Administrador']`.

---

### Requisito 6: Navegación — Sección Gestión Administrativa

**User Story:** Como Administrador, quiero acceder al módulo de clientes desde el menú lateral bajo la sección "Gestión Administrativa", para encontrarlo de forma intuitiva junto a los módulos de Vehículos y Servicios.

#### Criterios de Aceptación

1. THE Sistema SHALL agregar el enlace "Clientes" dentro de la sección colapsable "Gestión Administrativa" existente en el sidebar, visible únicamente para usuarios con el rol `Administrador`.
2. WHEN el Administrador expande la sección "Gestión Administrativa", THE Sistema SHALL mostrar el enlace "Clientes" que navega a `clientes.index`.
3. WHILE el usuario se encuentra en cualquier ruta `clientes.*`, THE Sistema SHALL mantener la sección "Gestión Administrativa" expandida y resaltar el enlace "Clientes" como activo.
4. THE Sistema SHALL replicar el comportamiento del enlace "Clientes" en la barra de navegación inferior para dispositivos móviles.
5. THE Sistema SHALL actualizar la variable `$gestionAdministrativaActive` para que también evalúe las rutas `clientes.*`, de modo que la sección permanezca expandida al navegar por el módulo de clientes.
