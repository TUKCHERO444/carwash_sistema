# Implementation Plan: Database Schema Implementation

## Overview

Este plan de implementación cubre la creación completa del esquema de base de datos para el sistema de gestión de taller mecánico en Laravel. La implementación incluye 12 migraciones con foreign keys y constraints, 12 modelos Eloquent con relaciones, y 12 seeders con datos realistas en español.

La estrategia de implementación sigue un enfoque incremental por niveles de dependencia, garantizando que las tablas base se creen primero, seguidas de las tablas con foreign keys. Cada fase incluye verificación mediante ejecución de migraciones y seeders.

## Tasks

- [x] 1. Crear migraciones de nivel 1 (tablas sin dependencias)
  - [x] 1.1 Crear migración create_clientes_table
    - Crear archivo `database/migrations/2024_01_01_000001_create_clientes_table.php`
    - Definir campos: id, dni (string 8, unique), nombre (string 100), placa (string 7), timestamps
    - _Requirements: 1.1, 14.1, 14.2, 14.4, 14.5_
  
  - [x] 1.2 Crear migración create_vehiculos_table
    - Crear archivo `database/migrations/2024_01_01_000002_create_vehiculos_table.php`
    - Definir campos: id, nombre (string 100), descripcion (text), precio (decimal 10,2), timestamps
    - _Requirements: 2.1, 14.1, 14.2, 14.4_
  
  - [x] 1.3 Crear migración create_trabajadores_table
    - Crear archivo `database/migrations/2024_01_01_000003_create_trabajadores_table.php`
    - Definir campos: id, nombre (string 100), estado (boolean default true), timestamps
    - Agregar índice en campo estado
    - _Requirements: 3.1, 14.1, 14.2, 14.4_
  
  - [x] 1.4 Crear migración create_productos_table
    - Crear archivo `database/migrations/2024_01_01_000004_create_productos_table.php`
    - Definir campos: id, nombre (string 150), precio_compra (decimal 10,2), precio_venta (decimal 10,2), stock (integer default 0), inventario (integer default 0), timestamps
    - Agregar índice en campo stock
    - _Requirements: 4.1, 14.1, 14.2, 14.4_
  
  - [x] 1.5 Crear migración create_servicios_table
    - Crear archivo `database/migrations/2024_01_01_000005_create_servicios_table.php`
    - Definir campos: id, nombre (string 100), precio (decimal 10,2), timestamps
    - _Requirements: 5.1, 14.1, 14.2, 14.4_

- [x] 2. Crear migraciones de nivel 2 (tablas con foreign keys a nivel 1)
  - [x] 2.1 Crear migración create_cambio_aceites_table
    - Crear archivo `database/migrations/2024_01_01_000006_create_cambio_aceites_table.php`
    - Definir campos: id, cliente_id (foreignId), trabajador_id (foreignId), fecha (date), timestamps
    - Agregar foreign key cliente_id → clientes.id con onDelete cascade
    - Agregar foreign key trabajador_id → trabajadores.id con onDelete cascade
    - Agregar índice en campo fecha
    - _Requirements: 6.1, 6.6, 6.7, 14.1, 14.2, 14.4, 14.5_
  
  - [x] 2.2 Crear migración create_ingresos_table
    - Crear archivo `database/migrations/2024_01_01_000008_create_ingresos_table.php`
    - Definir campos: id, cliente_id (foreignId), vehiculo_id (foreignId), fecha (date), foto (string 255 nullable), timestamps
    - Agregar foreign key cliente_id → clientes.id con onDelete cascade
    - Agregar foreign key vehiculo_id → vehiculos.id con onDelete cascade
    - Agregar índice en campo fecha
    - _Requirements: 8.1, 8.7, 8.8, 14.1, 14.2, 14.4, 14.5_
  
  - [x] 2.3 Crear migración create_ventas_table
    - Crear archivo `database/migrations/2024_01_01_000011_create_ventas_table.php`
    - Definir campos: id, cliente_id (foreignId), fecha (date), total (decimal 10,2 default 0), timestamps
    - Agregar foreign key cliente_id → clientes.id con onDelete cascade
    - Agregar índice en campo fecha
    - _Requirements: 11.1, 11.5, 14.1, 14.2, 14.4, 14.5_

- [x] 3. Crear migraciones de nivel 3 (tablas pivote y detalles)
  - [x] 3.1 Crear migración create_cambio_productos_table
    - Crear archivo `database/migrations/2024_01_01_000007_create_cambio_productos_table.php`
    - Definir campos: id, cambio_aceite_id (foreignId), producto_id (foreignId), cantidad (integer default 1), timestamps
    - Agregar foreign key cambio_aceite_id → cambio_aceites.id con onDelete cascade
    - Agregar foreign key producto_id → productos.id con onDelete cascade
    - Agregar unique constraint en (cambio_aceite_id, producto_id)
    - _Requirements: 7.1, 7.5, 7.6, 14.1, 14.2, 14.4, 14.5_
  
  - [x] 3.2 Crear migración create_ingreso_trabajadores_table
    - Crear archivo `database/migrations/2024_01_01_000009_create_ingreso_trabajadores_table.php`
    - Definir campos: id, ingreso_id (foreignId), trabajador_id (foreignId), timestamps
    - Agregar foreign key ingreso_id → ingresos.id con onDelete cascade
    - Agregar foreign key trabajador_id → trabajadores.id con onDelete cascade
    - Agregar unique constraint en (ingreso_id, trabajador_id)
    - _Requirements: 9.1, 9.5, 9.6, 14.1, 14.2, 14.4, 14.5_
  
  - [x] 3.3 Crear migración create_detalle_servicios_table
    - Crear archivo `database/migrations/2024_01_01_000010_create_detalle_servicios_table.php`
    - Definir campos: id, ingreso_id (foreignId), servicio_id (foreignId), timestamps
    - Agregar foreign key ingreso_id → ingresos.id con onDelete cascade
    - Agregar foreign key servicio_id → servicios.id con onDelete cascade
    - Agregar unique constraint en (ingreso_id, servicio_id)
    - _Requirements: 10.1, 10.5, 10.6, 14.1, 14.2, 14.4, 14.5_
  
  - [x] 3.4 Crear migración create_detalle_ventas_table
    - Crear archivo `database/migrations/2024_01_01_000012_create_detalle_ventas_table.php`
    - Definir campos: id, venta_id (foreignId), producto_id (foreignId), cantidad (integer default 1), precio_unitario (decimal 10,2), subtotal (decimal 10,2), timestamps
    - Agregar foreign key venta_id → ventas.id con onDelete cascade
    - Agregar foreign key producto_id → productos.id con onDelete cascade
    - Agregar unique constraint en (venta_id, producto_id)
    - _Requirements: 12.1, 12.5, 12.6, 14.1, 14.2, 14.4, 14.5_

- [x] 4. Checkpoint - Ejecutar y verificar migraciones
  - Ejecutar `php artisan migrate:fresh` para crear todas las tablas
  - Verificar que todas las 12 tablas se crearon correctamente
  - Verificar que todas las foreign keys están definidas
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Crear modelos Eloquent de nivel 1 (sin relaciones complejas)
  - [x] 5.1 Crear modelo Cliente
    - Crear archivo `app/Models/Cliente.php`
    - Definir namespace App\Models y extender Model
    - Definir $fillable: ['dni', 'nombre', 'placa']
    - Definir relaciones: hasMany(CambioAceite), hasMany(Ingreso), hasMany(Venta)
    - _Requirements: 1.2, 1.3, 1.4, 1.5, 14.3, 14.6_
  
  - [x] 5.2 Crear modelo Vehiculo
    - Crear archivo `app/Models/Vehiculo.php`
    - Definir namespace App\Models y extender Model
    - Definir $fillable: ['nombre', 'descripcion', 'precio']
    - Definir $casts: ['precio' => 'decimal:2']
    - Definir relación: hasMany(Ingreso)
    - _Requirements: 2.2, 2.3, 14.3, 14.6_
  
  - [x] 5.3 Crear modelo Trabajador
    - Crear archivo `app/Models/Trabajador.php`
    - Definir namespace App\Models y extender Model
    - Definir $fillable: ['nombre', 'estado']
    - Definir $casts: ['estado' => 'boolean']
    - Definir relaciones: hasMany(CambioAceite), belongsToMany(Ingreso, 'ingreso_trabajadores')
    - _Requirements: 3.2, 3.3, 3.4, 14.3, 14.6_
  
  - [x] 5.4 Crear modelo Producto
    - Crear archivo `app/Models/Producto.php`
    - Definir namespace App\Models y extender Model
    - Definir $fillable: ['nombre', 'precio_compra', 'precio_venta', 'stock', 'inventario']
    - Definir $casts: ['precio_compra' => 'decimal:2', 'precio_venta' => 'decimal:2', 'stock' => 'integer', 'inventario' => 'integer']
    - Definir relaciones: belongsToMany(CambioAceite, 'cambio_productos'), belongsToMany(Venta, 'detalle_ventas')
    - _Requirements: 4.2, 4.3, 4.4, 14.3, 14.6_
  
  - [x] 5.5 Crear modelo Servicio
    - Crear archivo `app/Models/Servicio.php`
    - Definir namespace App\Models y extender Model
    - Definir $fillable: ['nombre', 'precio']
    - Definir $casts: ['precio' => 'decimal:2']
    - Definir relación: belongsToMany(Ingreso, 'detalle_servicios')
    - _Requirements: 5.2, 5.3, 14.3, 14.6_

- [x] 6. Crear modelos Eloquent de nivel 2 (con relaciones a nivel 1)
  - [x] 6.1 Crear modelo CambioAceite
    - Crear archivo `app/Models/CambioAceite.php`
    - Definir namespace App\Models y extender Model
    - Definir $fillable: ['cliente_id', 'trabajador_id', 'fecha']
    - Definir $casts: ['fecha' => 'date']
    - Definir relaciones: belongsTo(Cliente), belongsTo(Trabajador), belongsToMany(Producto, 'cambio_productos')
    - _Requirements: 6.2, 6.3, 6.4, 6.5, 14.3, 14.6_
  
  - [x] 6.2 Crear modelo Ingreso
    - Crear archivo `app/Models/Ingreso.php`
    - Definir namespace App\Models y extender Model
    - Definir $fillable: ['cliente_id', 'vehiculo_id', 'fecha', 'foto']
    - Definir $casts: ['fecha' => 'date']
    - Definir relaciones: belongsTo(Cliente), belongsTo(Vehiculo), belongsToMany(Trabajador, 'ingreso_trabajadores'), belongsToMany(Servicio, 'detalle_servicios')
    - _Requirements: 8.2, 8.3, 8.4, 8.5, 8.6, 14.3, 14.6_
  
  - [x] 6.3 Crear modelo Venta
    - Crear archivo `app/Models/Venta.php`
    - Definir namespace App\Models y extender Model
    - Definir $fillable: ['cliente_id', 'fecha', 'total']
    - Definir $casts: ['fecha' => 'date', 'total' => 'decimal:2']
    - Definir relaciones: belongsTo(Cliente), hasMany(DetalleVenta)
    - _Requirements: 11.2, 11.3, 11.4, 14.3, 14.6_

- [x] 7. Crear modelos Eloquent de nivel 3 (tablas pivote)
  - [x] 7.1 Crear modelo CambioProducto
    - Crear archivo `app/Models/CambioProducto.php`
    - Definir namespace App\Models y extender Model
    - Definir $fillable: ['cambio_aceite_id', 'producto_id', 'cantidad']
    - Definir $casts: ['cantidad' => 'integer']
    - Definir relaciones: belongsTo(CambioAceite), belongsTo(Producto)
    - _Requirements: 7.2, 7.3, 7.4, 14.3, 14.6_
  
  - [x] 7.2 Crear modelo IngresoTrabajador
    - Crear archivo `app/Models/IngresoTrabajador.php`
    - Definir namespace App\Models y extender Model
    - Definir $table: 'ingreso_trabajadores'
    - Definir $fillable: ['ingreso_id', 'trabajador_id']
    - Definir relaciones: belongsTo(Ingreso), belongsTo(Trabajador)
    - _Requirements: 9.2, 9.3, 9.4, 14.3, 14.6_
  
  - [x] 7.3 Crear modelo DetalleServicio
    - Crear archivo `app/Models/DetalleServicio.php`
    - Definir namespace App\Models y extender Model
    - Definir $fillable: ['ingreso_id', 'servicio_id']
    - Definir relaciones: belongsTo(Ingreso), belongsTo(Servicio)
    - _Requirements: 10.2, 10.3, 10.4, 14.3, 14.6_
  
  - [x] 7.4 Crear modelo DetalleVenta
    - Crear archivo `app/Models/DetalleVenta.php`
    - Definir namespace App\Models y extender Model
    - Definir $fillable: ['venta_id', 'producto_id', 'cantidad', 'precio_unitario', 'subtotal']
    - Definir $casts: ['cantidad' => 'integer', 'precio_unitario' => 'decimal:2', 'subtotal' => 'decimal:2']
    - Definir relaciones: belongsTo(Venta), belongsTo(Producto)
    - _Requirements: 12.2, 12.3, 12.4, 14.3, 14.6_

- [x] 8. Crear seeders de nivel 1 (tablas sin dependencias)
  - [x] 8.1 Crear seeder ClienteSeeder
    - Crear archivo `database/seeders/ClienteSeeder.php`
    - Configurar Faker con locale 'es_ES'
    - Generar 20 clientes con DNI único de 8 dígitos, nombres realistas en español, placas formato ABC123
    - Validar formato de DNI (8 dígitos) y placa (3 letras + 3 números)
    - _Requirements: 1.6, 15.1, 15.2, 15.3_
  
  - [x] 8.2 Crear seeder VehiculoSeeder
    - Crear archivo `database/seeders/VehiculoSeeder.php`
    - Generar 15 tipos de vehículos predefinidos con nombres descriptivos, descripciones detalladas y precios entre 5000 y 50000
    - Usar datos del diseño: Sedan Compacto, SUV Mediana, Pickup Doble Cabina, etc.
    - _Requirements: 2.4, 15.4_
  
  - [x] 8.3 Crear seeder TrabajadorSeeder
    - Crear archivo `database/seeders/TrabajadorSeeder.php`
    - Configurar Faker con locale 'es_ES'
    - Generar 10 trabajadores con nombres realistas en español
    - Distribuir estados: 80% activos (true), 20% inactivos (false)
    - _Requirements: 3.5, 15.1_
  
  - [x] 8.4 Crear seeder ProductoSeeder
    - Crear archivo `database/seeders/ProductoSeeder.php`
    - Configurar Faker con locale 'es_ES'
    - Generar 30 productos organizados por categorías (aceites, filtros, frenos, suspensión, neumáticos, eléctrico, correas, líquidos)
    - Usar nombres del diseño: "Aceite Motor 5W-30 Sintético", "Filtro de Aceite", etc.
    - Calcular precio_venta con margen 20-80% sobre precio_compra
    - Generar stock entre 0 y 100, inventario entre 0 y 500
    - Validar que precio_venta > precio_compra
    - _Requirements: 4.5, 15.1, 15.4, 15.5_
  
  - [x] 8.5 Crear seeder ServicioSeeder
    - Crear archivo `database/seeders/ServicioSeeder.php`
    - Generar 12 servicios predefinidos con nombres descriptivos y precios entre 50 y 1000
    - Usar datos del diseño: "Cambio de Aceite y Filtro", "Alineación y Balanceo", etc.
    - _Requirements: 5.4, 15.4_

- [x] 9. Crear seeders de nivel 2 (tablas con foreign keys a nivel 1)
  - [x] 9.1 Crear seeder CambioAceiteSeeder
    - Crear archivo `database/seeders/CambioAceiteSeeder.php`
    - Configurar Faker con locale 'es_ES'
    - Generar 40 cambios de aceite con fechas distribuidas en los últimos 6 meses
    - Asignar clientes aleatorios y solo trabajadores activos
    - _Requirements: 6.8, 15.1, 15.4_
  
  - [x] 9.2 Crear seeder IngresoSeeder
    - Crear archivo `database/seeders/IngresoSeeder.php`
    - Configurar Faker con locale 'es_ES'
    - Generar 50 ingresos con fechas distribuidas en los últimos 3 meses
    - Asignar clientes y vehículos aleatorios
    - Generar foto simulada (ruta) en 30% de los ingresos, null en 70%
    - _Requirements: 8.9, 15.1, 15.4_
  
  - [x] 9.3 Crear seeder VentaSeeder
    - Crear archivo `database/seeders/VentaSeeder.php`
    - Configurar Faker con locale 'es_ES'
    - Generar 60 ventas con fechas distribuidas en los últimos 4 meses
    - Asignar clientes aleatorios
    - Inicializar total en 0 (se calculará en DetalleVentaSeeder)
    - _Requirements: 11.6, 15.1, 15.4_

- [x] 10. Crear seeders de nivel 3 (tablas pivote y detalles)
  - [x] 10.1 Crear seeder CambioProductoSeeder
    - Crear archivo `database/seeders/CambioProductoSeeder.php`
    - Configurar Faker con locale 'es_ES'
    - Para cada cambio de aceite, generar entre 1 y 4 productos
    - Seleccionar solo productos relacionados con aceites y filtros (filtrar por nombre)
    - Generar cantidades entre 1 y 5
    - Evitar duplicados por cambio de aceite (usar unique constraint)
    - _Requirements: 7.7, 15.1, 15.7_
  
  - [x] 10.2 Crear seeder IngresoTrabajadorSeeder
    - Crear archivo `database/seeders/IngresoTrabajadorSeeder.php`
    - Configurar Faker con locale 'es_ES'
    - Para cada ingreso, generar entre 1 y 3 trabajadores
    - Seleccionar solo trabajadores activos
    - Evitar duplicados por ingreso (usar unique constraint)
    - _Requirements: 9.7, 15.1, 15.7_
  
  - [x] 10.3 Crear seeder DetalleServicioSeeder
    - Crear archivo `database/seeders/DetalleServicioSeeder.php`
    - Configurar Faker con locale 'es_ES'
    - Para cada ingreso, generar entre 1 y 5 servicios
    - Evitar duplicados por ingreso (usar unique constraint)
    - _Requirements: 10.7, 15.1, 15.7_
  
  - [x] 10.4 Crear seeder DetalleVentaSeeder
    - Crear archivo `database/seeders/DetalleVentaSeeder.php`
    - Configurar Faker con locale 'es_ES'
    - Para cada venta, generar entre 1 y 6 productos
    - Generar cantidades entre 1 y 10
    - Asignar precio_unitario del precio_venta del producto
    - Calcular subtotal: cantidad × precio_unitario
    - Sumar todos los subtotales y actualizar el total de la venta
    - Evitar duplicados por venta (usar unique constraint)
    - _Requirements: 12.7, 15.1, 15.6, 15.7_

- [x] 11. Configurar DatabaseSeeder principal
  - [x] 11.1 Actualizar DatabaseSeeder
    - Editar archivo `database/seeders/DatabaseSeeder.php`
    - Agregar llamadas a todos los seeders en el orden correcto de dependencias
    - Orden: ClienteSeeder, VehiculoSeeder, TrabajadorSeeder, ProductoSeeder, ServicioSeeder, CambioAceiteSeeder, CambioProductoSeeder, IngresoSeeder, IngresoTrabajadorSeeder, DetalleServicioSeeder, VentaSeeder, DetalleVentaSeeder
    - _Requirements: 13.1, 13.3_

- [x] 12. Checkpoint - Ejecutar y verificar seeders
  - Ejecutar `php artisan migrate:fresh --seed` para recrear base de datos y poblarla
  - Verificar que todos los seeders se ejecutan sin errores
  - Verificar volumen de datos: 20 clientes, 15 vehículos, 10 trabajadores, 30 productos, 12 servicios, 40 cambios de aceite, 50 ingresos, 60 ventas
  - Verificar integridad de datos: DNI únicos, placas válidas, precios de venta > precios de compra, totales de ventas calculados correctamente
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Las migraciones deben ejecutarse en el orden especificado para respetar las dependencias de foreign keys
- Todos los seeders usan Faker con locale 'es_ES' para generar datos en español
- Los unique constraints en tablas pivote previenen duplicados automáticamente
- Los totales de ventas se calculan sumando los subtotales de detalle_ventas
- Las fechas se distribuyen en rangos lógicos: cambios de aceite (6 meses), ingresos (3 meses), ventas (4 meses)
- Solo trabajadores activos se asignan a cambios de aceite e ingresos
- El 30% de los ingresos tienen foto simulada, el 70% tienen foto null
- Los productos para cambios de aceite se filtran por nombre (contienen "Aceite" o "Filtro")
