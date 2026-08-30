# Feature: Sección de Auditoría — Módulo de Kardex de Movimientos de Productos

## Índice

1. [Resumen ejecutivo](#resumen-ejecutivo)
2. [Estado actual del sistema](#estado-actual-del-sistema)
3. [Decisiones confirmadas por el usuario](#decisiones-confirmadas-por-el-usuario)
4. [Requisitos funcionales y no funcionales](#requisitos-funcionales-y-no-funcionales)
5. [Diseño técnico](#diseño-técnico)
6. [Plan de implementación en 4 unidades de trabajo](#plan-de-implementación-en-4-unidades-de-trabajo)
7. [Riesgos y decisiones pendientes](#riesgos-y-decisiones-pendientes)
8. [Resultados esperados](#resultados-esperados)
9. [Criterios de aceptación](#criterios-de-aceptación)
10. [Verificación](#verificación)

---

## Resumen ejecutivo

Se implementa la **Sección de Auditoría** con su **Módulo de Kardex**: una tabla inmutable y auditable que registra **cada movimiento de existencias de un producto** de forma **individual por producto**, indicando:

- **fecha y hora** del movimiento,
- **producto** relacionado,
- **cantidad** movida,
- **usuario** que registró el movimiento (operador del evento origen),
- **fuente** del movimiento (`venta`, `cambio_aceite`, `inventario`),
- **origen** como valor de índice: correlativo de venta (`VTA-XXXX`), placa del vehículo (cambio de aceite) o **correlativo genérico de inventario** (`INV-XXXX`).

El registro es **100% automático**: se materializa dentro de la **misma transacción** que produce el cambio de existencias, garantizando que ninguna operación de inventario quede sin su movimiento de Kardex y viceversa.

Como **pre-requisito de comportamiento**, se **ajusta la lógica de descuento de stock del módulo de cambio de aceite**: hoy el stock se descuenta al generar el ticket (`pendiente`); se cambiará para que el stock se descuente **solo cuando el ticket sea CONFIRMADO**. Esto elimina el "reservado" fantasma de tickets pendientes y simplifica la trazabilidad del Kardex.

La nueva sección queda protegida por un **permiso nuevo `acceso-auditoria`**.

---

## Estado actual del sistema

### Datos vigentes relevantes

```
productos
  id, nombre, precio_compra, precio_venta, stock, inventario,
  activo, foto, categoria_id, marca_id

ventas
  id, correlativo (VTA-XXXX), subtotal, total, metodo_pago, user_id, caja_id

detalle_ventas
  id, venta_id (FK), producto_id (FK), cantidad, precio_unitario, subtotal

cambio_aceites
  id, cliente_id, automotor_id (FK -> automotores.placa), trabajador_id,
  fecha, precio, total, descripcion, foto, user_id, metodo_pago, caja_id, estado
        -- estado: 'pendiente' | 'confirmado'

cambio_productos
  id, cambio_aceite_id (FK), producto_id (FK), cantidad, precio, total
```

### Punto de verdad del stock

- `productos.stock`: existencias disponibles para la venta. Es la única columna que se descuenta/incrementa en las operaciones de negocio.

### Movimientos de inventario HOY (dispersos, sin trazabilidad)

| Operación | Controlador / método | Efecto en `productos.stock` | Trazabilidad hoy |
|---|---|---|---|
| Vender | `VentaController::store` | `decrement` por producto (transacción) | ❌ |
| Anular venta | `VentaController::destroy` | `increment` por producto (transacción) | ❌ |
| Generar ticket cambio aceite | `CambioAceiteController::store` | `decrement` por producto | ❌ (y descuenta en `pendiente`) |
| Editar ticket cambio aceite | `actualizarTicket` / `update` | restaura + decrementa (sync) | ❌ |
| Confirmar cambio aceite | `procesarConfirmacion` | restaura + decrementa (sync) | ❌ |
| Eliminar cambio aceite | `destroy` | restaura stock | ❌ |
| Crear producto | `ProductoController::store` | `stock = inventario` (inicial) | ❌ |
| Ajustar stock | `ProductoController::updateStock` | `stock += cantidad_adicional` | ❌ |

### Hallazgo clave (fuente de los movimientos)

Confirmado por inspección (`grep increment|decrement` en `app/`):

- Los únicos cambios sobre `productos.stock` están en `CambioAceiteController`, `VentaController` y `ProductoController`.
- **Lavados / ingresos** usan el catálogo `vehiculos` + `detalle_servicios` (servicios), **no descuentan productos** → quedan **fuera del alcance** del Kardex.
- **No existe ninguna tabla de movimientos/kardex** en el esquema actual.

### Permisos y navegación

- Permisos gestionados con Spatie vía `database/seeders/PermissionSeeder.php` (patrón `acceso-*`).
- El menú lateral en `resources/views/layouts/app.blade.php` usa `@can(...)` + `request()->routeIs(...)` con secciones desplegables (desktop y móvil).

---

## Decisiones confirmadas por el usuario

1. **Registro automático**: los movimientos de Kardex se insertan dentro de la **misma transacción** que crea/confirma la venta o cambio de aceite y que realiza cada ingreso de inventario (creación de producto y ajuste de stock). No hay alta manual.
2. **Alcance de fuentes ampliado**: `venta`, `cambio_aceite` **e** `inventario` (entradas).
3. **Permiso nuevo `acceso-auditoria`** que protege rutas, controlador y menú.
4. **Correlativo genérico para inventario**: las entradas de inventario llevan su propio correlativo `INV-XXXX` (mismo patrón que `VTA-XXXX`), de modo que **toda fuente** tiene un valor de índice de origen.
5. **Ajuste del cambio de aceite (pre-requisito)**: el stock se descuenta **solo al confirmar** el ticket (`estado = 'confirmado'`), no al generarlo (`pendiente`).

---

## Requisitos funcionales y no funcionales

### Requisitos funcionales

| ID | Requisito |
|----|-----------|
| RF-01 | Crear la tabla `movimientos_kardex` que registre cada movimiento de producto de forma **individual por producto**. |
| RF-02 | Cada movimiento guarda: `fecha_movimiento`, `producto_id`, `tipo`, `fuente`, `origen_id`, `cantidad`, `stock_antes`, `stock_despues`, `usuario_id`. |
| RF-03 | La fuente `venta` usa el **correlativo de venta** como `origen_id`. |
| RF-04 | La fuente `cambio_aceite` usa la **placa del vehículo** (`automotor_id`) como `origen_id`. |
| RF-05 | La fuente `inventario` usa un **correlativo genérico `INV-XXXX`** generado automáticamente como `origen_id`. |
| RF-06 | El registro se hace de forma **automática y atómica** dentro de la misma transacción de cada operación de stock. |
| RF-07 | **Ajuste cambio de aceite**: el stock se descuenta SOLO al confirmar el ticket; un `pendiente` no descuenta inventario. |
| RF-08 | Incluir movimientos de entrada por **creación de producto** (stock inicial) y por **ajuste de stock** (`updateStock`). |
| RF-09 | Registrar movimientos compensatorios al **anular venta** y al **eliminar/editar un ticket confirmado** (auditoría completa). |
| RF-10 | Proteger la sección con el permiso `acceso-auditoria` y mostrarla en el menú de Auditoría. |
| RF-11 | Proveer vistas de consulta con **filtros**: por producto, por fuente, por rango de fechas y por correlativo de origen. |

### Requisitos no funcionales

| ID | Requisito |
|----|-----------|
| RNF-01 | **Atomicidad**: si falla la operación de stock, no se persiste el movimiento; si falla la escritura del movimiento, se revierte la operación (misma transacción). |
| RNF-02 | **Rendimiento**: índices sobre `producto_id`, `fuente`, `origen_id` y `fecha_movimiento` para consultas de listado/filtrado. |
| RNF-03 | **Integridad referencial** vía FK (`producto_id → productos`, `usuario_id → users`). |
| RNF-04 | **Concurrencia**: el correlativo `INV-XXXX` se calcula dentro de la transacción para evitar colisiones. |
| RNF-05 | **Backward-compatibilidad**: los tests existentes del módulo de cambio de aceite se actualizan a la nueva semántica. |
| RNF-06 | Cobertura de **tests automáticos** (feature + unit) del registro del Kardex y del ajuste de stock del cambio de aceite. |

---

## Diseño técnico

### 1. Nueva tabla `movimientos_kardex`

| Columna | Tipo | Restricciones | Descripción |
|---------|------|---------------|-------------|
| `id` | bigint | PK, autoincrement | |
| `producto_id` | bigint | FK → `productos.id` (cascade) | Producto afectado |
| `tipo` | enum(`entrada`,`salida`) | not null | Entrada (inventario) o salida (venta/cambio) |
| `fuente` | enum(`venta`,`cambio_aceite`,`inventario`) | not null | Origen del movimiento |
| `origen_id` | string(20) | not null, index | Correlativo/índice de origen (ver 1.1) |
| `cantidad` | integer | not null (>0) | Unidades movidas |
| `stock_antes` | integer | not null | Stock del producto antes del movimiento |
| `stock_despues` | integer | not null | Stock del producto después del movimiento |
| `usuario_id` | bigint | FK → `users.id` (restrict) | Usuario que registró la operación origen |
| `fecha_movimiento` | timestamp | not null | Fecha/hora del movimiento |
| `created_at` / `updated_at` | timestamp | | |

#### 1.1 Semántica de `origen_id` (correlativo por fuente)

| Fuente | Formato de `origen_id` | Ejemplo |
|--------|--------------------------|---------|
| `venta` | correlativo de venta (existente) | `VTA-0021` |
| `cambio_aceite` | placa del automotor (`automotor_id`) | `ABC-123` |
| `inventario` | correlativo genérico generado | `INV-0034` |

**Nota de unicidad**: una operación (ticket/ajuste) puede contener **varios productos** → genera **una fila de Kardex por producto**, todas compartiendo el mismo `origen_id` (patrón análogo a `detalle_ventas`/`cambio_productos`). Por ello `origen_id` es **indexado pero NO único a nivel de fila**; el agrupador de la operación es el propio `origen_id`.

#### 1.2 Correlativo genérico de inventario

Se genera dentro de la transacción con el mismo patrón que el correlativo de venta (`VentaController::store`):

```php
public function siguienteCorrelativoInventario(): string
{
    $max = MovimientoKardex::where('fuente', 'inventario')
        ->where('origen_id', 'like', 'INV-%')
        ->max('origen_id');

    $next = $max ? ((int) substr($max, 4)) + 1 : 1;

    return 'INV-'.str_pad($next, 4, '0', STR_PAD_LEFT);
}
```

Las entradas de inventario usan este correlativo (no los valores provisionales `INICIAL`/`AJUSTE`).

### 2. Migración a crear

| Migración | Contenido |
|-----------|-----------|
| `2026_08_30_000001_create_movimientos_kardex_table` | Crear `movimientos_kardex` con columnas + FK + índices `(producto_id, fuente, origen_id, fecha_movimiento)`. |

### 3. Modelo `MovimientoKardex` (nuevo, `app/Models/MovimientoKardex.php`)

```php
class MovimientoKardex extends Model
{
    protected $fillable = [
        'producto_id', 'tipo', 'fuente', 'origen_id',
        'cantidad', 'stock_antes', 'stock_despues',
        'usuario_id', 'fecha_movimiento',
    ];

    protected $casts = [
        'cantidad'        => 'integer',
        'stock_antes'     => 'integer',
        'stock_despues'   => 'integer',
        'fecha_movimiento'=> 'datetime',
    ];

    public function producto(): BelongsTo { /* belongsTo Producto */ }
    public function usuario(): BelongsTo  { /* belongsTo User */ }
}
```

Se agrega la relación inversa en `Producto`:

```php
public function movimientos(): HasMany
{
    return $this->hasMany(MovimientoKardex::class);
}
```

### 4. Servicio `KardexService` (nuevo, `app/Services/KardexService.php`)

Centraliza la **escritura atómica** del Kardex. Todos los métodos se invocan **dentro** de la transacción de la operación que ya existe en los controladores.

```php
class KardexService
{
    // Salidas
    public function registrarSalidaVenta(Producto $p, int $cantidad, string $correlativo): void;
    public function registrarSalidaCambioAceite(Producto $p, int $cantidad, string $placa): void;

    // Entradas
    public function registrarEntradaInventario(Producto $p, int $cantidad): void; // genera INV-XXXX
    public function registrarEntradaCompensacion(Producto $p, int $cantidad, string $origen): void;

    // Auxiliares
    public function siguienteCorrelativoInventario(): string;
    private function registrarMovimiento(
        Producto $p, int $cantidad, string $tipo, string $fuente, string $origen
    ): void; // captura stock_antes/stock_despues y fecha_movimiento
}
```

> `registrarMovimiento` captura `$p->stock` antes y después de la operación de negocio para persistir `stock_antes`/`stock_despues` fieles.

### 5. Ajuste del módulo de cambio de aceite (pre-requisito)

El objetivo es que **el stock se descuente solo al confirmar el ticket**. Cambios en `app/Http/Controllers/CambioAceiteController.php`:

| Método | Estado | Hoy (stock) | Nuevo (stock) |
|---|---|---|---|
| `store` | crea `pendiente` | `decrement` (ll. 157-159) | **NO toca stock** |
| `actualizarTicket` | edita `pendiente` | restaura + decrementa (ll. 625-658) | **NO toca stock** |
| `procesarConfirmacion` | `pendiente`→`confirmado` | restaura + decrementa (ll. 513-548) | **decrementa una sola vez** aquí |
| `update` | edita `confirmado` | restaura + decrementa (ll. 300-320) | ajuste neto (sin cambios estructurales) |
| `destroy` | elimina | restaura stock (ll. 353-360) | **restaura solo si estaba `confirmado`** |

**Detalle por método:**

- **`store`**: quitar el `decrement` (ll. 157-159). Se mantiene la validación de stock disponible (ll. 109-116) como aviso, pero no se descuenta.
- **`actualizarTicket`**: eliminar los bloques "restaurar stock anterior" (ll. 623-627) y "decrementar con nuevas cantidades" (ll. 650-657). Solo se sincroniza el pivote `cambio_productos`.
- **`procesarConfirmacion`**: eliminar "restaurar stock anterior" (ll. 513-515) (el pendiente ya no descontó) y **mantener** el `decrement` (ll. 539-548). Único punto de descuento de stock para cambio de aceite.
- **`update`**: mantener el ajuste neto (restaurar anterior + descontar nuevo) porque el ticket ya está confirmado y su stock sí fue descontado.
- **`destroy`**: restaurar stock **solo si** `$cambioAceite->estado === 'confirmado'`.

**Impacto:** `CambioAceite::scopePendientes` y las vistas de pendientes no cambian funcionalmente; solo dejan de consumir inventario hasta confirmarse. La validación de stock en `procesarConfirmacion` (ll. 477-485) queda correcta (el pendiente no aporta stock descontado).

### 6. Controlador `KardexController` (nuevo, `app/Http/Controllers/KardexController.php`)

- `index()`: listado paginado de `movimientos_kardex` con relaciones `producto` y `usuario`, y **filtros** por producto, fuente, rango de fechas y `origen_id` (correlativo/placa). Vista `kardex.index`.
- `porProducto(Producto $producto)`: histórico específico por producto con saldo acumulado (derivado de `stock_despues`). Vista `kardex.por-producto`.

### 7. Rutas (`routes/web.php`)

```php
Route::middleware(['auth', 'permission:acceso-auditoria'])->prefix('auditoria')->group(function () {
    Route::get('/kardex', [KardexController::class, 'index'])->name('kardex.index');
    Route::get('/kardex/producto/{producto}', [KardexController::class, 'porProducto'])->name('kardex.porProducto');
});
```

### 8. Vistas (`resources/views/kardex/`)

Siguiendo el patrón Blade + Tailwind + dark mode de `automotores`/`ventas`:

- `index.blade.php`: tabla con Fecha/movimiento, Producto, Tipo, Cantidad, Stock antes/después, Fuente, Origen, Usuario + formulario de filtros.
- `por-producto.blade.php`: histórico filtrado por un producto con su saldo.

### 9. Menú lateral (`resources/views/layouts/app.blade.php`)

- Nueva variable activa `$kardexActive = request()->routeIs('kardex.*')`.
- Nueva sección **Auditoría → Kardex** envuelta en `@can('acceso-auditoria')`, tanto en el menú desktop como en el bottom-nav móvil (patrón de las secciones desplegables existentes).

### 10. Permiso y Seeders

- `database/seeders/PermissionSeeder.php`: agregar `'acceso-auditoria'`.
- Asignar el permiso a roles adecuados (revisar `AuthSeeder` y la gestión de roles).
- Nuevo `database/seeders/KardexSeeder.php` (+ registro en `DatabaseSeeder`) que genere movimientos de ejemplo coherentes con ventas/cambios/productos existentes.

### 11. Puntos de integración (resumen de modificaciones)

| Archivo | Cambio |
|---------|--------|
| `database/migrations/2026_08_30_000001_create_movimientos_kardex_table.php` | Tabla + FK + índices |
| `app/Models/MovimientoKardex.php` | Modelo nuevo |
| `app/Models/Producto.php` | Relación `movimientos()` |
| `app/Services/KardexService.php` | Escritura atómica + correlativo `INV-` |
| `app/Http/Controllers/CambioAceiteController.php` | Ajuste de stock: solo al confirmar + registro Kardex |
| `app/Http/Controllers/VentaController.php` | Registro salida (store) + compensación (destroy) |
| `app/Http/Controllers/ProductoController.php` | Registro entrada (store y updateStock) |
| `app/Http/Controllers/KardexController.php` | Consulta/flitros (nuevo) |
| `routes/web.php` | Grupo `auditoria` con permiso |
| `resources/views/layouts/app.blade.php` | Menú Auditoría |
| `resources/views/kardex/*.blade.php` | Vistas (nuevas) |
| `database/seeders/PermissionSeeder.php` | Permiso `acceso-auditoria` |
| `database/seeders/KardexSeeder.php` + `DatabaseSeeder.php` | Seeders |

---

## Plan de implementación en 4 unidades de trabajo

### Unidad 0 — Ajuste del cambio de aceite (pre-requisito de comportamiento)

> **Objetivo:** cambiar la semántica de descuento de stock para que solo ocurra al confirmar.

| # | Tarea |
|---|-------|
| 0.1 | `store`: quitar el `decrement` de stock (ll. 157-159). |
| 0.2 | `actualizarTicket`: eliminar restaurar + decrementar (ll. 623-657). |
| 0.3 | `procesarConfirmacion`: eliminar restaurar anterior (ll. 513-515), conservar decremento (ll. 539-548). |
| 0.4 | `destroy`: restaurar stock solo si `estado === 'confirmado'`. |
| 0.5 | Actualizar tests `CambioAceite/{StoreTest,ConfirmarTest,DestroyTest,ActualizarTicketTest}` a la nueva semántica. |
| 0.6 | **Verificación U0:** crear pendiente no baja stock; confirmar baja una sola vez; editar pendiente no cambia stock; eliminar confirmado restaura / eliminar pendiente no. |

### Unidad 1 — Esquema, modelo y servicio (fundamento de datos)

| # | Tarea |
|---|-------|
| 1.1 | Migración `create_movimientos_kardex_table`. |
| 1.2 | Modelo `MovimientoKardex` + relación `Producto::movimientos()`. |
| 1.3 | Servicio `KardexService` (registro atómico + `siguienteCorrelativoInventario`). |
| 1.4 | `PermissionSeeder`: agregar `acceso-auditoria` y asignación a roles. |
| 1.5 | **Verificación U1:** `migrate:fresh`; `tinker` escribe un movimiento con `stock_antes/stock_despues` y `INV-XXXX`. |

### Unidad 2 — Integración con los flujos (comportamiento)

| # | Tarea |
|---|-------|
| 2.1 | `VentaController`: registrar salida por correlativo en `store`; compensación en `destroy`. |
| 2.2 | `CambioAceiteController` (post-U0): registrar salida por placa en `procesarConfirmacion`; ajuste/compensación en `update` y `destroy` de confirmado. |
| 2.3 | `ProductoController`: registrar entrada `INV-XXXX` en `store` y `updateStock`. |
| 2.4 | **Verificación U2:** flujo end-to-end — crear venta → movimiento con `VTA-XXXX`; confirmar cambio → movimiento con placa; ajustar stock → entrada `INV-XXXX`. |

### Unidad 3 — UI, seeders y cobertura de tests (entrega)

| # | Tarea |
|---|-------|
| 3.1 | `KardexController` + vistas (`index`, `por-producto`) + integración en sidebar desktop/móvil. |
| 3.2 | `KardexSeeder` + registro en `DatabaseSeeder`. |
| 3.3 | Tests: registro automático en venta/cambio/stock; compensaciones; `stock_antes/despues`; filtros; permiso `acceso-auditoria`. |
| 3.4 | **Verificación U3:** `migrate:fresh --seed`, `php artisan test`, `npm run build` (Vite), `vendor/bin/pint`. |

---

## Riesgos y decisiones pendientes

### R1 — Doble registro/omisión en cambio de aceite (ya resuelto)
Con la **Unidad 0**, la fuente `cambio_aceite` solo se registra en `procesarConfirmacion` (y se ajusta/revierte en `update`/`destroy` de confirmado), eliminando la complejidad de reconciliación de ida y vuelta.

### R2 — Unicidad de `origen_id`
Como una operación genera varias filas (una por producto) compartiendo el mismo `origen_id`, este **no es único a nivel de fila**: es indexado como agrupador. Para agrupar un evento completo de forma inequívoca se usa `origen_id` (+ `fecha_movimiento`). **Decisión pendiente:** si se desea además un identificador de cabecera (`movimiento_id`, p. ej. UUID) compartido por todas las filas del mismo evento.

### R3 — Entradas de inventario sin "origen" de negocio
Las entradas por creación/ajuste no tienen correlativo de venta ni placa; el **correlativo genérico `INV-XXXX`** las cubre (decisión confirmada).

### R4 — Anulaciones
Se recomienda registrar movimientos compensatorios al anular/eliminar (auditoría completa). Confirmar si las anulaciones deben quedar explícitas en el Kardex como `entrada` con origen de la operación anulada.

---

## Resultados esperados

### Datos
- Toda operación de stock (venta, cambio de aceite confirmado, creación de producto, ajuste de stock) queda registrada en `movimientos_kardex`, una fila por producto.
- Cada movimiento conserva `stock_antes`/`stock_despues`, `usuario_id`, `fecha_movimiento`, `tipo`, `fuente` y `origen_id` (`VTA-XXXX` / placa / `INV-XXXX`).
- `productos.stock` ya no se descuenta al generar tickets de cambio de aceite `pendiente`.

### Flujo
- Al confirmar un cambio de aceite se descuenta el stock **y** se registra el Kardex en la misma transacción.
- Entradas de inventario (creación/ajuste) generan su correlativo `INV-XXXX`.
- La sección **Auditoría → Kardex** permite consultar y filtrar los movimientos.

### Calidad
- Permiso `acceso-auditoria` protege la sección.
- Tests automáticos cubren el registro del Kardex y el nuevo comportamiento de stock del cambio de aceite.
- Build de Vite y `pint` correctos.

---

## Criterios de aceptación

1. La tabla `movimientos_kardex` existe con los campos y FK definidos e índices sobre `producto_id`, `fuente`, `origen_id` y `fecha_movimiento`.
2. Al **crear una venta**, aparece un movimiento de `salida` por cada producto con `fuente=venta` y `origen_id=VTA-XXXX`.
3. Al **confirmar un cambio de aceite**, aparece un movimiento de `salida` por cada producto con `fuente=cambio_aceite` y `origen_id=placa`.
4. Al **crear un producto / ajustar stock**, aparece un movimiento de `entrada` con `fuente=inventario` y `origen_id=INV-XXXX`.
5. El stock del producto **no se descuenta** al generar un ticket `pendiente`, y **sí se descuenta** al confirmarlo (una sola vez).
6. La sección **Auditoría → Kardex** está protegida por el permiso `acceso-auditoria` y permite filtrar.
7. `migrate:fresh --seed`, `php artisan test`, `npm run build` y `vendor/bin/pint` pasan sin errores.

---

## Verificación

| Comando | Propósito |
|---------|-----------|
| `php artisan migrate:fresh --seed` | Validar esquema + seeders |
| `php artisan test` | Ejecutar suite (incluye nuevos tests de Kardex y cambio de aceite) |
| `vendor/bin/pint --test` | Formato de código |
| `npm run build` | Compilar assets (Vite) |
| `php artisan route:list` | Validar rutas nuevas (grupo `auditoria`) |
| `php artisan tinker` | Inspección manual de `MovimientoKardex` y correlativos |

> Nota: validar que `migrate:fresh` es seguro en el entorno de desarrollo actual (SQLite en `database/database.sqlite`).
