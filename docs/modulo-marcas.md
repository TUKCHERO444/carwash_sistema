# Módulo de Marcas

## Descripción

Módulo CRUD para gestionar las marcas de productos automotrices. Las marcas permiten identificar el fabricante o proveedor de cada producto, facilitando la búsqueda, filtración y organización del inventario.

---

## Modelo de Datos

### Tabla `marcas`

| Columna | Tipo | Restricciones | Descripción |
|---------|------|---------------|-------------|
| `id` | bigint (PK) | auto-increment | Identificador único |
| `nombre` | varchar(150) | unique, not null | Nombre de la marca |
| `descripcion` | text | nullable | Descripción de la marca |
| `created_at` | timestamp | | Fecha de creación |
| `updated_at` | timestamp | | Fecha de última actualización |

### Relación con Productos

- **Una marca** puede tener **muchos productos** (1:N)
- **Un producto** puede tener **una marca** (nullable)
- FK `marca_id` en tabla `productos` con `nullOnDelete` (si se elimina una marca, los productos quedan sin marca)

```
marcas (1) ──────── (∞) productos
   │                      │
   ├─ id                  ├─ id
   ├─ nombre              ├─ nombre
   ├─ descripcion         ├─ marca_id (FK nullable)
   └─ timestamps          └─ ...
```

---

## Arquitectura

```
app/Models/Marca.php                        — Modelo Eloquent
app/Http/Controllers/MarcaController.php    — Controlador CRUD
resources/views/marcas/index.blade.php      — Listado paginado
resources/views/marcas/create.blade.php     — Formulario de creación
resources/views/marcas/edit.blade.php       — Formulario de edición
database/migrations/*_create_marcas_table.php         — Migración tabla marcas
database/migrations/*_add_marca_id_to_productos_table.php — Migración FK
database/seeders/MarcaSeeder.php            — Seeder con 15 marcas reales
```

---

## Rutas

Todas las rutas están protegidas por el middleware `permission:acceso-inventario`.

| Método | Ruta | Nombre | Acción |
|--------|------|--------|--------|
| GET | `/marcas` | `marcas.index` | Listado paginado |
| GET | `/marcas/create` | `marcas.create` | Formulario de creación |
| POST | `/marcas` | `marcas.store` | Guardar nueva marca |
| GET | `/marcas/{marca}/edit` | `marcas.edit` | Formulario de edición |
| PUT | `/marcas/{marca}` | `marcas.update` | Actualizar marca |
| DELETE | `/marcas/{marca}` | `marcas.destroy` | Eliminar marca |

> La ruta `show` no está registrada (`.except(['show'])`), siguiendo el patrón del proyecto.

---

## Controlador

### `MarcaController`

**Métodos:**

| Método | Descripción |
|--------|-------------|
| `index()` | Lista marcas paginadas (10 por página) con conteo de productos (`withCount`) |
| `create()` | Muestra formulario de creación |
| `store()` | Valida y guarda nueva marca |
| `edit()` | Muestra formulario de edición con marca existente |
| `update()` | Valida y actualiza marca |
| `destroy()` | Elimina marca (bloqueado si tiene productos asignados) |

**Validaciones:**

| Campo | Creación | Edición |
|-------|----------|---------|
| `nombre` | required, string, max:150, unique:marcas,nombre | required, string, max:150, unique:marcas,nombre,{id} |
| `descripcion` | nullable, string, max:500 | nullable, string, max:500 |

**Protección de eliminación:**

```php
if ($marca->productos()->exists()) {
    return redirect()->route('marcas.index')
        ->with('error', 'No se puede eliminar la marca porque tiene productos asignados.');
}
```

---

## Navegación

La sección "Marcas" se encuentra en el sidebar bajo **Gestión de productos**:

```
Dashboard
Caja
Gestión de Ventas
  ├─ Ventas
  ├─ Ingresos
  └─ Cambio de Aceite
Gestión de usuarios
  ├─ Usuarios
  ├─ Roles
  └─ Trabajadores
Gestión de productos        ← sección activa
  ├─ Productos
  ├─ Categorías
  └─ Marcas                 ← nuevo enlace
Gestión Administrativa
  ├─ Vehículos
  ├─ Servicios
  └─ Clientes
```

---

## Seeders

### `MarcaSeeder`

Crea 15 marcas reales de repuestos automotrices:

| Marca | Descripción |
|-------|-------------|
| Motul | Aceites y lubricantes de alta performance |
| Castrol | Lubricantes automotrices e industriales |
| Bosch | Componentes automotrices, frenos y eléctrico |
| Monroe | Amortiguadores y suspensión |
| Continental | Neumáticos y componentes |
| Michelin | Neumáticos y cubiertas |
| Brembo | Sistemas de frenado |
| Varta | Baterías automotrices |
| NGK | Bujías y sensores |
| Gates | Correas y transmisión |
| Sachs | Embragues y tren motriz |
| Fram | Filtros |
| ACDelco | Repuestos GM y eléctrico |
| Denso | Componentes y sistemas eléctricos |
| Mobil | Lubricantes |

### Asignación en `ProductoSeeder`

Los productos se asignan a marcas por **coincidencia de palabras clave**:

```php
$marcaMap = [
    'Motul'       => ['aceite motor 5w-30', 'aceite motor 10w-40', 'aceite transmisión'],
    'Castrol'     => ['aceite motor 20w-50', 'aceite hidráulico'],
    'Fram'        => ['filtro de aceite', 'filtro de aire', ...],
    'Brembo'      => ['pastillas de freno', 'discos de freno'],
    // ... etc
];
```

**Resultado:** 29 de 30 productos tienen marca asignada.

---

## Integridad de Datos

| Capa | Mecanismo |
|------|-----------|
| **Migración** | FK `marca_id` con `constrained('marcas')->nullOnDelete()` |
| **Modelo Producto** | `belongsTo(Marca::class)` en relación |
| **Modelo Marca** | `hasMany(Producto::class)` en relación |
| **Controlador** | Bloqueo de eliminación si existen productos asociados |
| **Seeder** | Asignación consistente de marcas a productos existentes |

---

## Permisos

El módulo utiliza el permiso existente `acceso-inventario`, que ya está asignado al rol **Administrador** en el `AuthSeeder`. No se requiere crear permisos adicionales.

---

## Verificación

- **101 tests pasaron** sin regresiones
- **Build de Vite** compiló correctamente
- **15 marcas** creadas por el seeder
- **29/30 productos** con marca asignada
- **migrate:fresh --seed** ejecutado exitosamente
