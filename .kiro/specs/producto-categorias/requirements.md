# Documento de Requisitos

## Introducción

Este documento describe los requisitos para el módulo de **gestión de categorías de productos** del sistema. La funcionalidad permite organizar los productos existentes en categorías, facilitando su clasificación y búsqueda. Cada categoría mantiene automáticamente un contador de cuántos productos tiene asociados. Los seeders de ejemplo deben crear categorías coherentes con los productos ya existentes en el sistema y asignarles la categoría correspondiente.

## Glosario

- **Categoria**: Entidad que agrupa productos bajo un nombre común. Posee un identificador único, nombre, descripción opcional y un contador de productos asociados.
- **Producto**: Entidad existente en el sistema que representa un artículo con nombre, precios, stock e inventario.
- **Contador_Productos**: Valor numérico entero que refleja cuántos productos están actualmente asignados a una categoría.
- **Gestor_Categorias**: Módulo del sistema responsable de las operaciones CRUD sobre categorías.
- **Seeder_Categorias**: Componente de base de datos que crea categorías de ejemplo y las asigna a los productos existentes.

---

## Requisitos

### Requisito 1: Creación de categorías

**User Story:** Como administrador, quiero crear categorías de productos, para poder organizar el catálogo del sistema.

#### Criterios de Aceptación

1. THE Gestor_Categorias SHALL almacenar cada categoría con un identificador único autogenerado, un nombre y una descripción opcional.
2. WHEN el administrador envía el formulario de creación con un nombre válido, THE Gestor_Categorias SHALL persistir la nueva categoría y redirigir al listado de categorías con un mensaje de confirmación.
3. IF el campo nombre está vacío o supera los 150 caracteres, THEN THE Gestor_Categorias SHALL rechazar la solicitud y devolver un mensaje de error descriptivo.
4. IF el nombre de la categoría ya existe en el sistema, THEN THE Gestor_Categorias SHALL rechazar la solicitud y devolver un mensaje de error indicando que el nombre ya está en uso.
5. THE Gestor_Categorias SHALL inicializar el Contador_Productos de toda categoría recién creada en cero.

---

### Requisito 2: Listado de categorías

**User Story:** Como administrador, quiero ver el listado de todas las categorías, para conocer la organización actual del catálogo.

#### Criterios de Aceptación

1. WHEN el administrador accede a la sección de categorías, THE Gestor_Categorias SHALL mostrar todas las categorías existentes con su nombre, descripción y Contador_Productos.
2. THE Gestor_Categorias SHALL mostrar el Contador_Productos actualizado para cada categoría en el listado.

---

### Requisito 3: Edición de categorías

**User Story:** Como administrador, quiero editar el nombre y la descripción de una categoría, para corregir o actualizar la información del catálogo.

#### Criterios de Aceptación

1. WHEN el administrador envía el formulario de edición con datos válidos, THE Gestor_Categorias SHALL actualizar el nombre y la descripción de la categoría seleccionada.
2. IF el campo nombre está vacío o supera los 150 caracteres durante la edición, THEN THE Gestor_Categorias SHALL rechazar la solicitud y devolver un mensaje de error descriptivo.
3. IF el nombre editado ya pertenece a otra categoría distinta, THEN THE Gestor_Categorias SHALL rechazar la solicitud y devolver un mensaje de error indicando que el nombre ya está en uso.

---

### Requisito 4: Eliminación de categorías

**User Story:** Como administrador, quiero eliminar una categoría, para mantener el catálogo limpio de clasificaciones obsoletas.

#### Criterios de Aceptación

1. WHEN el administrador solicita eliminar una categoría que no tiene productos asociados, THE Gestor_Categorias SHALL eliminar la categoría y redirigir al listado con un mensaje de confirmación.
2. IF el administrador solicita eliminar una categoría que tiene uno o más productos asociados, THEN THE Gestor_Categorias SHALL rechazar la eliminación y devolver un mensaje de error indicando que la categoría tiene productos asignados.

---

### Requisito 5: Asignación de categoría a productos

**User Story:** Como administrador, quiero asignar una categoría a cada producto, para que los productos queden organizados dentro del catálogo.

#### Criterios de Aceptación

1. WHEN el administrador crea o edita un producto, THE Gestor_Categorias SHALL presentar un selector con todas las categorías activas disponibles.
2. WHEN el administrador asigna una categoría a un producto, THE Gestor_Categorias SHALL actualizar el campo `categoria_id` del producto con el identificador de la categoría seleccionada.
3. WHERE la asignación de categoría es opcional, THE Gestor_Categorias SHALL permitir guardar un producto sin categoría asignada (valor nulo).
4. WHEN un producto es asignado a una categoría, THE Gestor_Categorias SHALL incrementar en uno el Contador_Productos de esa categoría.
5. WHEN un producto cambia de categoría, THE Gestor_Categorias SHALL decrementar en uno el Contador_Productos de la categoría anterior e incrementar en uno el Contador_Productos de la nueva categoría.
6. WHEN un producto es eliminado del sistema, THE Gestor_Categorias SHALL decrementar en uno el Contador_Productos de la categoría a la que pertenecía, si tenía una asignada.

---

### Requisito 6: Contador automático de productos por categoría

**User Story:** Como administrador, quiero que el sistema mantenga automáticamente el conteo de productos por categoría, para conocer la distribución del catálogo sin cálculos manuales.

#### Criterios de Aceptación

1. THE Gestor_Categorias SHALL mantener el Contador_Productos de cada categoría sincronizado con el número real de productos que tienen asignada esa categoría.
2. WHEN se consulta el Contador_Productos de una categoría, THE Gestor_Categorias SHALL devolver un valor entero mayor o igual a cero.
3. WHILE el sistema procesa operaciones de creación, edición o eliminación de productos, THE Gestor_Categorias SHALL actualizar el Contador_Productos de las categorías afectadas de forma atómica dentro de la misma transacción de base de datos.

---

### Requisito 7: Datos de ejemplo en el seeder

**User Story:** Como desarrollador, quiero que el seeder cree categorías de ejemplo y las asigne a los productos existentes, para disponer de datos representativos en entornos de desarrollo y pruebas.

#### Criterios de Aceptación

1. WHEN se ejecuta el Seeder_Categorias, THE Seeder_Categorias SHALL crear las siguientes categorías: "Aceites y Lubricantes", "Filtros", "Frenos", "Suspensión", "Neumáticos", "Batería y Eléctrico", "Correas y Cadenas", "Líquidos".
2. WHEN se ejecuta el Seeder_Categorias, THE Seeder_Categorias SHALL asignar a cada producto existente la categoría que corresponda según su nombre.
3. WHEN se ejecuta el Seeder_Categorias, THE Seeder_Categorias SHALL actualizar el Contador_Productos de cada categoría para reflejar el número de productos asignados tras la ejecución del seeder.
4. IF un producto no puede ser asociado a ninguna categoría conocida, THEN THE Seeder_Categorias SHALL dejar el campo `categoria_id` del producto como nulo.
