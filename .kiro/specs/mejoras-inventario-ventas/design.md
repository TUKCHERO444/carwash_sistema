# .kiro/specs/mejoras-inventario-ventas/design.md

# Diseño Técnico — Stock Mínimo, CPP, Cambio Aceite Correlativo, Concurrencia Ventas, Retirar Modal

---

## 1. Fase 9 — Stock Mínimo

### 1.1 Migración
```php
// add_stock_minimo_to_productos_table.php
Schema::table('productos', function (Blueprint $table) {
    $table->unsignedInteger('stock_minimo')->default(0)->after('inventario');
    $table->index('stock_minimo');
});
```

### 1.2 Modelo `Producto`
```php
// Agregar a $fillable
'stock_minimo'

protected $casts = [
    'stock_minimo' => 'integer',
    // ...
];

public function estaEnAlerta(): bool
{
    return $this->stock > 0 
        && $this->stock_minimo > 0 
        && $this->stock <= $this->stock_minimo;
}

public function estaAgotado(): bool
{
    return $this->stock === 0;
}

public function getEstadoAlertaAttribute(): string
{
    if ($this->estaAgotado()) return 'agotado';
    if ($this->estaEnAlerta()) return 'alerta';
    return 'normal';
}
```

### 1.3 Configuración Legacy
```php
// config/app.php
'stock_alert_mode' => env('STOCK_ALERT_MODE', 'ambos'), // 'legacy', 'minimo', 'ambos'

// En Producto model (helper para vistas)
public function getAlertaLegacyAttribute(): bool
{
    if ($this->inventario === 0) return false;
    return $this->stock <= ($this->inventario * 0.75);
}
```

### 1.4 Vista `productos/index.blade.php` — Badge Alerta
```blade
@php
    $modoAlerta = config('app.stock_alert_mode', 'ambos');
    $enAlertaMinimo = $producto->estaEnAlerta();
    $agotado = $producto->estaAgotado();
    $alertaLegacy = $producto->alerta_legacy;
@endphp

<td class="px-6 py-8 whitespace-nowrap">
    @if($agotado)
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
            AGOTADO
        </span>
    @elseif($enAlertaMinimo && in_array(config('app.stock_alert_mode'), ['minimo', 'ambos']))
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
            REPONER (mín: {{ $producto->stock_minimo }})
        </span>
    @elseif($alertaLegacy && in_array(config('app.stock_alert_mode'), ['legacy', 'ambos']))
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400">
            STOCK BAJO ({{ round(100 - ($producto->stock / max($producto->inventario, 1)) * 100) }}%)
        </span>
    @else
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
            OK
        </span>
    @endif
</td>
```

### 1.5 Factory / Seeder
```php
// ProductoFactory.php
'stock_minimo' => $this->faker->numberBetween(0, 20),

// ProductoSeeder.php
'stock_minimo' => $faker->numberBetween(0, 10),
```

---

## 2. Fase 10 — Costo Promedio Ponderado (CPP)

### 2.1 Migración
```php
// add_costo_promedio_to_productos_table.php
Schema::table('productos', function (Blueprint $table) {
    $table->decimal('costo_promedio_ponderado', 10, 4)->default(0)->after('precio_compra');
    $table->index('costo_promedio_ponderado');
});
```

### 2.2 Modelo `Producto`
```php
protected $fillable = [
    // ...
    'costo_promedio_ponderado',
];

protected $casts = [
    'costo_promedio_ponderado' => 'decimal:4',
    // ...
];
```

### 2.3 Servicio `CostoPromedioService` — Ampliado
```php
class CostoPromedioService
{
    public function obtenerCpp(Producto $producto): float
    {
        $cpp = (float) $producto->costo_promedio_ponderado;
        return $cpp > 0 ? $cpp : (float) $producto->precio_compra;
    }

    public function actualizarPorEntrada(Producto $producto, int $cantidad, float $costoUnitario): void
    {
        // $producto->stock YA incluye la nueva cantidad
        $stockActual = $producto->stock;
        $cppActual = (float) $producto->costo_promedio_ponderado;

        if ($cppActual <= 0) {
            $nuevoCpp = $costoUnitario;
        } else {
            $nuevoCpp = (($stockActual - $cantidad) * $cppActual + $cantidad * $costoUnitario) / $stockActual;
        }

        $producto->update(['costo_promedio_ponderado' => round($nuevoCpp, 4)]);
    }

    // Inicialización masiva para productos existentes
    public function inicializarCppExistentes(): int
    {
        $actualizados = 0;
        Producto::where('costo_promedio_ponderado', 0)
            ->where('precio_compra', '>', 0)
            ->chunkById(100, function ($productos) use (&$actualizados) {
                foreach ($productos as $producto) {
                    $producto->update(['costo_promedio_ponderado' => round((float) $producto->precio_compra, 4)]);
                    $actualizados++;
                }
            });
        return $actualizados;
    }
}
```

### 2.4 Integración en Operaciones de Entrada

#### `CompraController::recibir()` — Tras actualizar stock
```php
// Dentro del loop de líneas, tras actualizar stock:
if ($nuevoCosto > 0) {
    $producto->update(['precio_compra' => round($nuevoCosto, 2)]);
    app(CostoPromedioService::class)->actualizarPorEntrada($producto, $cantidad, $nuevoCosto);
}
```

#### `AjusteInventarioService::procesarLinea()` — Entradas con costo
```php
// En resolveMovimiento(), para entradas con costo > 0:
if ($cantidad > 0 && $costoUnitario > 0) {
    app(CostoPromedioService::class)->actualizarPorEntrada($producto, $cantidad, $costoUnitario);
}
```

### 2.5 Servicio `ReporteInventarioService` — Valorizado con CPP
```php
// En conIngresos() — línea 49 aprox:
'valorizado' => round(
    (float) $producto->stock * 
    $this->obtenerCostoValorizado($producto), 
    2
),

private function obtenerCostoValorizado(Producto $producto): float
{
    $cpp = (float) $producto->costo_promedio_ponderado;
    return $cpp > 0 ? $cpp : (float) $producto->precio_compra;
}
```

### 2.6 Inicialización Masiva (Command/Seeder)
```bash
php artisan tinker --execute="
app(App\Services\CostoPromedioService::class)->inicializarCppExistentes();
"
```

---

## 3. Fase 18 — Cambio Aceite Correlativo CAM-XXXX

### 3.1 Migración
```php
// add_correlativo_to_cambio_aceites_table.php
Schema::table('cambio_aceites', function (Blueprint $table) {
    $table->string('correlativo', 20)->unique()->nullable()->after('id');
    $table->index('correlativo');
});
```

### 3.2 Modelo `CambioAceite`
```php
protected $fillable = [
    // ...
    'correlativo',
];

protected static function booted()
{
    static::creating(function ($cambio) {
        if (empty($cambio->correlativo) && $cambio->estado === 'confirmado') {
            $cambio->correlativo = self::generarCorrelativo();
        }
    });
}

public static function generarCorrelativo(): string
{
    $max = static::where('correlativo', 'like', 'CAM-%')
        ->max('correlativo');
    $next = $max ? ((int) substr($max, 4)) + 1 : 1;
    return 'CAM-'.str_pad($next, 4, '0', STR_PAD_LEFT);
}
```

### 3.3 `CambioAceiteController::confirmar()`
```php
public function confirmar(Request $request, CambioAceite $cambio): RedirectResponse
{
    if ($cambio->estado !== 'pendiente') {
        return back()->with('error', 'Solo cambios pendientes pueden confirmarse.');
    }

    DB::transaction(function () use ($cambio) {
        $cambio = CambioAceite::lockForUpdate()->findOrFail($cambio->id);
        
        // Generar correlativo si no tiene
        if (empty($cambio->correlativo)) {
            $cambio->correlativo = self::generarCorrelativo();
        }

        // Procesar productos con lock
        $productosIds = $cambio->productos->pluck('id')->unique()->sort()->values();
        $productos = Producto::whereIn('id', $productosIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($cambio->productos as $productoMod) {
            $producto = $productos[$productoMod->id];
            $cantidad = $productoMod->pivot->cantidad;
            // ... validar stock, descontar, Kardex ...
        }

        $cambio->update([
            'estado' => 'confirmado',
            'fecha_confirmacion' => now(),
        ]);

        app(AuditService::class)->anotarAccion('confirmar cambio aceite');
    });

    return redirect()->route('cambio-aceite.show', $cambio)
        ->with('success', 'Cambio de aceite confirmado como '.$cambio->correlativo);
}
```

### 3.3 KardexSeeder — Usar Correlativo
```php
// En KardexSeeder.php, loop de cambios:
$origenId = $cambio->correlativo ?? 'CAM-'.str_pad($cambio->id, 4, '0', STR_PAD_LEFT);
```

### 3.4 Vistas
```blade
{{-- cambio-aceite/index.blade.php --}}
<th>Correlativo</th>
<td>{{ $cambio->correlativo ?? '<span class="text-gray-400">—</span>' }}</td>

{{-- cambio-aceite/show.blade.php --}}
<div>
    <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Correlativo</p>
    <p class="text-sm font-mono text-primary">{{ $cambio->correlativo ?? '—' }}</p>
</div>
<div>
    <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Vehículo</p>
    <p class="text-sm text-secondary">{{ $cambio->automotor->placa ?? '—' }}</p>
</div>
```

---

## 4. Fase 19 — Concurrencia Ventas (lockForUpdate)

### 4.1 Trait `LocksProductos` (Reutilizable)
```php
// app/Traits/LocksProductos.php
trait LocksProductos
{
    protected function lockProductosAsc($productos): Collection
    {
        $ids = $productos instanceof Collection
            ? $productos->pluck('id')->unique()->sort()->values()
            : collect($productos)->unique()->sort()->values();

        return Producto::whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
```

### 4.2 `VentaController::store()` — Con Lock
```php
use LocksProductos;

public function store(Request $request): RedirectResponse
{
    $validated = $request->validate($this->reglas(), self::MENSAJES);

    $venta = DB::transaction(function () use ($validated, $request) {
        [$lineas, $subtotal] = $this->prepararLineas($validated['detalle']);

        // Lock productos en orden ASC
        $productosIds = collect($lineas)->pluck('producto_id')->unique()->sort()->values();
        $productos = Producto::whereIn('id', $productosIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // Validar stock dentro de transacción con lock
        foreach ($lineas as $linea) {
            $producto = $productos[$linea['producto_id']] ?? null;
            if (! $producto) {
                throw new \RuntimeException('Producto no encontrado.');
            }
            if ($producto->stock < $linea['cantidad']) {
                throw new \RuntimeException(
                    "Stock insuficiente para {$producto->nombre}. Disponible: {$producto->stock}"
                );
            }
        }

        // Crear venta y descontar
        $venta = Venta::create([
            'correlativo' => $this->siguienteCorrelativoVenta(),
            'user_id' => $request->user()->id,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'estado' => 'confirmada',
            // ...
        ]);

        foreach ($lineas as $linea) {
            $producto = $productos[$linea['producto_id']];
            $stockAntes = $producto->stock;
            $cantidad = $linea['cantidad'];
            $stockDespues = $stockAntes - $cantidad;

            $producto->update([
                'stock' => $stockDespues,
                'inventario' => $stockDespues,
            ]);

            app(KardexService::class)->registrarSalida(
                $producto, $cantidad, $stockAntes, 'venta', $venta->correlativo
            );

            DetalleVenta::create($linea + ['venta_id' => $venta->id]);
        }

        return $venta;
    });

    return redirect()->route('ventas.show', $venta)
        ->with('success', 'Venta registrada correctamente.');
}
```

### 4.3 `CambioAceiteController::confirmar()` — Con Lock
```php
// En confirmar(), antes del loop de productos:
$productosIds = $cambio->productos->pluck('id')->unique()->sort()->values();
$productos = Producto::whereIn('id', $productosIds)
    ->orderBy('id')
    ->lockForUpdate()
    ->get()
    ->keyBy('id');

foreach ($cambio->productos as $productoMod) {
    $producto = $productos[$productoMod->id];
    $cantidad = $productoMod->pivot->cantidad;
    
    if ($producto->stock < $cantidad) {
        throw new \RuntimeException("Stock insuficiente para {$producto->nombre}.");
    }
    // ... descontar, Kardex ...
}
```

---

## 5. Fase 4 Completar — Retirar Modal "Actualizar Stock"

### 5.1 Eliminar Vista Modal
```blade
{{-- ELIMINAR de resources/views/productos/index.blade.php líneas 254-289 --}}
{{-- <x-modal id="stock-modal" ...> ... </x-modal> --}}
```

### 5.2 Eliminar JS
```javascript
// ELIMINAR de resources/js/productos/index.js:
// - función abrirModalStock()
// - función enviarActualizacionStock()
// - event listeners relacionados
```

### 5.3 Eliminar Controlador y Ruta
```php
// ELIMINAR de ProductoController:
// public function updateStock(Request $request, Producto $producto)

// ELIMINAR de routes/web.php:
// Route::patch('productos/{producto}/update-stock', ...)->name('productos.updateStock');
```

### 5.4 UI Reemplazo
```blade
{{-- En productos/index.blade.php, columna Acciones --}}
@can('acceso-ajustes')
<a href="{{ route('ajustes.create', ['producto' => $producto->id]) }}"
   class="inline-flex items-center gap-1 px-3 py-1.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-xs font-medium rounded-lg hover:bg-yellow-200 dark:hover:bg-yellow-900/50 transition-colors"
   title="Gestionar ajustes de inventario (reposición, merma, conteo, etc.)">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
    </svg>
    Ajustar
</a>
@endcan
```

---

## 6. Integración y Verificación

### Migraciones Orden
1. `add_stock_minimo_to_productos_table.php`
2. `add_costo_promedio_to_productos_table.php`
3. `add_correlativo_to_cambio_aceites_table.php`

### Tests Requeridos
| Archivo | Cubre |
|---------|-------|
| `tests/Feature/StockMinimoTest.php` | Alertas, badges, config legacy/minimo/ambos |
| `tests/Feature/ValorizacionCostoPromedioTest.php` | CPP update, fallback, reporte valorizado |
| `tests/Feature/CambioAceiteCorrelativoTest.php` | Generación correlativo, Kardex origen, Seeder |
| `tests/Feature/ConcurrenciaStockTest.php` | Ventas concurrentes, deadlock-free, lock ASC |

### Verificación Final
```bash
php artisan migrate --force
php artisan test --filter="StockMinimo|Valorizacion|CambioAceiteCorrelativo|ConcurrenciaStock"
php artisan test
vendor\bin\pint --test
php artisan migrate:fresh --seed --force

# Verificar CPP inicializado
php artisan tinker --execute="
echo 'CPP > 0: '.\App\Models\Producto::where('costo_promedio_ponderado', '>', 0)->count().PHP_EOL;
echo 'Stock minimo > 0: '.\App\Models\Producto::where('stock_minimo', '>', 0)->count().PHP_EOL;
"
```