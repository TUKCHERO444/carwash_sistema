# Pendientes de Implementación — Módulo Compras e Ingreso de Mercadería

**Fecha:** 2026-09-26  
**Estado actual:** Fases 0-4 completadas, Fase 6 parcial, Fases 5, 7-10 pendientes  
**Documento base:** `docs/nueva_implmentacion_compra.txt` + `docs/plan-compras-ingreso-mercaderia.md`  
**Repositorio:** `carwash_sistema` (Laravel 12, PHP 8.2, SQLite dev / MySQL prod)

---

## Resumen de Estado

| Fase | Descripción | Estado |
|------|-------------|--------|
| 0 | Preparación: separación catálogo/inventario, `precio_compra` opcional, migración `egresos_caja.tipo_pago` | ✅ Completa |
| 1 | Reforma creación producto: stock=0, sin Kardex INV-XXXX | ✅ Completa |
| 2 | CRUD Compras: modelos, controller, vistas, JS, permisos, auditoría | ✅ Completa |
| 3 | Recepción atómica: lock, caja, Kardex (fuente=compra), stock/inventario, precio_compra, egreso caja | ✅ Completa |
| 4 | Retirar modal "Actualizar stock": etiqueta provisional, KardexSeeder placa | ⚠️ Parcial (etiqueta ✓, retiro modal pendiente) |
| 5 | **Ajustes de inventario** (positivo, negativo, merma, daño, conteo) | ❌ Pendiente |
| 6 | **Kardex completo**: fuentes compra, venta, servicio, ajuste, merma, daño, costo unitario/total | ⚠️ Parcial (compra ✓, resto pendiente) |
| 7 | **Anular compra recibida**: salida compensatoria Kardex | ❌ Pendiente |
| 8 | **Concurrencia**: `lockForUpdate` en ventas, cambios, ajustes | ❌ Pendiente |
| 9 | **Stock mínimo** como criterio real de abastecimiento | ❌ Pendiente |
| 10 | **Valorización**: costo promedio ponderado | ❌ Pendiente |
| 18 | Cambio aceite: correlativo CAM-XXXX + placa | ⚠️ Parcial (placa en seeder ✓, correlativo pendiente) |
| 19 | Ventas: condición de carrera `lockForUpdate` | ❌ Pendiente |

---

## 1. Fase 5 — Módulo de Ajustes de Inventario

### 1.1 Objetivo
Reemplazar el modal "Actualizar stock" como mecanismo normal de corrección. Los ajustes serán operaciones formales, trazables y con Kardex obligatorio.

### 1.2 Modelo de Datos

#### Migración: `create_ajustes_inventario_table.php`
```php
Schema::create('ajustes_inventario', function (Blueprint $table) {
    $table->id();
    $table->string('correlativo', 20)->unique(); // AJU-0001
    $table->enum('tipo', ['positivo', 'negativo', 'merma', 'daño', 'conteo_fisico']);
    $table->text('motivo')->nullable();
    $table->text('observaciones')->nullable();
    $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
    $table->timestamps();
});

Schema::create('detalle_ajustes_inventario', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ajuste_id')->constrained('ajustes_inventario')->onDelete('cascade');
    $table->foreignId('producto_id')->constrained('productos')->onDelete('restrict');
    $table->integer('cantidad'); // positivo o negativo según tipo
    $table->integer('stock_antes');
    $table->integer('stock_despues');
    $table->timestamps();
});
```

#### Modelos
- `App\Models\AjusteInventario` — `tipo` enum, `correlativo`, `user()`, `detalles()`, `productos()` (belongsToMany con pivot)
- `App\Models\DetalleAjusteInventario` — pivot con `cantidad`, `stock_antes`, `stock_despues`

### 1.3 Controller: `AjusteInventarioController`

| Método | Reglas |
|--------|--------|
| `index` | `paginate(10)`, filtros tipo/fecha |
| `create` | Productos activos, select tipo con descripciones |
| `store` | Transacción: valida ≥1 línea, calcula stock_antes/despues, crea Kardex |
| `show` | Cabecera + líneas + movimientos Kardex relacionados |

**Validaciones por tipo:**
- `positivo` / `negativo`: cantidad ≥ 1
- `merma` / `daño`: cantidad ≥ 1 (siempre salida)
- `conteo_fisico`: cantidad absoluta, calcula delta = conteo - stock_actual

### 1.4 Kardex — Nuevas Fuentes
Agregar a `movimientos_kardex.fuente` (ya string(30)):
- `ajuste_positivo` → ENTRADA
- `ajuste_negativo` → SALIDA
- `merma` → SALIDA
- `daño` → SALIDA
- `conteo_fisico` → ENTRADA o SALIDA según delta

### 1.5 Vistas
- `resources/views/ajustes/index.blade.php` — tabla con badges por tipo
- `resources/views/ajustes/create.blade.php` — líneas dinámicas (JS igual a compras)
- `resources/views/ajustes/show.blade.php` — detalle + Kardex relacionados

### 1.6 Rutas
```php
Route::middleware('permission:acceso-ajustes')->group(function () {
    Route::resource('ajustes', AjusteInventarioController::class)
        ->parameters(['ajustes' => 'ajuste']);
});
```

### 1.7 Permisos
- `acceso-ajustes` en `PermissionSeeder`
- Asignar a rol `Administrador` y `Almacenero` (futuro)

---

## 2. Fase 6 Completar — Kardex con Costo y Fuentes Faltantes

### 2.1 Migración: `add_costo_to_movimientos_kardex_table.php`
```php
Schema::table('movimientos_kardex', function (Blueprint $table) {
    $table->decimal('costo_unitario', 10, 2)->nullable()->after('cantidad');
    $table->decimal('costo_total', 12, 2)->nullable()->after('costo_unitario');
});
```

### 2.2 Actualizar `KardexService`
```php
public const FUENTES = [
    'venta', 'cambio_aceite', 'compra',
    'ajuste_positivo', 'ajuste_negativo', 'merma', 'daño', 'conteo_fisico', 'inventario'
];

public function registrarEntrada(..., float $costoUnitario = 0): void
public function registrarSalida(..., float $costoUnitario = 0): void
```

- `costo_unitario`: para ENTRADA = costo de compra/ajuste; para SALIDA = último costo promedio o precio_compra del producto
- `costo_total = cantidad * costo_unitario`

### 2.3 Actualizar `KardexController` / Vistas
- `index`: mostrar `costo_unitario`, `costo_total` en tabla
- `show` Kardex: detalle de costo por movimiento

---

## 3. Fase 7 — Anular Compra Recibida (Salida Compensatoria)

### 3.1 Reglas de Negocio
- Solo compras en estado `recibida` (no `borrador` ni `anulada`)
- Genera **salida compensatoria** en Kardex por cada línea
- Revierte `stock` e `inventario` (stock -= cantidad)
- Crea egreso de caja negativo (ingreso) o nota de crédito en caja
- Estado pasa a `anulada`, conserva correlativo original

### 3.2 Método `CompraController::anularRecibida()`
```php
public function anularRecibida(Request $request, Compra $compra): RedirectResponse
{
    if ($compra->estado !== 'recibida') {
        return $this->errorTransicion($compra, 'anular');
    }

    // Verificar caja abierta para ingreso compensatorio
    $caja = app(CajaService::class)->getCajaActiva();
    if (! $caja) {
        return redirect()->route('compras.show', $compra)
            ->with('error_caja', true);
    }

    DB::transaction(function () use ($compra, $caja, $request) {
        // 1. Revertir stock e inventario
        foreach ($compra->detalles as $detalle) {
            $producto = Producto::lockForUpdate()->findOrFail($detalle->producto_id);
            $stockAntes = $producto->stock;
            $stockDespues = max(0, $stockAntes - $detalle->cantidad);
            
            $producto->update([
                'stock' => $stockDespues,
                'inventario' => $stockDespues,
            ]);

            // Salida compensatoria Kardex
            app(KardexService::class)->registrarSalidaCompensatoria(
                $producto, $detalle->cantidad, $stockAntes, $compra->correlativo
            );
        }

        // 2. Ingreso compensatorio en caja (nota de crédito)
        app(CajaService::class)->registrarIngresoCompensatorio($caja, [
            'monto' => (float) $compra->total,
            'descripcion' => "Anulación compra {$compra->correlativo}",
            'tipo_pago' => 'transferencia', // o efectivo
            'user_id' => $request->user()->id,
        ]);

        // 3. Marcar anulada
        $compra->update(['estado' => 'anulada']);
        app(AuditService::class)->anotarAccion('anular compra recibida');
    });

    return redirect()->route('compras.show', $compra)
        ->with('success', 'Compra anulada con salida compensatoria registrada.');
}
```

### 3.3 `KardexService::registrarSalidaCompensatoria()`
```php
public function registrarSalidaCompensatoria(
    Producto $producto, int $cantidad, int $stockAntes, string $origenId
): void {
    $this->registrar(
        $producto, 'salida', $cantidad, $stockAntes,
        $stockAntes - $cantidad, 'ajuste_negativo', $origenId
    );
}
```

### 3.4 UI
- Botón "Anular" en `show` solo para `recibida`
- Modal de confirmación: "Se generará salida compensatoria y nota de crédito en caja"

---

## 4. Fase 8 — Concurrencia (lockForUpdate)

### 4.1 Problema Actual
```
Usuario A ve stock=2
Usuario B ve stock=2
A compra 2 → stock=0
B compra 2 → stock=-2 (oversell)
```

### 4.2 Solución: `lockForUpdate` en todas las salidas

#### `VentaController::store()`
```php
DB::transaction(function () use ($validated) {
    // ... validaciones ...
    foreach ($lineas as $linea) {
        $producto = Producto::lockForUpdate()->findOrFail($linea['producto_id']);
        if ($producto->stock < $linea['cantidad']) {
            throw new \RuntimeException("Stock insuficiente para {$producto->nombre}");
        }
        // ... crear detalle, actualizar stock ...
    }
});
```

#### `CambioAceiteController::confirmar()`
```php
DB::transaction(function () use ($cambio) {
    foreach ($cambio->productos as $productoMod) {
        $producto = Producto::lockForUpdate()->findOrFail($productoMod->id);
        // ... validar stock, descontar, Kardex ...
    }
});
```

#### `AjusteInventarioController::store()` (Fase 5)
```php
foreach ($lineas as $linea) {
    $producto = Producto::lockForUpdate()->findOrFail($linea['producto_id']);
    // ... validar y actualizar ...
}
```

---

## 5. Fase 9 — Stock Mínimo (Criterio Real)

### 5.1 Migración: `add_stock_minimo_to_productos_table.php`
```php
Schema::table('productos', function (Blueprint $table) {
    $table->integer('stock_minimo')->default(0)->after('inventario');
});
```

### 5.2 Factory / Seeder
```php
// ProductoFactory
'stock_minimo' => $this->faker->numberBetween(0, 20),

// ProductoSeeder
'stock_minimo' => $faker->numberBetween(0, 10),
```

### 5.3 UI — Alerta Nueva
En `resources/views/productos/index.blade.php`:
```blade
@php
    $enAlerta = $producto->stock > 0 && $producto->stock <= $producto->stock_minimo;
    $agotado = $producto->stock === 0;
@endphp

@if($agotado)
    <span class="badge bg-red-600">AGOTADO</span>
@elseif($enAlerta)
    <span class="badge bg-yellow-600">REPONER (mín: {{ $producto->stock_minimo }})</span>
@endif
```

### 5.4 Compatibilidad
- Mantener badge "75% consumido" como legacy
- Configurable vía `config/app.php` → `stock_alert_mode` (legacy|minimo|ambos)

---

## 6. Fase 10 — Valorización Costo Promedio Ponderado

### 6.1 Concepto
Reemplazar `stock × precio_compra_actual` por **costo promedio ponderado (CPP)** calculado desde entradas de Kardex.

```cpp
cpp_nuevo = (stock_actual * cpp_actual + cantidad_nueva * costo_nuevo) / (stock_actual + cantidad_nueva)
```

### 6.2 Migración: `add_costo_promedio_to_productos_table.php`
```php
Schema::table('productos', function (Blueprint $table) {
    $table->decimal('costo_promedio_ponderado', 10, 4)->default(0)->after('precio_compra');
});
```

### 6.3 Servicio: `CostoPromedioService`
```php
class CostoPromedioService
{
    public function actualizarPorEntrada(Producto $producto, int $cantidad, float $costoUnitario): void
    {
        $stockActual = $producto->stock; // ya incluye la nueva cantidad
        $cppActual = $producto->costo_promedio_ponderado;
        
        $nuevoCpp = (($stockActual - $cantidad) * $cppActual + $cantidad * $costoUnitario) / $stockActual;
        
        $producto->update(['costo_promedio_ponderado' => round($nuevoCpp, 4)]);
    }

    public function actualizarPorSalida(Producto $producto): void
    {
        // CPP no cambia en salidas (método PEPS/PMP)
    }
}
```

### 6.4 Integración en Recepción / Ajustes
```php
// En CompraController::recibir() tras actualizar stock
app(CostoPromedioService::class)->actualizarPorEntrada($producto, $cantidad, $nuevoCosto);

// En AjusteInventarioController::store() para entradas positivas
app(CostoPromedioService::class)->actualizarPorEntrada($producto, $cantidad, $costoUnitario);
```

### 6.5 Reportes
- `ReporteInventarioService::resumenValorizado()` usa `costo_promedio_ponderado` en lugar de `precio_compra`
- Fallback: si `costo_promedio_ponderado == 0` → usa `precio_compra`

---

## 7. Fase 18 — Cambio de Aceite: Correlativo CAM-XXXX

### 7.1 Migración: `add_correlativo_to_cambio_aceites_table.php`
```php
Schema::table('cambio_aceites', function (Blueprint $table) {
    $table->string('correlativo', 20)->unique()->nullable()->after('id');
});
```

### 7.2 `CambioAceiteService` / Controller
```php
private function generarCorrelativo(): string
{
    $max = CambioAceite::where('correlativo', 'like', 'CAM-%')
        ->max('correlativo');
    $next = $max ? ((int) substr($max, 4)) + 1 : 1;
    return 'CAM-'.str_pad($next, 4, '0', STR_PAD_LEFT);
}

// En confirmar():
$cambio->update([
    'correlativo' => $this->generarCorrelativo(),
    'estado' => 'confirmado',
]);
```

### 7.3 KardexSeeder — Usar Correlativo
```php
$origenId = $cambio->correlativo ?? 'CAM-'.str_pad($cambio->id, 4, '0', STR_PAD_LEFT);
```

### 7.4 Vistas
- `show`: mostrar correlativo + placa
- `index`: columna correlativo

---

## 8. Fase 19 — Ventas: Condición de Carrera

### 8.1 Ya cubierto en **Fase 8** (lockForUpdate en VentaController)
Ver sección 4.2.

---

## 9. Fase 4 Completar — Retirar Modal "Actualizar Stock"

### 9.1 Acciones
1. **Eliminar** `resources/views/productos/index.blade.php` → modal `#stock-modal` (líneas 254-289)
2. **Eliminar** `resources/js/productos/index.js` → lógica `abrirModalStock()`
3. **Eliminar** ruta `productos.updateStock` y método `ProductoController::updateStock()`
4. **Actualizar** `ProductoController::edit()` → no pasar datos de stock al modal

### 9.2 Reemplazo
El enlace "Ajustar" en tabla productos → redirige a `ajustes.create` (Fase 5)

---

## 10. Matriz de Movimientos Kardex — Actualización Final

| Operación | Stock | Kardex | Fuente |
|-----------|-------|--------|--------|
| Crear producto | 0 | Opcional | — |
| Compra recibida | + | Entrada | **compra** |
| Venta | - | Salida | **venta** |
| Anular venta | + | Entrada compensatoria | **venta** |
| Servicio confirmado | - | Salida | **cambio_aceite** |
| Eliminar servicio confirmado | + | Entrada | **cambio_aceite** |
| Ajuste positivo | + | Entrada | **ajuste_positivo** |
| Ajuste negativo | - | Salida | **ajuste_negativo** |
| Merma | - | Salida | **merma** |
| Daño | - | Salida | **daño** |
| Conteo físico | ± | Entrada/Salida | **conteo_fisico** |
| Anular compra recibida | - | Salida compensatoria | **ajuste_negativo** |
| Edición ficha producto | 0 | 0 | — |
| Activar/desactivar | 0 | 0 | — |

---

## 11. Orden de Implementación Recomendado

| Orden | Fase | Esfuerzo | Dependencias |
|-------|------|----------|--------------|
| 1 | **Fase 5** Ajustes | Alto | Fase 3, 4 |
| 2 | **Fase 6** Kardex costo/fuentes | Medio | Fase 3, 5 |
| 3 | **Fase 7** Anular recibida | Alto | Fase 3, 6 |
| 4 | **Fase 8** Concurrencia | Alto | Fase 2, 3, 5 |
| 5 | **Fase 9** Stock mínimo | Medio | Fase 1, 5 |
| 6 | **Fase 18** Cambio aceite correlativo | Bajo | Independiente |
| 7 | **Fase 4** Retirar modal | Bajo | Fase 5 |
| 8 | **Fase 10** Valorización CPP | Alto | Fase 3, 5, 6 |
| 9 | **Fase 19** Ventas concurrencia | Alto | Fase 8 |

---

## 12. Tests Requeridos por Fase

| Fase | Archivos de Test Nuevos |
|------|------------------------|
| 5 | `tests/Feature/AjusteInventarioTest.php`, `tests/js/ajustes/create.property.test.js` |
| 6 | `tests/Feature/KardexCostoTest.php`, `tests/Feature/MovimientoKardexTest.php` (extend) |
| 7 | `tests/Feature/CompraAnularRecibidaTest.php` |
| 8 | `tests/Feature/ConcurrenciaStockTest.php` (property tests con `fast-check`) |
| 9 | `tests/Feature/StockMinimoTest.php` |
| 10 | `tests/Feature/ValorizacionCostoPromedioTest.php` |
| 18 | `tests/Feature/CambioAceiteCorrelativoTest.php` |

---

## 13. Comandos de Verificación Final

```bash
# Suite completa
php artisan test

# Lint
vendor\bin\pint --test

# Migración + seed coherente
php artisan migrate:fresh --seed --force

# Verificar Kardex con costo
php artisan tinker --execute="
\$m = App\Models\MovimientoKardex::where('fuente','compra')->first();
echo 'costo_unitario: '.\$m->costo_unitario.PHP_EOL;
echo 'costo_total: '.\$m->costo_total.PHP_EOL;
"
```

---

## 14. Referencias de Código Existente

| Componente | Archivo | Estado |
|------------|---------|--------|
| `CompraController::recibir()` | `app/Http/Controllers/CompraController.php:218` | ✅ |
| `KardexService::registrarEntradaCompra()` | `app/Services/KardexService.php:80` | ✅ |
| `KardexSeeder` reconstruido | `database/seeders/KardexSeeder.php` | ✅ |
| `ProductoSeeder` stock=0 | `database/seeders/ProductoSeeder.php` | ✅ |
| `ReporteInventarioService::resumenValorizado()` | `app/Services/Reportes/ReporteInventarioService.php` | ✅ |
| `ProductoPrecioTest` margen condicional | `tests/Feature/ProductoPrecioTest.php` | ✅ |
| `tests/js/compras/create.property.test.js` | fast-check 10 propiedades | ✅ |

---

**Próximo paso inmediato:** Iniciar **Fase 5** — Migración `ajustes_inventario` + `detalle_ajustes_inventario`, modelos, controller, vistas, JS, rutas, permisos, tests.