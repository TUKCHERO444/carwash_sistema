# Documento de Requisitos

## Introducción

Este módulo implementa el CRUD de vehículos dentro de la sección **Gestión Administrativa** del sistema de gestión de taller mecánico. Su propósito es permitir al administrador registrar y mantener los tipos de vehículos con sus precios estandarizados de servicio, de modo que al registrar un ingreso o servicio se pueda referenciar el tipo de vehículo y aplicar el precio correspondiente automáticamente.

El módulo sigue el mismo patrón visual y de código que los CRUDs existentes (Productos, Trabajadores, Usuarios, Roles), usa Blade templates con Tailwind CSS, y está protegido por el rol `Administrador` mediante Spatie Permission.

---

## Glosario

- **Vehiculo**: Entidad que representa un tipo o categoría de vehículo (ej. "Sedan Compacto", "SUV Mediana") con un precio estandarizado de servicio asociado.
- **VehiculoController**: Controlador Laravel que gestiona las operaciones CRUD sobre la entidad Vehiculo.
- **Sistema**: La aplicación Laravel de gestión de taller mecánico.
- **Administrador**: Usuario autenticado con el rol `Administrador` asignado mediante Spatie Permission.
- **Precio estandarizado**: Precio de referencia asociado a un tipo de vehículo, utilizado como valor base en otros módulos del sistema (ej. cálculo de costos de servicios, registro de ingresos). No representa un precio final; puede ser ajustado en los módulos que lo consumen.
- **Gestión Administrativa**: Sección del menú lateral que agrupa los módulos de administración del negocio (Vehículos, y posteriormente Servicios).

---

## Requisitos

### Requisito 1: Listado de vehículos

**User Story:** Como Administrador, quiero ver la lista de todos los vehículos registrados, para tener una visión general de los tipos de vehículos y sus precios estandarizados.

#### Criterios de Aceptación

1. WHEN el Administrador accede a la ruta `/vehiculos`, THE VehiculoController SHALL retornar la vista `vehiculos.index` con los vehículos paginados de 15 en 15.
2. THE Sistema SHALL mostrar en la tabla las columnas: Nombre, Descripción y Precio.
3. THE Sistema SHALL mostrar el precio con formato monetario de dos decimales (ej. `S/ 15,000.00`).
4. WHEN no existen vehículos registrados, THE Sistema SHALL mostrar un mensaje de estado vacío: "No hay vehículos registrados."
5. THE Sistema SHALL mostrar controles de paginación cuando el total de vehículos supere los 15 registros.
6. THE Sistema SHALL mostrar botones de acción "Editar" y "Eliminar" por cada fila de la tabla.

---

### Requisito 2: Creación de vehículo

**User Story:** Como Administrador, quiero registrar un nuevo tipo de vehículo con su precio estandarizado, para que esté disponible al momento de registrar servicios en el taller.

#### Criterios de Aceptación

1. WHEN el Administrador accede a la ruta `/vehiculos/create`, THE VehiculoController SHALL retornar la vista `vehiculos.create` con el formulario de creación.
2. THE Sistema SHALL presentar los campos: Nombre (texto, obligatorio), Descripción (textarea, opcional) y Precio (numérico decimal, obligatorio).
3. WHEN el Administrador envía el formulario con datos válidos, THE VehiculoController SHALL persistir el nuevo Vehiculo en la base de datos y redirigir a `vehiculos.index` con el mensaje de éxito "Vehículo creado correctamente."
4. IF el campo `nombre` está vacío o supera los 100 caracteres, THEN THE Sistema SHALL mostrar el error de validación correspondiente sin persistir el registro.
5. IF el campo `precio` no es un número mayor a 0, THEN THE Sistema SHALL mostrar el error de validación correspondiente sin persistir el registro.
6. WHEN la validación falla, THE Sistema SHALL repoblar los campos del formulario con los valores ingresados previamente (`old()`).

---

### Requisito 3: Edición de vehículo

**User Story:** Como Administrador, quiero editar los datos de un vehículo existente, para corregir información o actualizar su precio estandarizado.

#### Criterios de Aceptación

1. WHEN el Administrador accede a la ruta `/vehiculos/{vehiculo}/edit`, THE VehiculoController SHALL retornar la vista `vehiculos.edit` con los datos actuales del Vehiculo precargados en el formulario.
2. WHEN el Administrador envía el formulario de edición con datos válidos, THE VehiculoController SHALL actualizar el Vehiculo en la base de datos y redirigir a `vehiculos.index` con el mensaje de éxito "Vehículo actualizado correctamente."
3. IF el campo `nombre` está vacío o supera los 100 caracteres, THEN THE Sistema SHALL mostrar el error de validación correspondiente sin persistir los cambios.
4. IF el campo `precio` no es un número mayor a 0, THEN THE Sistema SHALL mostrar el error de validación correspondiente sin persistir los cambios.
5. WHEN la validación falla, THE Sistema SHALL repoblar los campos del formulario con los valores enviados previamente (`old()`).

---

### Requisito 4: Eliminación de vehículo

**User Story:** Como Administrador, quiero eliminar un vehículo que ya no sea relevante, para mantener el catálogo limpio y actualizado.

#### Criterios de Aceptación

1. WHEN el Administrador confirma la eliminación de un Vehiculo, THE VehiculoController SHALL eliminar el registro de la base de datos y redirigir a `vehiculos.index` con el mensaje de éxito "Vehículo eliminado correctamente."
2. IF el Vehiculo tiene ingresos asociados, THEN THE VehiculoController SHALL cancelar la eliminación y redirigir a `vehiculos.index` con el mensaje de error "No se puede eliminar el vehículo porque tiene ingresos asociados."
3. THE Sistema SHALL solicitar confirmación al Administrador antes de ejecutar la eliminación mediante un diálogo de confirmación del navegador.

---

### Requisito 5: Control de acceso

**User Story:** Como Administrador, quiero que el módulo de vehículos esté protegido por rol, para que solo los usuarios autorizados puedan gestionarlo.

#### Criterios de Aceptación

1. WHILE el usuario no está autenticado, THE Sistema SHALL redirigir cualquier acceso a las rutas de vehículos hacia la página de login.
2. WHILE el usuario autenticado no tiene el rol `Administrador`, THE Sistema SHALL retornar una respuesta HTTP 403 al intentar acceder a cualquier ruta del recurso `vehiculos`.
3. THE Sistema SHALL registrar las rutas del recurso `vehiculos` bajo el middleware `['auth', 'role:Administrador']`.

---

### Requisito 6: Navegación — Sección Gestión Administrativa

**User Story:** Como Administrador, quiero acceder al módulo de vehículos desde el menú lateral bajo una sección llamada "Gestión Administrativa", para encontrarlo de forma intuitiva junto a otros módulos administrativos del negocio.

#### Criterios de Aceptación

1. THE Sistema SHALL mostrar en el sidebar una nueva sección colapsable llamada "Gestión Administrativa" visible únicamente para usuarios con el rol `Administrador`.
2. WHEN el Administrador expande la sección "Gestión Administrativa", THE Sistema SHALL mostrar el enlace "Vehículos" que navega a `vehiculos.index`.
3. WHILE el usuario se encuentra en cualquier ruta `vehiculos.*`, THE Sistema SHALL mantener la sección "Gestión Administrativa" expandida y resaltar el enlace "Vehículos" como activo.
4. THE Sistema SHALL replicar el comportamiento de la sección "Gestión Administrativa" en la barra de navegación inferior para dispositivos móviles.
