# .kiro/specs/operaciones-avanzadas-compras/design.md

# Diseño Técnico — Anular Compra Recibida + Concurrencia Stock

## 1. Anular Compra Recibida — Flujo Transaccional

```
POST /compras/{compra}/anular-recibida
        │
        ▼
┌──────────────────────────────────────────────────────────────┐
│                  TRANSACCIÓN ÚNICA (DB)                      │
├──────────────────────────────────────────────────────────────┤
│  1. SELECT ... FOR UPDATE compra (lock fila)                 │
│  2. Validar estado = 'recibida'                              │
│  3. Caja::abierta() → error si null                          │
│  4. FOR cada línea ORDER BY producto_id ASC:                 │
│       SELECT ... FOR UPDATE producto                         │
│       Validar stock >= cantidad                              │
│  5. LOOP cada línea (orden producto_id ASC):                 │
│       stock_antes = producto->stock                          │
│       stock_despues = stock_antes - cantidad                 │
│       IF stock_despues < 0 → throw                           │
│       UPDATE producto SET stock=stock_despues, inventario=stock_despues │
│       KardexService::registrarSalidaCompensatoria(...)      │
│  6. CajaService::registrarIngresoCompensatorio(caja, [...]) │
│  7. UPDATE compra SET estado='anulada', fecha_anulacion=now()│
│  8. AuditService::anotarAccion('anular compra recibida')    │
└──────────────────────────────────────────────────────────────┘
```

### Secuencia de Locks (Anti-Deadlock)
```php
// 1. Lock compra (único registro)
$compra = Compra::lockForUpdate()->findOrFail($compra->id);

// 2. Lock productos en orden ASC (evita deadlock)
$productosIds = $compra->detalles->pluck('producto_id')->unique()->sort()->values();
foreach ($productosIds as $pid) {
    Producto::lockForUpdate()->findOrFail($pid);
}
```

---

## 2. Modelos y Migraciones

### 2.1 Migración: `add_fecha_anulacion_to_compras_table.php`
```php
Schema::table('compras', function (Blueprint $table) {
    $table->timestamp('fecha_anulacion')->nullable()->after('fecha_recepcion');
});
```

### 2.2 Modelo `Compra` — Métodos Adicionales
```php
public function esRecibida(): bool { return $this->estado === 'recibida'; }
public function esAnulada(): bool { return $this->estado === 'anulada'; }
public function puedeAnularse(): bool {
    return in_array($this->estado, ['borrador', 'recibida']);
}
```

### 2.3 `KardexService` — Nuevo Método
```php
public function registrarSalidaCompensatoria(
    Producto $producto,
    int $cantidad,
    int $stockAntes,
    string $origenId  // correlativo compra CMP-XXXX
): void {
    $cpp = app(CostoPromedioService::class)->obtenerCpp($producto);
    
    $this->registrar(
        $producto,
        'salida',
        $cantidad,
        $stockAntes,
        $stockAntes - $cantidad,
        'ajuste_negativo',
        $origenId,
        $cpp  // costo_unitario = CPP
    );
}
```

---

## 3. Controlador — `CompraController::anularRecibida()`

```php
public function anularRecibida(Request $request, Compra $compra): RedirectResponse
{
    // Solo compras recibidas
    if (! $compra->esRecibida()) {
        return $this->errorTransicion($compra, 'anular con reversión');
    }

    // Caja debe estar abierta ANTES de transacción
    $caja = app(CajaService::class)->getCajaActiva();
    if (! $caja) {
        return redirect()->route('compras.show', $compra)
            ->with('error_caja', true);
    }

    try {
        DB::transaction(function () use ($compra, $caja, $request) {
            // 1. Lock compra
            $compra = Compra::lockForUpdate()->findOrFail($compra->id);
            if (! $compra->esRecibida()) {
                throw new \RuntimeException('La compra ya no está en estado recibida.');
            }

            // 2. Lock productos ordenados ASC
            $lineas = $compra->detalles()
                ->join('productos', 'detalle_compras.producto_id', '=', 'productos.id')
                ->select('detalle_compras.*', 'productos.stock')
                ->orderBy('detalle_compras.producto_id')
                ->get();

            foreach ($lineas as $linea) {
                $producto = Producto::lockForUpdate()->findOrFail($linea->producto_id);
                
                $stockAntes = $producto->stock;
                $cantidad = (int) $linea->cantidad;
                $stockDespues = $stockAntes - $cantidad;

                if ($stockDespues < 0) {
                    throw new \RuntimeException(
                        "Stock insuficiente para {$producto->nombre}. " .
                        "Disponible: {$stockAntes}, requerido: {$cantidad}"
                    );
                }

                // Actualizar stock e inventario
                $producto->update([
                    'stock' => $stockDespues,
                    'inventario' => $stockDespues,
                ]);

                // Kardex salida compensatoria
                app(KardexService::class)->registrarSalidaCompensatoria(
                    $producto,
                    $cantidad,
                    $stockAntes,
                    $compra->correlativo
                );
            }

            // Ingreso compensatorio en caja (nota de crédito)
            $egreso = app(CajaService::class)->registrarIngresoCompensatorio($caja, [
                'monto' => (float) $compra->total,
                'descripcion' => "Anulación compra {$compra->correlativo} ({$compra->proveedor->razon_social})",
                'tipo_pago' => 'transferencia', // default para nota de crédito
                'user_id' => $request->user()->id,
            ]);

            // Marcar anulada
            $compra->update([
                'estado' => 'anulada',
                'fecha_anulacion' => now(),
            ]);

            // Auditoría
            app(AuditService::class)->anotarAccion('anular compra recibida');
        });

        return redirect()->route('compras.show', $compra)
            ->with('success', 'Compra anulada con salida compensatoria y nota de crédito registrada.');

    } catch (\RuntimeException $e) {
        return redirect()->route('compras.show', $compra)
            ->with('error', $e->getMessage());
    } catch (\Throwable $e) {
        return redirect()->route('compras.show', $compra)
            ->with('error', 'Error al anular la compra: ' . $e->getMessage());
    }
}
```

### 3.1 Ruta Nueva
```php
// routes/web.php — dentro del grupo permission:acceso-compras
Route::post('compras/{compra}/anular-recibida', [CompraController::class, 'anularRecibida'])
    ->name('compras.anular-recibida');
```

> **Nota**: La ruta `compras.anular` existente maneja solo borradores. Esta nueva ruta maneja `recibida`.

---

## 4. UI — Vista Show Compra

### Botón "Anular" para Compras Recibidas
```blade
@if($compra->esRecibida())
<form method="POST" action="{{ route('compras.anular-recibida', $compra) }}" class="inline">
    @csrf
    <button type="submit"
            data-confirm="¿Anular esta compra recibida? Se generará salida compensatoria en Kardex y nota de crédito en caja. Esta acción es irreversible."
            class="inline-flex items-center gap-2 px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-xs font-medium rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        Anular (con reversión)
    </button>
</form>
@endif
```

---

## 4. Concurrencia — lockForUpdate Global

### 4.1 Patrón Único de Lock
```php
trait LocksProductos
{
    /**
     * Bloquea productos en orden ASC para evitar deadlocks.
     * @param  Collection|int[]  $productos  Colección de productos o array de IDs
     * @return Collection<Producto>  Productos bloqueados en orden ASC
     */
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

### 4.2 Aplicación en Controladores Existentes

#### `VentaController::store()`
```php
use LocksProductos;

public function store(Request $request): RedirectResponse
{
    $validated = $request->validate($this->reglas(), self::MENSAJES);

    $venta = DB::transaction(function () use ($validated, $request) {
        // 1. Preparar líneas y calcular total
        [$lineas, $subtotal] = $this->prepararLineas($validated['detalle']);

        // 2. Lock productos en orden ASC
        $productosIds = collect($lineas)->pluck('producto_id')->unique()->sort()->values();
        $productos = Producto::whereIn('id', $productosIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // 3. Validar stock dentro de la transacción con lock
        foreach ($lineas as $linea) {
            $producto = $productos[$linea['producto_id']];
            if ($producto->stock < $linea['cantidad']) {
                throw new \RuntimeException(
                    "Stock insuficiente para {$producto->nombre}. Disponible: {$producto->stock}"
                );
            }
        }

        // 4. Crear venta y descontar stock
        $venta = Venta::create([...]);
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
    // ...
}
```

#### `CambioAceiteController::confirmar()`
```php
// Mismo patrón: lock productos ordenados ASC antes de descontar
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

#### `AjusteInventarioService::procesarLinea()` (solo salidas)
```php
// Ya implementado en Fase 5 con lockForUpdate por producto_id ASC
```

---

## 5. Vistas y UI

### 4.1 Vista `compras/show.blade.php` — Botón Anular Recibida
```blade
@if($compra->esRecibida())
<form method="POST" action="{{ route('compras.anular-recibida', $compra) }}" class="inline">
    @csrf
    <button type="submit"
            data-confirm="¿Anular esta compra recibida? Se generará salida compensatoria en Kardex y nota de crédito en caja. Esta acción es irreversible."
            class="inline-flex items-center gap-2 px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-xs font-medium rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        Anular (con reversión)
    </button>
</form>
@endif
```

### 4.2 Modal `error_caja` — Reutilizar existente
```blade
@if(session('error_caja'))
<x-modal id="modal-caja-cerrada" title="Caja Cerrada" open>
    <div class="px-6 pb-4 sm:pb-6">
        <div class="flex items-start gap-3">
            <svg class="h-6 w-6 text-red-600 dark:text-red-400" ...>...</svg>
            <p class="text-sm text-secondary">No puedes anular la compra porque no hay ninguna sesión de caja activa.</p>
        </div>
    </div>
    <x-slot:footer>
        <a href="{{ route('caja.index') }}" class="...">Ir a Caja</a>
        <a href="{{ route('compras.show', $compra) }}" class="...">Cancelar</a>
    </x-slot:footer>
</x-modal>
@endif
```

---

## 5. Tests

### 5.1 `tests/Feature/CompraAnularRecibidaTest.php`
```php
// Property 1: Anular recibida → stock - cant, Kardex salida ajuste_negativo, caja ingreso compensatorio
// Property 2: Anular con stock insuficiente → error + rollback
// Property 3: Anular borrador → anulación simple (sin Kardex/caja) — comportamiento actual
// Property 4: Anular ya anulada → error
// Property 4: Caja cerrada → error_caja modal
// Property 5: Stock insuficiente línea 2 → rollback completo (línea 1 también revertida)
```

### 5.2 `tests/Feature/ConcurrenciaStockTest.php`
```php
// Property 1: 2 ventas concurrentes mismo producto → una éxito, otra error stock insuficiente
// Property 2: Venta + Cambio Aceite simultáneos mismo producto → ambos éxito si stock suficiente
// Property 3: 100 hilos concurrentes mismo producto → stock nunca negativo, suma correcta
// Property 4: Deadlock libre: locks ordenados ASC → sin deadlock en 100 iteraciones
```

---

## 5. Integración y Verificación

### Migraciones
1. `add_fecha_anulacion_to_compras_table.php`

### Rutas
```php
Route::post('compras/{compra}/anular-recibida', [CompraController::class, 'anularRecibida'])
    ->name('compras.anular-recibida');
```

### Auditoría
- `AccionesAuditoriaController::$acciones` → agregar `anular compra recibida`

### Verificación Final
```bash
php artisan test --filter="CompraAnularRecibida|ConcurrenciaStock"
php artisan test
vendor\bin\pint --test
```

### Criterios Finales
- [ ] Anular recibida → stock - cant, Kardex salida ajuste_negativo, caja ingreso compensatorio
- [ ] Stock insuficiente → error + rollback completo
- [ ] 100 hilos concurrentes mismo producto → stock nunca negativo
- [ ] Locks ordenados ASC → sin deadlock
- [ ] Pint passed, tests pass (salvo preexistentes)