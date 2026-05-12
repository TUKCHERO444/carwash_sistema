# Requirements Document

## Introduction

Este documento define los requisitos para implementar un sistema completo de gestión para un taller mecánico en Laravel. El sistema gestiona clientes, vehículos, trabajadores, inventario de productos, servicios, cambios de aceite, ingresos de vehículos al taller y ventas. La implementación incluye modelos Eloquent, migraciones de base de datos adaptadas de Oracle a MySQL/PostgreSQL, y seeders con datos de ejemplo realistas.

## Glossary

- **Sistema**: El sistema de gestión de taller mecánico implementado en Laravel
- **Cliente**: Persona que solicita servicios o compra productos en el taller
- **Vehiculo**: Tipo de vehículo del catálogo con información de nombre, descripción y precio
- **Trabajador**: Empleado del taller que realiza servicios y cambios de aceite
- **Producto**: Artículo del inventario con precios de compra/venta, stock e inventario
- **Servicio**: Tipo de servicio ofrecido por el taller con precio definido
- **Cambio_Aceite**: Registro de un cambio de aceite realizado a un cliente
- **Cambio_Producto**: Relación entre productos utilizados en un cambio de aceite específico
- **Ingreso**: Registro de entrada de un vehículo al taller para recibir servicios
- **Ingreso_Trabajador**: Asignación de trabajadores a un ingreso específico
- **Detalle_Servicio**: Servicios aplicados a un ingreso específico
- **Venta**: Transacción de venta de productos a clientes
- **Detalle_Venta**: Productos individuales vendidos en una venta específica
- **Modelo_Eloquent**: Clase PHP que representa una tabla de base de datos en Laravel
- **Migracion**: Archivo PHP que define la estructura de una tabla de base de datos
- **Seeder**: Archivo PHP que inserta datos de ejemplo en la base de datos
- **Foreign_Key**: Restricción de integridad referencial entre tablas
- **Soft_Delete**: Eliminación lógica que marca registros como eliminados sin borrarlos físicamente

## Requirements

### Requirement 1: Gestión de Clientes

**User Story:** Como administrador del taller, quiero gestionar la información de clientes, para poder identificarlos y asociarlos con sus vehículos y servicios.

#### Acceptance Criteria

1. THE Sistema SHALL crear una tabla "clientes" con campos: id, dni, nombre, placa, timestamps
2. THE Sistema SHALL crear un Modelo_Eloquent "Cliente" con fillable: dni, nombre, placa
3. THE Sistema SHALL definir una relación hasMany desde Cliente hacia Cambio_Aceite
4. THE Sistema SHALL definir una relación hasMany desde Cliente hacia Ingreso
5. THE Sistema SHALL definir una relación hasMany desde Cliente hacia Venta
6. THE Sistema SHALL crear un seeder que genere al menos 20 clientes con DNI únicos de 8 dígitos, nombres realistas y placas de vehículos válidas

### Requirement 2: Catálogo de Vehículos

**User Story:** Como administrador del taller, quiero mantener un catálogo de tipos de vehículos, para poder registrar qué tipo de vehículo ingresa al taller.

#### Acceptance Criteria

1. THE Sistema SHALL crear una tabla "vehiculos" con campos: id, nombre, descripcion, precio, timestamps
2. THE Sistema SHALL crear un Modelo_Eloquent "Vehiculo" con fillable: nombre, descripcion, precio
3. THE Sistema SHALL definir una relación hasMany desde Vehiculo hacia Ingreso
4. THE Sistema SHALL crear un seeder que genere al menos 15 tipos de vehículos con nombres descriptivos, descripciones detalladas y precios realistas entre 5000 y 50000

### Requirement 3: Gestión de Trabajadores

**User Story:** Como administrador del taller, quiero gestionar la información de trabajadores, para poder asignarlos a servicios y cambios de aceite.

#### Acceptance Criteria

1. THE Sistema SHALL crear una tabla "trabajadores" con campos: id, nombre, estado (boolean), timestamps
2. THE Sistema SHALL crear un Modelo_Eloquent "Trabajador" con fillable: nombre, estado
3. THE Sistema SHALL definir una relación hasMany desde Trabajador hacia Cambio_Aceite
4. THE Sistema SHALL definir una relación belongsToMany desde Trabajador hacia Ingreso a través de Ingreso_Trabajador
5. THE Sistema SHALL crear un seeder que genere al menos 10 trabajadores con nombres realistas y estados activos/inactivos distribuidos 80/20

### Requirement 4: Inventario de Productos

**User Story:** Como administrador del taller, quiero gestionar el inventario de productos, para poder controlar stock, precios de compra/venta e inventario.

#### Acceptance Criteria

1. THE Sistema SHALL crear una tabla "productos" con campos: id, nombre, precio_compra, precio_venta, stock, inventario, timestamps
2. THE Sistema SHALL crear un Modelo_Eloquent "Producto" con fillable: nombre, precio_compra, precio_venta, stock, inventario
3. THE Sistema SHALL definir una relación belongsToMany desde Producto hacia Cambio_Aceite a través de Cambio_Producto
4. THE Sistema SHALL definir una relación belongsToMany desde Producto hacia Venta a través de Detalle_Venta
5. THE Sistema SHALL crear un seeder que genere al menos 30 productos con nombres descriptivos, precios de compra menores que precios de venta, stock entre 0 y 100, e inventario entre 0 y 500

### Requirement 5: Catálogo de Servicios

**User Story:** Como administrador del taller, quiero mantener un catálogo de servicios ofrecidos, para poder asignarlos a los ingresos de vehículos.

#### Acceptance Criteria

1. THE Sistema SHALL crear una tabla "servicios" con campos: id, nombre, precio, timestamps
2. THE Sistema SHALL crear un Modelo_Eloquent "Servicio" con fillable: nombre, precio
3. THE Sistema SHALL definir una relación belongsToMany desde Servicio hacia Ingreso a través de Detalle_Servicio
4. THE Sistema SHALL crear un seeder que genere al menos 12 servicios con nombres descriptivos y precios realistas entre 50 y 1000

### Requirement 6: Registro de Cambios de Aceite

**User Story:** Como trabajador del taller, quiero registrar cambios de aceite realizados, para poder llevar un historial de este servicio específico.

#### Acceptance Criteria

1. THE Sistema SHALL crear una tabla "cambio_aceites" con campos: id, cliente_id, trabajador_id, fecha, timestamps
2. THE Sistema SHALL crear un Modelo_Eloquent "CambioAceite" con fillable: cliente_id, trabajador_id, fecha
3. THE Sistema SHALL definir una relación belongsTo desde CambioAceite hacia Cliente
4. THE Sistema SHALL definir una relación belongsTo desde CambioAceite hacia Trabajador
5. THE Sistema SHALL definir una relación belongsToMany desde CambioAceite hacia Producto a través de Cambio_Producto
6. THE Sistema SHALL crear Foreign_Key desde cambio_aceites.cliente_id hacia clientes.id con onDelete cascade
7. THE Sistema SHALL crear Foreign_Key desde cambio_aceites.trabajador_id hacia trabajadores.id con onDelete cascade
8. THE Sistema SHALL crear un seeder que genere al menos 40 cambios de aceite con fechas distribuidas en los últimos 6 meses

### Requirement 7: Productos Utilizados en Cambios de Aceite

**User Story:** Como trabajador del taller, quiero registrar qué productos se utilizaron en cada cambio de aceite, para poder controlar el consumo de inventario.

#### Acceptance Criteria

1. THE Sistema SHALL crear una tabla "cambio_productos" con campos: id, cambio_aceite_id, producto_id, cantidad, timestamps
2. THE Sistema SHALL crear un Modelo_Eloquent "CambioProducto" con fillable: cambio_aceite_id, producto_id, cantidad
3. THE Sistema SHALL definir una relación belongsTo desde CambioProducto hacia CambioAceite
4. THE Sistema SHALL definir una relación belongsTo desde CambioProducto hacia Producto
5. THE Sistema SHALL crear Foreign_Key desde cambio_productos.cambio_aceite_id hacia cambio_aceites.id con onDelete cascade
6. THE Sistema SHALL crear Foreign_Key desde cambio_productos.producto_id hacia productos.id con onDelete cascade
7. THE Sistema SHALL crear un seeder que genere entre 1 y 4 productos por cada cambio de aceite con cantidades entre 1 y 5

### Requirement 8: Registro de Ingresos de Vehículos

**User Story:** Como recepcionista del taller, quiero registrar la entrada de vehículos al taller, para poder gestionar los servicios que se les aplicarán.

#### Acceptance Criteria

1. THE Sistema SHALL crear una tabla "ingresos" con campos: id, cliente_id, vehiculo_id, fecha, foto (nullable), timestamps
2. THE Sistema SHALL crear un Modelo_Eloquent "Ingreso" con fillable: cliente_id, vehiculo_id, fecha, foto
3. THE Sistema SHALL definir una relación belongsTo desde Ingreso hacia Cliente
4. THE Sistema SHALL definir una relación belongsTo desde Ingreso hacia Vehiculo
5. THE Sistema SHALL definir una relación belongsToMany desde Ingreso hacia Trabajador a través de Ingreso_Trabajador
6. THE Sistema SHALL definir una relación belongsToMany desde Ingreso hacia Servicio a través de Detalle_Servicio
7. THE Sistema SHALL crear Foreign_Key desde ingresos.cliente_id hacia clientes.id con onDelete cascade
8. THE Sistema SHALL crear Foreign_Key desde ingresos.vehiculo_id hacia vehiculos.id con onDelete cascade
9. THE Sistema SHALL crear un seeder que genere al menos 50 ingresos con fechas distribuidas en los últimos 3 meses y 30% con fotos simuladas

### Requirement 9: Asignación de Trabajadores a Ingresos

**User Story:** Como supervisor del taller, quiero asignar trabajadores a cada ingreso de vehículo, para poder distribuir la carga de trabajo.

#### Acceptance Criteria

1. THE Sistema SHALL crear una tabla "ingreso_trabajadores" con campos: id, ingreso_id, trabajador_id, timestamps
2. THE Sistema SHALL crear un Modelo_Eloquent "IngresoTrabajador" con fillable: ingreso_id, trabajador_id
3. THE Sistema SHALL definir una relación belongsTo desde IngresoTrabajador hacia Ingreso
4. THE Sistema SHALL definir una relación belongsTo desde IngresoTrabajador hacia Trabajador
5. THE Sistema SHALL crear Foreign_Key desde ingreso_trabajadores.ingreso_id hacia ingresos.id con onDelete cascade
6. THE Sistema SHALL crear Foreign_Key desde ingreso_trabajadores.trabajador_id hacia trabajadores.id con onDelete cascade
7. THE Sistema SHALL crear un seeder que genere entre 1 y 3 trabajadores por cada ingreso sin duplicados

### Requirement 10: Servicios Aplicados a Ingresos

**User Story:** Como trabajador del taller, quiero registrar qué servicios se aplicaron a cada ingreso, para poder facturar correctamente.

#### Acceptance Criteria

1. THE Sistema SHALL crear una tabla "detalle_servicios" con campos: id, ingreso_id, servicio_id, timestamps
2. THE Sistema SHALL crear un Modelo_Eloquent "DetalleServicio" con fillable: ingreso_id, servicio_id
3. THE Sistema SHALL definir una relación belongsTo desde DetalleServicio hacia Ingreso
4. THE Sistema SHALL definir una relación belongsTo desde DetalleServicio hacia Servicio
5. THE Sistema SHALL crear Foreign_Key desde detalle_servicios.ingreso_id hacia ingresos.id con onDelete cascade
6. THE Sistema SHALL crear Foreign_Key desde detalle_servicios.servicio_id hacia servicios.id con onDelete cascade
7. THE Sistema SHALL crear un seeder que genere entre 1 y 5 servicios por cada ingreso sin duplicados

### Requirement 11: Registro de Ventas

**User Story:** Como vendedor del taller, quiero registrar ventas de productos, para poder llevar un control de transacciones.

#### Acceptance Criteria

1. THE Sistema SHALL crear una tabla "ventas" con campos: id, cliente_id, fecha, total, timestamps
2. THE Sistema SHALL crear un Modelo_Eloquent "Venta" con fillable: cliente_id, fecha, total
3. THE Sistema SHALL definir una relación belongsTo desde Venta hacia Cliente
4. THE Sistema SHALL definir una relación hasMany desde Venta hacia Detalle_Venta
5. THE Sistema SHALL crear Foreign_Key desde ventas.cliente_id hacia clientes.id con onDelete cascade
6. THE Sistema SHALL crear un seeder que genere al menos 60 ventas con fechas distribuidas en los últimos 4 meses y totales calculados correctamente

### Requirement 12: Detalle de Productos Vendidos

**User Story:** Como vendedor del taller, quiero registrar qué productos se vendieron en cada venta, para poder controlar el inventario y calcular totales.

#### Acceptance Criteria

1. THE Sistema SHALL crear una tabla "detalle_ventas" con campos: id, venta_id, producto_id, cantidad, precio_unitario, subtotal, timestamps
2. THE Sistema SHALL crear un Modelo_Eloquent "DetalleVenta" con fillable: venta_id, producto_id, cantidad, precio_unitario, subtotal
3. THE Sistema SHALL definir una relación belongsTo desde DetalleVenta hacia Venta
4. THE Sistema SHALL definir una relación belongsTo desde DetalleVenta hacia Producto
5. THE Sistema SHALL crear Foreign_Key desde detalle_ventas.venta_id hacia ventas.id con onDelete cascade
6. THE Sistema SHALL crear Foreign_Key desde detalle_ventas.producto_id hacia productos.id con onDelete cascade
7. THE Sistema SHALL crear un seeder que genere entre 1 y 6 productos por cada venta con cantidades entre 1 y 10, precios unitarios del producto y subtotales calculados correctamente

### Requirement 13: Orden de Ejecución de Migraciones

**User Story:** Como desarrollador, quiero que las migraciones se ejecuten en el orden correcto, para que las Foreign_Key se creen sin errores.

#### Acceptance Criteria

1. THE Sistema SHALL crear migraciones con timestamps que garanticen el siguiente orden: clientes, vehiculos, trabajadores, productos, servicios, cambio_aceites, cambio_productos, ingresos, ingreso_trabajadores, detalle_servicios, ventas, detalle_ventas
2. THE Sistema SHALL nombrar las migraciones siguiendo la convención Laravel: YYYY_MM_DD_HHMMSS_create_[tabla]_table.php
3. WHEN las migraciones se ejecuten, THE Sistema SHALL crear todas las tablas sin errores de Foreign_Key

### Requirement 14: Convenciones de Laravel

**User Story:** Como desarrollador, quiero que el código siga las convenciones de Laravel, para mantener consistencia y facilitar el mantenimiento.

#### Acceptance Criteria

1. THE Sistema SHALL incluir campos timestamps (created_at, updated_at) en todas las tablas
2. THE Sistema SHALL usar nombres de tabla en plural y minúsculas con guiones bajos
3. THE Sistema SHALL usar nombres de modelo en singular con PascalCase
4. THE Sistema SHALL usar id como clave primaria autoincremental en todas las tablas
5. THE Sistema SHALL usar el método foreignId() para crear Foreign_Key en migraciones
6. THE Sistema SHALL definir la propiedad $fillable en todos los modelos para mass assignment

### Requirement 15: Datos de Ejemplo Realistas

**User Story:** Como desarrollador, quiero que los seeders generen datos realistas, para poder probar el sistema con información que simule casos reales.

#### Acceptance Criteria

1. THE Sistema SHALL usar Faker para generar nombres, fechas y textos realistas en español
2. THE Sistema SHALL generar DNI con formato de 8 dígitos numéricos
3. THE Sistema SHALL generar placas de vehículos con formato válido (3 letras + 3 números)
4. THE Sistema SHALL generar fechas dentro de rangos lógicos para cada entidad
5. THE Sistema SHALL garantizar que los precios de venta sean mayores que los precios de compra en productos
6. THE Sistema SHALL calcular correctamente los subtotales en detalle_ventas y totales en ventas
7. THE Sistema SHALL evitar duplicados en relaciones many-to-many dentro del mismo registro padre
