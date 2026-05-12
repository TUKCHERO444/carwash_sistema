# Design Document

## Overview

Este documento describe el diseño técnico para implementar un sistema completo de gestión de taller mecánico en Laravel. El sistema gestiona 12 entidades principales interconectadas: clientes, vehículos, trabajadores, productos, servicios, cambios de aceite, productos en cambios de aceite, ingresos de vehículos, asignación de trabajadores a ingresos, servicios aplicados a ingresos, ventas y detalles de ventas.

La arquitectura se basa en el patrón MVC de Laravel utilizando Eloquent ORM para la capa de persistencia. El diseño adapta un esquema Oracle existente a MySQL/PostgreSQL, manteniendo la integridad referencial mediante foreign keys y aprovechando las características de Laravel como timestamps automáticos, soft deletes opcionales y relaciones Eloquent.

### Objetivos del Diseño

1. **Integridad Referencial**: Garantizar que todas las relaciones entre entidades mantengan consistencia mediante foreign keys con cascadas apropiadas
2. **Convenciones Laravel**: Seguir estrictamente las convenciones de Laravel para nombres de tablas, modelos y relaciones
3. **Datos Realistas**: Generar datos de prueba que simulen operaciones reales de un taller mecánico
4. **Orden de Ejecución**: Establecer un orden correcto de migraciones que respete las dependencias entre tablas
5. **Adaptación de Tipos**: Mapear tipos de datos Oracle a equivalentes MySQL/PostgreSQL compatibles

## Architecture

### Arquitectura General

El sistema sigue una arquitectura de tres capas:

```
┌─────────────────────────────────────────┐
│         Capa de Presentación            │
│    (Controladores y Vistas Blade)       │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│         Capa de Lógica de Negocio       │
│         (Modelos Eloquent)              │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│         Capa de Persistencia            │
│    (MySQL/PostgreSQL via Eloquent)      │
└─────────────────────────────────────────┘
```

### Diagrama de Relaciones Entre Entidades

```mermaid
erDiagram
    CLIENTES ||--o{ CAMBIO_ACEITES : "realiza"
    CLIENTES ||--o{ INGRESOS : "solicita"
    CLIENTES ||--o{ VENTAS : "compra"
    
    TRABAJADORES ||--o{ CAMBIO_ACEITES : "ejecuta"
    TRABAJADORES ||--o{ INGRESO_TRABAJADORES : "asignado_a"
    
    VEHICULOS ||--o{ INGRESOS : "tipo_de"
    
    PRODUCTOS ||--o{ CAMBIO_PRODUCTOS : "usado_en"
    PRODUCTOS ||--o{ DETALLE_VENTAS : "vendido_en"
    
    SERVICIOS ||--o{ DETALLE_SERVICIOS : "aplicado_en"
    
    CAMBIO_ACEITES ||--o{ CAMBIO_PRODUCTOS : "contiene"
    
    INGRESOS ||--o{ INGRESO_TRABAJADORES : "tiene"
    INGRESOS ||--o{ DETALLE_SERVICIOS : "recibe"
    
    VENTAS ||--o{ DETALLE_VENTAS : "incluye"
    
    CLIENTES {
        bigint id PK
        string dni UK
        string nombre
        string placa
        timestamp created_at
        timestamp updated_at
    }
    
    VEHICULOS {
        bigint id PK
        string nombre
        text descripcion
        decimal precio
        timestamp created_at
        timestamp updated_at
    }
    
    TRABAJADORES {
        bigint id PK
        string nombre
        boolean estado
        timestamp created_at
        timestamp updated_at
    }
    
    PRODUCTOS {
        bigint id PK
        string nombre
        decimal precio_compra
        decimal precio_venta
        integer stock
        integer inventario
        timestamp created_at
        timestamp updated_at
    }
    
    SERVICIOS {
        bigint id PK
        string nombre
        decimal precio
        timestamp created_at
        timestamp updated_at
    }
    
    CAMBIO_ACEITES {
        bigint id PK
        bigint cliente_id FK
        bigint trabajador_id FK
        date fecha
        timestamp created_at
        timestamp updated_at
    }
    
    CAMBIO_PRODUCTOS {
        bigint id PK
        bigint cambio_aceite_id FK
        bigint producto_id FK
        integer cantidad
        timestamp created_at
        timestamp updated_at
    }
    
    INGRESOS {
        bigint id PK
        bigint cliente_id FK
        bigint vehiculo_id FK
        date fecha
        string foto nullable
        timestamp created_at
        timestamp updated_at
    }
    
    INGRESO_TRABAJADORES {
        bigint id PK
        bigint ingreso_id FK
        bigint trabajador_id FK
        timestamp created_at
        timestamp updated_at
    }
    
    DETALLE_SERVICIOS {
        bigint id PK
        bigint ingreso_id FK
        bigint servicio_id FK
        timestamp created_at
        timestamp updated_at
    }
    
    VENTAS {
        bigint id PK
        bigint cliente_id FK
        date fecha
        decimal total
        timestamp created_at
        timestamp updated_at
    }
    
    DETALLE_VENTAS {
        bigint id PK
        bigint venta_id FK
        bigint producto_id FK
        integer cantidad
        decimal precio_unitario
        decimal subtotal
        timestamp created_at
        timestamp updated_at
    }
```

### Patrones de Diseño Utilizados

1. **Active Record Pattern**: Implementado a través de Eloquent ORM
2. **Repository Pattern**: Implícito en los modelos Eloquent
3. **Factory Pattern**: Para la generación de datos de prueba mediante seeders
4. **Migration Pattern**: Para control de versiones del esquema de base de datos

## Components and Interfaces

### Modelos Eloquent

#### 1. Cliente

**Responsabilidad**: Gestionar información de clientes del taller

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $fillable = ['dni', 'nombre', 'placa'];
    
    // Relaciones
    public function cambioAceites(): HasMany;
    public function ingresos(): HasMany;
    public function ventas(): HasMany;
}
```

**Relaciones**:
- `hasMany` → CambioAceite
- `hasMany` → Ingreso
- `hasMany` → Venta

#### 2. Vehiculo

**Responsabilidad**: Catálogo de tipos de vehículos

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehiculo extends Model
{
    protected $fillable = ['nombre', 'descripcion', 'precio'];
    
    protected $casts = [
        'precio' => 'decimal:2'
    ];
    
    // Relaciones
    public function ingresos(): HasMany;

}
```

**Relaciones**:
- `hasMany` → Ingreso

#### 3. Trabajador

**Responsabilidad**: Gestionar información de empleados del taller

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Trabajador extends Model

{
    protected $fillable = ['nombre', 'estado'];
    
    protected $casts = [
        'estado' => 'boolean'
    ];
    
    // Relaciones
    public function cambioAceites(): HasMany;
    public function ingresos(): BelongsToMany;
}
```

**Relaciones**:
- `hasMany` → CambioAceite
- `belongsToMany` → Ingreso (through ingreso_trabajadores)

#### 4. Producto

**Responsabilidad**: Gestionar inventario de productos

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Producto extends Model
{
    protected $fillable = [
        'nombre',
        'precio_compra',
        'precio_venta',
        'stock',
        'inventario'
    ];
    
    protected $casts = [
        'precio_compra' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'stock' => 'integer',
        'inventario' => 'integer'
    ];
    
    // Relaciones
    public function cambioAceites(): BelongsToMany;
    public function ventas(): BelongsToMany;
}
```

**Relaciones**:
- `belongsToMany` → CambioAceite (through cambio_productos)
- `belongsToMany` → Venta (through detalle_ventas)

#### 5. Servicio

**Responsabilidad**: Catálogo de servicios ofrecidos

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Servicio extends Model
{
    protected $fillable = ['nombre', 'precio'];
    
    protected $casts = [
        'precio' => 'decimal:2'
    ];
    
    // Relaciones
    public function ingresos(): BelongsToMany;
}
```

**Relaciones**:
- `belongsToMany` → Ingreso (through detalle_servicios)

#### 6. CambioAceite

**Responsabilidad**: Registrar cambios de aceite realizados

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CambioAceite extends Model
{
    protected $fillable = ['cliente_id', 'trabajador_id', 'fecha'];
    
    protected $casts = [
        'fecha' => 'date'
    ];
    
    // Relaciones
    public function cliente(): BelongsTo;
    public function trabajador(): BelongsTo;
    public function productos(): BelongsToMany;
}
```

**Relaciones**:
- `belongsTo` → Cliente
- `belongsTo` → Trabajador
- `belongsToMany` → Producto (through cambio_productos)

#### 7. CambioProducto

**Responsabilidad**: Tabla pivote con datos adicionales para productos en cambios de aceite

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CambioProducto extends Model
{
    protected $fillable = ['cambio_aceite_id', 'producto_id', 'cantidad'];
    
    protected $casts = [
        'cantidad' => 'integer'
    ];
    
    // Relaciones
    public function cambioAceite(): BelongsTo;
    public function producto(): BelongsTo;
}
```

**Relaciones**:
- `belongsTo` → CambioAceite
- `belongsTo` → Producto

#### 8. Ingreso

**Responsabilidad**: Registrar entrada de vehículos al taller

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingreso extends Model
{
    protected $fillable = ['cliente_id', 'vehiculo_id', 'fecha', 'foto'];
    
    protected $casts = [
        'fecha' => 'date'
    ];
    
    // Relaciones
    public function cliente(): BelongsTo;
    public function vehiculo(): BelongsTo;
    public function trabajadores(): BelongsToMany;
    public function servicios(): BelongsToMany;
}
```

**Relaciones**:
- `belongsTo` → Cliente
- `belongsTo` → Vehiculo
- `belongsToMany` → Trabajador (through ingreso_trabajadores)
- `belongsToMany` → Servicio (through detalle_servicios)

#### 9. IngresoTrabajador

**Responsabilidad**: Tabla pivote para asignación de trabajadores a ingresos

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngresoTrabajador extends Model
{
    protected $table = 'ingreso_trabajadores';
    
    protected $fillable = ['ingreso_id', 'trabajador_id'];
    
    // Relaciones
    public function ingreso(): BelongsTo;
    public function trabajador(): BelongsTo;
}
```

**Relaciones**:
- `belongsTo` → Ingreso
- `belongsTo` → Trabajador

#### 10. DetalleServicio

**Responsabilidad**: Tabla pivote para servicios aplicados a ingresos

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleServicio extends Model
{
    protected $fillable = ['ingreso_id', 'servicio_id'];
    
    // Relaciones
    public function ingreso(): BelongsTo;
    public function servicio(): BelongsTo;
}
```

**Relaciones**:
- `belongsTo` → Ingreso
- `belongsTo` → Servicio

#### 11. Venta

**Responsabilidad**: Registrar transacciones de venta

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    protected $fillable = ['cliente_id', 'fecha', 'total'];
    
    protected $casts = [
        'fecha' => 'date',
        'total' => 'decimal:2'
    ];
    
    // Relaciones
    public function cliente(): BelongsTo;
    public function detalles(): HasMany;
}
```

**Relaciones**:
- `belongsTo` → Cliente
- `hasMany` → DetalleVenta

#### 12. DetalleVenta

**Responsabilidad**: Tabla pivote con datos adicionales para productos vendidos

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleVenta extends Model
{
    protected $fillable = [
        'venta_id',
        'producto_id',
        'cantidad',
        'precio_unitario',
        'subtotal'
    ];
    
    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2'
    ];
    
    // Relaciones
    public function venta(): BelongsTo;
    public function producto(): BelongsTo;
}
```

**Relaciones**:
- `belongsTo` → Venta
- `belongsTo` → Producto

### Resumen de Relaciones

| Modelo | Relación | Modelo Relacionado | Tabla Pivote |
|--------|----------|-------------------|--------------|
| Cliente | hasMany | CambioAceite | - |
| Cliente | hasMany | Ingreso | - |
| Cliente | hasMany | Venta | - |
| Vehiculo | hasMany | Ingreso | - |
| Trabajador | hasMany | CambioAceite | - |
| Trabajador | belongsToMany | Ingreso | ingreso_trabajadores |
| Producto | belongsToMany | CambioAceite | cambio_productos |
| Producto | belongsToMany | Venta | detalle_ventas |
| Servicio | belongsToMany | Ingreso | detalle_servicios |
| CambioAceite | belongsTo | Cliente | - |
| CambioAceite | belongsTo | Trabajador | - |
| CambioAceite | belongsToMany | Producto | cambio_productos |
| Ingreso | belongsTo | Cliente | - |
| Ingreso | belongsTo | Vehiculo | - |
| Ingreso | belongsToMany | Trabajador | ingreso_trabajadores |
| Ingreso | belongsToMany | Servicio | detalle_servicios |
| Venta | belongsTo | Cliente | - |
| Venta | hasMany | DetalleVenta | - |

## Data Models

### Mapeo de Tipos de Datos Oracle a MySQL/PostgreSQL

| Tipo Oracle | Tipo Laravel Migration | MySQL | PostgreSQL | Notas |
|-------------|------------------------|-------|------------|-------|
| NUMBER(*, 0) | `id()` o `bigInteger()` | BIGINT UNSIGNED | BIGSERIAL | Clave primaria autoincremental |
| VARCHAR2(n) | `string('campo', n)` | VARCHAR(n) | VARCHAR(n) | Texto de longitud variable |
| CLOB | `text('campo')` | TEXT | TEXT | Texto largo |
| NUMBER(p, s) | `decimal('campo', p, s)` | DECIMAL(p, s) | NUMERIC(p, s) | Números decimales |
| DATE | `date('campo')` | DATE | DATE | Solo fecha |
| TIMESTAMP | `timestamp('campo')` | TIMESTAMP | TIMESTAMP | Fecha y hora |
| NUMBER(1, 0) | `boolean('campo')` | TINYINT(1) | BOOLEAN | Valores booleanos |

### Estructura Detallada de Migraciones

#### 1. Migración: create_clientes_table

**Timestamp**: `2024_01_01_000001`

```php
Schema::create('clientes', function (Blueprint $table) {
    $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT
    $table->string('dni', 8)->unique(); // VARCHAR(8) UNIQUE
    $table->string('nombre', 100); // VARCHAR(100)
    $table->string('placa', 7); // VARCHAR(7) - Formato: ABC123
    $table->timestamps(); // created_at, updated_at TIMESTAMP
});
```

**Índices**:
- PRIMARY KEY: `id`
- UNIQUE KEY: `dni`

#### 2. Migración: create_vehiculos_table

**Timestamp**: `2024_01_01_000002`

```php
Schema::create('vehiculos', function (Blueprint $table) {
    $table->id();
    $table->string('nombre', 100);
    $table->text('descripcion');
    $table->decimal('precio', 10, 2); // DECIMAL(10,2)
    $table->timestamps();
});
```

**Índices**:
- PRIMARY KEY: `id`

#### 3. Migración: create_trabajadores_table

**Timestamp**: `2024_01_01_000003`

```php
Schema::create('trabajadores', function (Blueprint $table) {
    $table->id();
    $table->string('nombre', 100);
    $table->boolean('estado')->default(true); // TINYINT(1) DEFAULT 1
    $table->timestamps();
});
```

**Índices**:
- PRIMARY KEY: `id`
- INDEX: `estado` (para filtrado rápido)

#### 4. Migración: create_productos_table

**Timestamp**: `2024_01_01_000004`

```php
Schema::create('productos', function (Blueprint $table) {
    $table->id();
    $table->string('nombre', 150);
    $table->decimal('precio_compra', 10, 2);
    $table->decimal('precio_venta', 10, 2);
    $table->integer('stock')->default(0); // INT DEFAULT 0
    $table->integer('inventario')->default(0); // INT DEFAULT 0
    $table->timestamps();
});
```

**Índices**:
- PRIMARY KEY: `id`
- INDEX: `stock` (para consultas de inventario)

#### 5. Migración: create_servicios_table

**Timestamp**: `2024_01_01_000005`

```php
Schema::create('servicios', function (Blueprint $table) {
    $table->id();
    $table->string('nombre', 100);
    $table->decimal('precio', 10, 2);
    $table->timestamps();
});
```

**Índices**:
- PRIMARY KEY: `id`

#### 6. Migración: create_cambio_aceites_table

**Timestamp**: `2024_01_01_000006`

**Dependencias**: clientes, trabajadores

```php
Schema::create('cambio_aceites', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cliente_id')
          ->constrained('clientes')
          ->onDelete('cascade');
    $table->foreignId('trabajador_id')
          ->constrained('trabajadores')
          ->onDelete('cascade');
    $table->date('fecha');
    $table->timestamps();
});
```

**Índices**:
- PRIMARY KEY: `id`
- FOREIGN KEY: `cliente_id` → `clientes(id)` ON DELETE CASCADE
- FOREIGN KEY: `trabajador_id` → `trabajadores(id)` ON DELETE CASCADE
- INDEX: `fecha` (para consultas por rango de fechas)

#### 7. Migración: create_cambio_productos_table

**Timestamp**: `2024_01_01_000007`

**Dependencias**: cambio_aceites, productos

```php
Schema::create('cambio_productos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cambio_aceite_id')
          ->constrained('cambio_aceites')
          ->onDelete('cascade');
    $table->foreignId('producto_id')
          ->constrained('productos')
          ->onDelete('cascade');
    $table->integer('cantidad')->default(1);
    $table->timestamps();
    
    // Evitar duplicados en la misma relación
    $table->unique(['cambio_aceite_id', 'producto_id']);
});
```

**Índices**:
- PRIMARY KEY: `id`
- FOREIGN KEY: `cambio_aceite_id` → `cambio_aceites(id)` ON DELETE CASCADE
- FOREIGN KEY: `producto_id` → `productos(id)` ON DELETE CASCADE
- UNIQUE KEY: `(cambio_aceite_id, producto_id)`

#### 8. Migración: create_ingresos_table

**Timestamp**: `2024_01_01_000008`

**Dependencias**: clientes, vehiculos

```php
Schema::create('ingresos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cliente_id')
          ->constrained('clientes')
          ->onDelete('cascade');
    $table->foreignId('vehiculo_id')
          ->constrained('vehiculos')
          ->onDelete('cascade');
    $table->date('fecha');
    $table->string('foto', 255)->nullable();
    $table->timestamps();
});
```

**Índices**:
- PRIMARY KEY: `id`
- FOREIGN KEY: `cliente_id` → `clientes(id)` ON DELETE CASCADE
- FOREIGN KEY: `vehiculo_id` → `vehiculos(id)` ON DELETE CASCADE
- INDEX: `fecha` (para consultas por rango de fechas)

#### 9. Migración: create_ingreso_trabajadores_table

**Timestamp**: `2024_01_01_000009`

**Dependencias**: ingresos, trabajadores

```php
Schema::create('ingreso_trabajadores', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ingreso_id')
          ->constrained('ingresos')
          ->onDelete('cascade');
    $table->foreignId('trabajador_id')
          ->constrained('trabajadores')
          ->onDelete('cascade');
    $table->timestamps();
    
    // Evitar duplicados en la misma relación
    $table->unique(['ingreso_id', 'trabajador_id']);
});
```

**Índices**:
- PRIMARY KEY: `id`
- FOREIGN KEY: `ingreso_id` → `ingresos(id)` ON DELETE CASCADE
- FOREIGN KEY: `trabajador_id` → `trabajadores(id)` ON DELETE CASCADE
- UNIQUE KEY: `(ingreso_id, trabajador_id)`

#### 10. Migración: create_detalle_servicios_table

**Timestamp**: `2024_01_01_000010`

**Dependencias**: ingresos, servicios

```php
Schema::create('detalle_servicios', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ingreso_id')
          ->constrained('ingresos')
          ->onDelete('cascade');
    $table->foreignId('servicio_id')
          ->constrained('servicios')
          ->onDelete('cascade');
    $table->timestamps();
    
    // Evitar duplicados en la misma relación
    $table->unique(['ingreso_id', 'servicio_id']);
});
```

**Índices**:
- PRIMARY KEY: `id`
- FOREIGN KEY: `ingreso_id` → `ingresos(id)` ON DELETE CASCADE
- FOREIGN KEY: `servicio_id` → `servicios(id)` ON DELETE CASCADE
- UNIQUE KEY: `(ingreso_id, servicio_id)`

#### 11. Migración: create_ventas_table

**Timestamp**: `2024_01_01_000011`

**Dependencias**: clientes

```php
Schema::create('ventas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cliente_id')
          ->constrained('clientes')
          ->onDelete('cascade');
    $table->date('fecha');
    $table->decimal('total', 10, 2)->default(0);
    $table->timestamps();
});
```

**Índices**:
- PRIMARY KEY: `id`
- FOREIGN KEY: `cliente_id` → `clientes(id)` ON DELETE CASCADE
- INDEX: `fecha` (para consultas por rango de fechas)

#### 12. Migración: create_detalle_ventas_table

**Timestamp**: `2024_01_01_000012`

**Dependencias**: ventas, productos

```php
Schema::create('detalle_ventas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('venta_id')
          ->constrained('ventas')
          ->onDelete('cascade');
    $table->foreignId('producto_id')
          ->constrained('productos')
          ->onDelete('cascade');
    $table->integer('cantidad')->default(1);
    $table->decimal('precio_unitario', 10, 2);
    $table->decimal('subtotal', 10, 2);
    $table->timestamps();
    
    // Evitar duplicados en la misma relación
    $table->unique(['venta_id', 'producto_id']);
});
```

**Índices**:
- PRIMARY KEY: `id`
- FOREIGN KEY: `venta_id` → `ventas(id)` ON DELETE CASCADE
- FOREIGN KEY: `producto_id` → `productos(id)` ON DELETE CASCADE
- UNIQUE KEY: `(venta_id, producto_id)`

### Orden de Ejecución de Migraciones

El orden de ejecución está determinado por las dependencias de foreign keys:

```
Nivel 1 (Sin dependencias):
  ├── 2024_01_01_000001_create_clientes_table
  ├── 2024_01_01_000002_create_vehiculos_table
  ├── 2024_01_01_000003_create_trabajadores_table
  ├── 2024_01_01_000004_create_productos_table
  └── 2024_01_01_000005_create_servicios_table

Nivel 2 (Dependen de Nivel 1):
  ├── 2024_01_01_000006_create_cambio_aceites_table (clientes, trabajadores)
  ├── 2024_01_01_000008_create_ingresos_table (clientes, vehiculos)
  └── 2024_01_01_000011_create_ventas_table (clientes)

Nivel 3 (Dependen de Nivel 2):
  ├── 2024_01_01_000007_create_cambio_productos_table (cambio_aceites, productos)
  ├── 2024_01_01_000009_create_ingreso_trabajadores_table (ingresos, trabajadores)
  ├── 2024_01_01_000010_create_detalle_servicios_table (ingresos, servicios)
  └── 2024_01_01_000012_create_detalle_ventas_table (ventas, productos)
```

**Regla de Orden**: Una tabla solo puede crearse después de que todas las tablas referenciadas en sus foreign keys hayan sido creadas.

## Error Handling

### Estrategias de Manejo de Errores

#### 1. Violaciones de Integridad Referencial

**Escenario**: Intento de insertar un registro con foreign key inválida

**Manejo**:
```php
try {
    $cambioAceite = CambioAceite::create([
        'cliente_id' => $clienteId,
        'trabajador_id' => $trabajadorId,
        'fecha' => now()
    ]);
} catch (\Illuminate\Database\QueryException $e) {
    if ($e->getCode() === '23000') {
        // Violación de constraint de integridad
        throw new \Exception('Cliente o trabajador no existe');
    }
    throw $e;
}
```

#### 2. Violaciones de Unicidad

**Escenario**: Intento de insertar DNI duplicado en clientes

**Manejo**:
```php
try {
    $cliente = Cliente::create([
        'dni' => $dni,
        'nombre' => $nombre,
        'placa' => $placa
    ]);
} catch (\Illuminate\Database\QueryException $e) {
    if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'dni')) {
        throw new \Exception('El DNI ya está registrado');
    }
    throw $e;
}
```

#### 3. Errores en Migraciones

**Escenario**: Fallo al ejecutar una migración

**Manejo**:
- Laravel automáticamente hace rollback de la migración fallida
- Verificar el orden de ejecución de migraciones
- Revisar que todas las tablas referenciadas existan antes de crear foreign keys

#### 4. Errores en Seeders

**Escenario**: Fallo al generar datos de prueba

**Manejo**:
```php
try {
    DB::transaction(function () {
        // Generar datos
        Cliente::factory()->count(20)->create();
    });
} catch (\Exception $e) {
    Log::error('Error en seeder: ' . $e->getMessage());
    throw $e;
}
```

### Validaciones a Nivel de Aplicación

#### 1. Validación de DNI

```php
'dni' => 'required|digits:8|unique:clientes,dni'
```

#### 2. Validación de Placa

```php
'placa' => 'required|regex:/^[A-Z]{3}[0-9]{3}$/'
```

#### 3. Validación de Precios

```php
'precio_compra' => 'required|numeric|min:0',
'precio_venta' => 'required|numeric|gt:precio_compra'
```

#### 4. Validación de Stock

```php
'stock' => 'required|integer|min:0',
'inventario' => 'required|integer|min:0'
```

## Testing Strategy

### Enfoque de Testing

Este proyecto implementa un esquema de base de datos con migraciones, modelos y seeders. **Property-based testing NO es aplicable** para este tipo de implementación porque:

1. **Infrastructure as Code**: Las migraciones son configuración declarativa, no funciones con inputs/outputs
2. **No hay lógica de transformación**: Los modelos Eloquent son principalmente mapeos ORM sin lógica de negocio compleja
3. **Seeders son generadores de datos**: No hay propiedades universales que verificar, solo generación de datos de ejemplo

### Estrategia de Testing Apropiada

#### 1. **Unit Tests para Modelos**

Verificar que las relaciones Eloquent están correctamente definidas:

```php
/** @test */
public function cliente_tiene_relacion_con_cambio_aceites()
{
    $cliente = Cliente::factory()->create();
    $cambioAceite = CambioAceite::factory()->create(['cliente_id' => $cliente->id]);
    
    $this->assertTrue($cliente->cambioAceites->contains($cambioAceite));
}

/** @test */
public function producto_precio_venta_mayor_que_precio_compra()
{
    $producto = Producto::factory()->create([
        'precio_compra' => 100,
        'precio_venta' => 150
    ]);
    
    $this->assertGreaterThan($producto->precio_compra, $producto->precio_venta);
}
```

#### 2. **Integration Tests para Migraciones**

Verificar que las migraciones se ejecutan sin errores y crean la estructura correcta:

```php
/** @test */
public function migraciones_se_ejecutan_en_orden_correcto()
{
    Artisan::call('migrate:fresh');
    
    $this->assertTrue(Schema::hasTable('clientes'));
    $this->assertTrue(Schema::hasTable('cambio_aceites'));
    $this->assertTrue(Schema::hasColumn('cambio_aceites', 'cliente_id'));
}

/** @test */
public function foreign_keys_estan_correctamente_definidas()
{
    $cliente = Cliente::factory()->create();
    $trabajador = Trabajador::factory()->create();
    
    $cambioAceite = CambioAceite::create([
        'cliente_id' => $cliente->id,
        'trabajador_id' => $trabajador->id,
        'fecha' => now()
    ]);
    
    $this->assertDatabaseHas('cambio_aceites', [
        'id' => $cambioAceite->id,
        'cliente_id' => $cliente->id
    ]);
}
```

#### 3. **Schema Tests**

Verificar que la estructura de las tablas coincide con las especificaciones:

```php
/** @test */
public function tabla_clientes_tiene_estructura_correcta()
{
    $this->assertTrue(Schema::hasColumns('clientes', [
        'id', 'dni', 'nombre', 'placa', 'created_at', 'updated_at'
    ]));
}

/** @test */
public function tabla_productos_tiene_indices_correctos()
{
    $indexes = Schema::getIndexes('productos');
    $indexNames = array_column($indexes, 'name');
    
    $this->assertContains('productos_stock_index', $indexNames);
}
```

#### 4. **Seeder Tests**

Verificar que los seeders generan datos válidos:

```php
/** @test */
public function seeder_genera_clientes_con_dni_valido()
{
    Artisan::call('db:seed', ['--class' => 'ClienteSeeder']);
    
    $clientes = Cliente::all();
    
    $this->assertGreaterThanOrEqual(20, $clientes->count());
    
    foreach ($clientes as $cliente) {
        $this->assertMatchesRegularExpression('/^\d{8}$/', $cliente->dni);
    }
}

/** @test */
public function seeder_genera_ventas_con_totales_correctos()
{
    Artisan::call('db:seed', ['--class' => 'VentaSeeder']);
    
    $ventas = Venta::with('detalles')->get();
    
    foreach ($ventas as $venta) {
        $totalCalculado = $venta->detalles->sum('subtotal');
        $this->assertEquals($totalCalculado, $venta->total);
    }
}
```

#### 5. **Constraint Tests**

Verificar que las restricciones de base de datos funcionan correctamente:

```php
/** @test */
public function no_permite_dni_duplicado()
{
    Cliente::factory()->create(['dni' => '12345678']);
    
    $this->expectException(\Illuminate\Database\QueryException::class);
    Cliente::factory()->create(['dni' => '12345678']);
}

/** @test */
public function cascade_delete_funciona_correctamente()
{
    $cliente = Cliente::factory()->create();
    $cambioAceite = CambioAceite::factory()->create(['cliente_id' => $cliente->id]);
    
    $cliente->delete();
    
    $this->assertDatabaseMissing('cambio_aceites', ['id' => $cambioAceite->id]);
}
```

### Cobertura de Testing

| Componente | Tipo de Test | Cobertura Objetivo |
|------------|--------------|-------------------|
| Modelos Eloquent | Unit Tests | 100% de relaciones |
| Migraciones | Integration Tests | Todas las tablas y foreign keys |
| Seeders | Integration Tests | Validación de datos generados |
| Constraints | Integration Tests | Todas las restricciones |
| Índices | Schema Tests | Todos los índices definidos |

### Herramientas de Testing

- **PHPUnit**: Framework principal de testing
- **Laravel Testing Utilities**: Helpers para testing de base de datos
- **Database Transactions**: Para rollback automático después de cada test
- **Factories**: Para generación de datos de prueba

### Configuración de Testing

```php
// phpunit.xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Usar SQLite en memoria para tests rápidos y aislados.



## Seeder Design and Implementation Strategy

### Estrategia General de Seeders

Los seeders generarán datos realistas en español utilizando Faker con locale 'es_ES'. La estrategia sigue estos principios:

1. **Orden de Ejecución**: Respetar dependencias de foreign keys
2. **Datos Realistas**: Generar información que simule operaciones reales de un taller
3. **Consistencia**: Mantener relaciones lógicas entre entidades
4. **Volumen Adecuado**: Suficientes datos para pruebas sin sobrecargar la base de datos

### Orden de Ejecución de Seeders

```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    $this->call([
        ClienteSeeder::class,        // Nivel 1
        VehiculoSeeder::class,       // Nivel 1
        TrabajadorSeeder::class,     // Nivel 1
        ProductoSeeder::class,       // Nivel 1
        ServicioSeeder::class,       // Nivel 1
        CambioAceiteSeeder::class,   // Nivel 2
        CambioProductoSeeder::class, // Nivel 3
        IngresoSeeder::class,        // Nivel 2
        IngresoTrabajadorSeeder::class, // Nivel 3
        DetalleServicioSeeder::class,   // Nivel 3
        VentaSeeder::class,          // Nivel 2
        DetalleVentaSeeder::class,   // Nivel 3
    ]);
}
```

### Diseño Detallado de Cada Seeder

#### 1. ClienteSeeder

**Objetivo**: Generar 20 clientes con datos realistas

**Estrategia**:
```php
public function run(): void
{
    $faker = \Faker\Factory::create('es_ES');
    
    for ($i = 0; $i < 20; $i++) {
        Cliente::create([
            'dni' => $faker->unique()->numerify('########'),
            'nombre' => $faker->name(),
            'placa' => $this->generarPlaca($faker)
        ]);
    }
}

private function generarPlaca($faker): string
{
    $letras = strtoupper($faker->lexify('???'));
    $numeros = $faker->numerify('###');
    return $letras . $numeros; // Formato: ABC123
}
```

**Datos Generados**:
- DNI: 8 dígitos únicos (ej: 12345678, 87654321)
- Nombre: Nombres españoles realistas (ej: "Juan García López", "María Rodríguez")
- Placa: Formato válido 3 letras + 3 números (ej: "ABC123", "XYZ789")

#### 2. VehiculoSeeder

**Objetivo**: Generar 15 tipos de vehículos del catálogo

**Estrategia**:
```php
public function run(): void
{
    $vehiculos = [
        ['nombre' => 'Sedan Compacto', 'descripcion' => 'Vehículo sedan de 4 puertas, ideal para ciudad', 'precio' => 15000],
        ['nombre' => 'SUV Mediana', 'descripcion' => 'SUV de 5 pasajeros con tracción 4x4', 'precio' => 28000],
        ['nombre' => 'Pickup Doble Cabina', 'descripcion' => 'Camioneta pickup con capacidad de carga de 1 tonelada', 'precio' => 32000],
        ['nombre' => 'Hatchback', 'descripcion' => 'Auto compacto de 5 puertas, económico', 'precio' => 12000],
        ['nombre' => 'Minivan', 'descripcion' => 'Vehículo familiar de 7 pasajeros', 'precio' => 25000],
        ['nombre' => 'Sedan Ejecutivo', 'descripcion' => 'Sedan de lujo con acabados premium', 'precio' => 45000],
        ['nombre' => 'SUV Compacta', 'descripcion' => 'SUV urbana de 5 pasajeros', 'precio' => 22000],
        ['nombre' => 'Coupe Deportivo', 'descripcion' => 'Auto deportivo de 2 puertas', 'precio' => 50000],
        ['nombre' => 'Station Wagon', 'descripcion' => 'Familiar con amplio espacio de carga', 'precio' => 20000],
        ['nombre' => 'Pickup Simple', 'descripcion' => 'Camioneta pickup de cabina simple', 'precio' => 18000],
        ['nombre' => 'Crossover', 'descripcion' => 'Vehículo crossover urbano', 'precio' => 24000],
        ['nombre' => 'Van Comercial', 'descripcion' => 'Furgoneta para transporte de carga', 'precio' => 30000],
        ['nombre' => 'Sedan Medio', 'descripcion' => 'Sedan de tamaño medio, confortable', 'precio' => 18000],
        ['nombre' => 'SUV Grande', 'descripcion' => 'SUV de 7 pasajeros, alto rendimiento', 'precio' => 42000],
        ['nombre' => 'Convertible', 'descripcion' => 'Auto descapotable de 2 puertas', 'precio' => 48000]
    ];
    
    foreach ($vehiculos as $vehiculo) {
        Vehiculo::create($vehiculo);
    }
}
```

**Datos Generados**:
- 15 tipos de vehículos con nombres descriptivos
- Descripciones detalladas de características
- Precios entre 12,000 y 50,000

#### 3. TrabajadorSeeder

**Objetivo**: Generar 10 trabajadores con distribución 80/20 activos/inactivos

**Estrategia**:
```php
public function run(): void
{
    $faker = \Faker\Factory::create('es_ES');
    
    for ($i = 0; $i < 10; $i++) {
        Trabajador::create([
            'nombre' => $faker->name(),
            'estado' => $i < 8 // 80% activos (índices 0-7), 20% inactivos (8-9)
        ]);
    }
}
```

**Datos Generados**:
- 10 trabajadores con nombres españoles
- 8 trabajadores activos (estado = true)
- 2 trabajadores inactivos (estado = false)

#### 4. ProductoSeeder

**Objetivo**: Generar 30 productos con precios, stock e inventario realistas

**Estrategia**:
```php
public function run(): void
{
    $productos = [
        // Aceites y lubricantes
        ['nombre' => 'Aceite Motor 5W-30 Sintético', 'categoria' => 'aceites'],
        ['nombre' => 'Aceite Motor 10W-40 Semi-Sintético', 'categoria' => 'aceites'],
        ['nombre' => 'Aceite Motor 20W-50 Mineral', 'categoria' => 'aceites'],
        ['nombre' => 'Aceite Transmisión ATF', 'categoria' => 'aceites'],
        ['nombre' => 'Aceite Hidráulico', 'categoria' => 'aceites'],
        
        // Filtros
        ['nombre' => 'Filtro de Aceite', 'categoria' => 'filtros'],
        ['nombre' => 'Filtro de Aire', 'categoria' => 'filtros'],
        ['nombre' => 'Filtro de Combustible', 'categoria' => 'filtros'],
        ['nombre' => 'Filtro de Cabina', 'categoria' => 'filtros'],
        
        // Frenos
        ['nombre' => 'Pastillas de Freno Delanteras', 'categoria' => 'frenos'],
        ['nombre' => 'Pastillas de Freno Traseras', 'categoria' => 'frenos'],
        ['nombre' => 'Discos de Freno Delanteros', 'categoria' => 'frenos'],
        ['nombre' => 'Discos de Freno Traseros', 'categoria' => 'frenos'],
        ['nombre' => 'Líquido de Frenos DOT 4', 'categoria' => 'frenos'],
        
        // Suspensión
        ['nombre' => 'Amortiguador Delantero', 'categoria' => 'suspension'],
        ['nombre' => 'Amortiguador Trasero', 'categoria' => 'suspension'],
        ['nombre' => 'Resorte de Suspensión', 'categoria' => 'suspension'],
        
        // Neumáticos
        ['nombre' => 'Neumático 185/65 R15', 'categoria' => 'neumaticos'],
        ['nombre' => 'Neumático 195/55 R16', 'categoria' => 'neumaticos'],
        ['nombre' => 'Neumático 205/55 R17', 'categoria' => 'neumaticos'],
        
        // Batería y eléctrico
        ['nombre' => 'Batería 12V 45Ah', 'categoria' => 'electrico'],
        ['nombre' => 'Batería 12V 65Ah', 'categoria' => 'electrico'],
        ['nombre' => 'Bujías de Encendido (set 4)', 'categoria' => 'electrico'],
        ['nombre' => 'Alternador', 'categoria' => 'electrico'],
        
        // Correas y cadenas
        ['nombre' => 'Correa de Distribución', 'categoria' => 'correas'],
        ['nombre' => 'Correa de Accesorios', 'categoria' => 'correas'],
        ['nombre' => 'Tensor de Correa', 'categoria' => 'correas'],
        
        // Líquidos
        ['nombre' => 'Refrigerante Motor', 'categoria' => 'liquidos'],
        ['nombre' => 'Líquido Limpiaparabrisas', 'categoria' => 'liquidos'],
        ['nombre' => 'Aditivo Limpiador Motor', 'categoria' => 'liquidos']
    ];
    
    $faker = \Faker\Factory::create('es_ES');
    
    foreach ($productos as $producto) {
        $precioCompra = $faker->randomFloat(2, 10, 500);
        $margen = $faker->randomFloat(2, 1.2, 1.8); // Margen entre 20% y 80%
        
        Producto::create([
            'nombre' => $producto['nombre'],
            'precio_compra' => $precioCompra,
            'precio_venta' => round($precioCompra * $margen, 2),
            'stock' => $faker->numberBetween(0, 100),
            'inventario' => $faker->numberBetween(0, 500)
        ]);
    }
}
```

**Datos Generados**:
- 30 productos organizados por categorías
- Precio de compra: entre 10 y 500
- Precio de venta: 20% a 80% más que precio de compra
- Stock: entre 0 y 100 unidades
- Inventario: entre 0 y 500 unidades

#### 5. ServicioSeeder

**Objetivo**: Generar 12 servicios con precios realistas

**Estrategia**:
```php
public function run(): void
{
    $servicios = [
        ['nombre' => 'Cambio de Aceite y Filtro', 'precio' => 80],
        ['nombre' => 'Alineación y Balanceo', 'precio' => 60],
        ['nombre' => 'Revisión de Frenos', 'precio' => 50],
        ['nombre' => 'Cambio de Pastillas de Freno', 'precio' => 150],
        ['nombre' => 'Cambio de Neumáticos', 'precio' => 100],
        ['nombre' => 'Diagnóstico Computarizado', 'precio' => 70],
        ['nombre' => 'Cambio de Batería', 'precio' => 120],
        ['nombre' => 'Revisión de Suspensión', 'precio' => 90],
        ['nombre' => 'Cambio de Correa de Distribución', 'precio' => 350],
        ['nombre' => 'Limpieza de Inyectores', 'precio' => 180],
        ['nombre' => 'Cambio de Líquido de Frenos', 'precio' => 65],
        ['nombre' => 'Mantenimiento Preventivo Completo', 'precio' => 250]
    ];
    
    foreach ($servicios as $servicio) {
        Servicio::create($servicio);
    }
}
```

**Datos Generados**:
- 12 servicios típicos de taller mecánico
- Precios entre 50 y 350

#### 6. CambioAceiteSeeder

**Objetivo**: Generar 40 cambios de aceite distribuidos en los últimos 6 meses

**Estrategia**:
```php
public function run(): void
{
    $faker = \Faker\Factory::create('es_ES');
    $clientes = Cliente::all();
    $trabajadores = Trabajador::where('estado', true)->get();
    
    for ($i = 0; $i < 40; $i++) {
        CambioAceite::create([
            'cliente_id' => $clientes->random()->id,
            'trabajador_id' => $trabajadores->random()->id,
            'fecha' => $faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d')
        ]);
    }
}
```

**Datos Generados**:
- 40 cambios de aceite
- Fechas distribuidas en los últimos 6 meses
- Solo trabajadores activos asignados

#### 7. CambioProductoSeeder

**Objetivo**: Generar entre 1 y 4 productos por cada cambio de aceite

**Estrategia**:
```php
public function run(): void
{
    $faker = \Faker\Factory::create('es_ES');
    $cambioAceites = CambioAceite::all();
    $productosAceite = Producto::where('nombre', 'like', '%Aceite%')
                                ->orWhere('nombre', 'like', '%Filtro%')
                                ->get();
    
    foreach ($cambioAceites as $cambioAceite) {
        $cantidadProductos = $faker->numberBetween(1, 4);
        $productosSeleccionados = $productosAceite->random($cantidadProductos);
        
        foreach ($productosSeleccionados as $producto) {
            CambioProducto::create([
                'cambio_aceite_id' => $cambioAceite->id,
                'producto_id' => $producto->id,
                'cantidad' => $faker->numberBetween(1, 5)
            ]);
        }
    }
}
```

**Datos Generados**:
- Entre 1 y 4 productos por cambio de aceite
- Solo productos relacionados con aceites y filtros
- Cantidades entre 1 y 5 unidades
- Sin duplicados por cambio de aceite (unique constraint)

#### 8. IngresoSeeder

**Objetivo**: Generar 50 ingresos distribuidos en los últimos 3 meses, 30% con fotos

**Estrategia**:
```php
public function run(): void
{
    $faker = \Faker\Factory::create('es_ES');
    $clientes = Cliente::all();
    $vehiculos = Vehiculo::all();
    
    for ($i = 0; $i < 50; $i++) {
        $tieneFoto = $faker->boolean(30); // 30% de probabilidad
        
        Ingreso::create([
            'cliente_id' => $clientes->random()->id,
            'vehiculo_id' => $vehiculos->random()->id,
            'fecha' => $faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'foto' => $tieneFoto ? 'fotos/ingreso_' . ($i + 1) . '.jpg' : null
        ]);
    }
}
```

**Datos Generados**:
- 50 ingresos de vehículos
- Fechas distribuidas en los últimos 3 meses
- 30% con foto simulada (ruta de archivo)
- 70% sin foto (null)

#### 9. IngresoTrabajadorSeeder

**Objetivo**: Generar entre 1 y 3 trabajadores por ingreso sin duplicados

**Estrategia**:
```php
public function run(): void
{
    $faker = \Faker\Factory::create('es_ES');
    $ingresos = Ingreso::all();
    $trabajadores = Trabajador::where('estado', true)->get();
    
    foreach ($ingresos as $ingreso) {
        $cantidadTrabajadores = $faker->numberBetween(1, 3);
        $trabajadoresSeleccionados = $trabajadores->random(
            min($cantidadTrabajadores, $trabajadores->count())
        );
        
        foreach ($trabajadoresSeleccionados as $trabajador) {
            IngresoTrabajador::create([
                'ingreso_id' => $ingreso->id,
                'trabajador_id' => $trabajador->id
            ]);
        }
    }
}
```

**Datos Generados**:
- Entre 1 y 3 trabajadores por ingreso
- Solo trabajadores activos
- Sin duplicados por ingreso (unique constraint)

#### 10. DetalleServicioSeeder

**Objetivo**: Generar entre 1 y 5 servicios por ingreso sin duplicados

**Estrategia**:
```php
public function run(): void
{
    $faker = \Faker\Factory::create('es_ES');
    $ingresos = Ingreso::all();
    $servicios = Servicio::all();
    
    foreach ($ingresos as $ingreso) {
        $cantidadServicios = $faker->numberBetween(1, 5);
        $serviciosSeleccionados = $servicios->random(
            min($cantidadServicios, $servicios->count())
        );
        
        foreach ($serviciosSeleccionados as $servicio) {
            DetalleServicio::create([
                'ingreso_id' => $ingreso->id,
                'servicio_id' => $servicio->id
            ]);
        }
    }
}
```

**Datos Generados**:
- Entre 1 y 5 servicios por ingreso
- Sin duplicados por ingreso (unique constraint)

#### 11. VentaSeeder

**Objetivo**: Generar 60 ventas distribuidas en los últimos 4 meses con totales calculados

**Estrategia**:
```php
public function run(): void
{
    $faker = \Faker\Factory::create('es_ES');
    $clientes = Cliente::all();
    
    for ($i = 0; $i < 60; $i++) {
        Venta::create([
            'cliente_id' => $clientes->random()->id,
            'fecha' => $faker->dateTimeBetween('-4 months', 'now')->format('Y-m-d'),
            'total' => 0 // Se calculará en DetalleVentaSeeder
        ]);
    }
}
```

**Datos Generados**:
- 60 ventas
- Fechas distribuidas en los últimos 4 meses
- Total inicializado en 0 (se actualizará después)

#### 12. DetalleVentaSeeder

**Objetivo**: Generar entre 1 y 6 productos por venta con cálculo correcto de subtotales y totales

**Estrategia**:
```php
public function run(): void
{
    $faker = \Faker\Factory::create('es_ES');
    $ventas = Venta::all();
    $productos = Producto::all();
    
    foreach ($ventas as $venta) {
        $cantidadProductos = $faker->numberBetween(1, 6);
        $productosSeleccionados = $productos->random(
            min($cantidadProductos, $productos->count())
        );
        
        $totalVenta = 0;
        
        foreach ($productosSeleccionados as $producto) {
            $cantidad = $faker->numberBetween(1, 10);
            $precioUnitario = $producto->precio_venta;
            $subtotal = $cantidad * $precioUnitario;
            
            DetalleVenta::create([
                'venta_id' => $venta->id,
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'subtotal' => $subtotal
            ]);
            
            $totalVenta += $subtotal;
        }
        
        // Actualizar el total de la venta
        $venta->update(['total' => $totalVenta]);
    }
}
```

**Datos Generados**:
- Entre 1 y 6 productos por venta
- Cantidades entre 1 y 10 unidades
- Precio unitario tomado del precio de venta del producto
- Subtotal calculado: cantidad × precio_unitario
- Total de venta calculado: suma de todos los subtotales
- Sin duplicados por venta (unique constraint)

### Configuración de Faker para Español

```php
// En cada seeder
$faker = \Faker\Factory::create('es_ES');
```

Esto garantiza:
- Nombres en español
- Formatos de fecha apropiados
- Textos en español

### Validaciones en Seeders

Cada seeder debe incluir validaciones para garantizar datos consistentes:

```php
// Ejemplo: Validar que precio_venta > precio_compra
if ($precioVenta <= $precioCompra) {
    throw new \Exception('Precio de venta debe ser mayor que precio de compra');
}

// Ejemplo: Validar formato de DNI
if (!preg_match('/^\d{8}$/', $dni)) {
    throw new \Exception('DNI debe tener 8 dígitos');
}

// Ejemplo: Validar formato de placa
if (!preg_match('/^[A-Z]{3}\d{3}$/', $placa)) {
    throw new \Exception('Placa debe tener formato ABC123');
}
```

### Resumen de Volumen de Datos

| Entidad | Cantidad | Observaciones |
|---------|----------|---------------|
| Clientes | 20 | DNI únicos, placas válidas |
| Vehículos | 15 | Catálogo predefinido |
| Trabajadores | 10 | 80% activos, 20% inactivos |
| Productos | 30 | Organizados por categorías |
| Servicios | 12 | Servicios típicos de taller |
| Cambios de Aceite | 40 | Últimos 6 meses |
| Cambio Productos | 80-160 | 1-4 por cambio de aceite |
| Ingresos | 50 | Últimos 3 meses, 30% con foto |
| Ingreso Trabajadores | 50-150 | 1-3 por ingreso |
| Detalle Servicios | 50-250 | 1-5 por ingreso |
| Ventas | 60 | Últimos 4 meses |
| Detalle Ventas | 60-360 | 1-6 por venta |

**Total aproximado**: 400-1000 registros generados

