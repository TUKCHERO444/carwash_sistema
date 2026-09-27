# .kiro/specs/mejoras-inventario-ventas/tasks.md

# Tareas — Mejoras Inventario y Ventas (Fases 9, 10, 18, 19, 4 Completar)

---

## Fase 9 — Stock Mínimo Real

### 9.1 Migración y Modelo
- [ ] **9.1.1** `add_stock_minimo_to_productos_table.php` — `stock_minimo` unsignedInteger default 0 after inventario + índice
- [ ] **9.1.2** `Producto.php` — agregar `stock_minimo` a `$fillable` y `$casts`, métodos `estaEnAlerta()`, `estaAgotado()`, `getEstadoAlertaAttribute()`

### 9.2 Configuración Legacy
- [ ] **9.2.1** `config/app.php` — `stock_alert_mode` = `env('STOCK_ALERT_MODE', 'ambos')`
- [ ] **9.2.2** `Producto.php` — property `alerta_legacy` (75% consumo) para compatibilidad

### 9.3 UI — Badges
- [ ] **9.3.1** `resources/views/productos/index.blade.php` — badges: AGOTADO (rojo), REPONER amarillo (stock_minimo), legacy 75% (naranja), OK verde
- [ ] **9.3.2** Respeto a `config('app.stock_alert_mode')` = legacy/minimo/ambos

### 9.3 Factory / Seeder
- [ ] **9.3.1** `ProductoFactory.php` — `stock_minimo` => `faker->numberBetween(0, 20)`
- [ ] **9.3.2** `ProductoSeeder.php` — `stock_minimo` => `faker->numberBetween(0, 10)`

---

## Fase 10 — Costo Promedio Ponderado (CPP)

### 10.1 Migración y Modelo
- [ ] **10.1.1** `add_costo_promedio_to_productos_table.php` — `costo_promedio_ponderado` decimal(10,4) default 0 after precio_compra + índice
- [ ] **10.1.2** `Producto.php` — agregar a `$fillable` y `$casts` (decimal:4)

### 10.2 Servicio `CostoPromedioService`
- [ ] **10.2.1** `obtenerCpp(Producto)` — fallback a `precio_compra` si CPP = 0
- [ ] **10.2.2** `actualizarPorEntrada(Producto, cantidad, costoUnitario)` — fórmula PMP
- [ ] **10.2.3** `inicializarCppExistentes()` — command para migrar productos existentes con `precio_compra > 0`

### 10.3 Integración en Entradas
- [ ] **10.3.1** `CompraController::recibir()` — tras actualizar stock, llamar `actualizarPorEntrada()` con costo del detalle
- [ ] **10.3.2** `AjusteInventarioService::procesarLinea()` — en entradas con costo > 0, llamar `actualizarPorEntrada()`

### 10.4 Reportes
- [ ] **10.4.1** `ReporteInventarioService::resumenValorizado()` — usar `obtenerCostoValorizado()` (CPP > 0 ? CPP : precio_compra)
- [ ] **10.4.2** `obtenerCostoValorizado(Producto)` — fallback CPP > 0 ? CPP : precio_compra

### 10.5 Inicialización Masiva
- [ ] **10.5.1** Command/Seeder `inicializarCppExistentes()` — migrar productos existentes con `precio_compra > 0` y CPP = 0

### 10.5 Tests
- [ ] **10.5.1** `tests/Feature/ValorizacionCostoPromedioTest.php` — property tests: CPP update, fallback, reporte, salidas no cambian CPP
- [ ] **10.5.2** `tests/js/...` property tests fast-check para fórmula CPP

---

## Fase 18 — Cambio Aceite Correlativo CAM-XXXX

### 18.1 Migración y Modelo
- [ ] **18.1.1** `add_correlativo_to_cambio_aceites_table.php` — `correlativo` string(20) unique nullable after id + índice
- [ ] **18.1.2** `CambioAceite.php` — `correlativo` en `$fillable`, método estático `generarCorrelativo()`, boot `creating` para auto-generar en confirmar

### 18.2 Controlador
- [ ] **18.2.1** `CambioAceiteController::confirmar()` — generar correlativo si vacío antes de procesar productos

### 18.3 KardexSeeder
- [ ] **18.3.1** `KardexSeeder.php` — usar `$cambio->correlativo ?? 'CAM-'.str_pad($cambio->id, 4, '0', STR_PAD_LEFT)` como origen_id

### 18.4 Vistas
- [ ] **18.4.1** `resources/views/cambio-aceite/index.blade.php` — columna Correlativo
- [ ] **18.4.2** `resources/views/cambio-aceite/show.blade.php` — mostrar correlativo + placa

### 18.5 Tests
- [ ] **18.5.1** `tests/Feature/CambioAceiteCorrelativoTest.php` — generar correlativo, Kardex origen=CAM-XXXX, Seeder usa correlativo

---

## Fase 19 — Concurrencia Ventas (lockForUpdate)

### 19.1 Trait LocksProductos
- [ ] **19.1.1** `app/Traits/LocksProductos.php` — `lockProductosAsc()` ordena IDs ASC y `lockForUpdate()`

### 19.2 VentaController::store()
- [ ] **19.2.1** Usar trait `LocksProductos`
- [ ] **19.2.2** Lock productos en orden ASC antes de validar stock
- [ ] **19.2.3** Validar stock dentro de transacción con lock
- [ ] **19.2.3** Error amigable: "Stock insuficiente para {producto}. Disponible: {stock}"

### 19.3 CambioAceiteController::confirmar()
- [ ] **19.3.1** Lock productos en orden ASC antes de descontar stock

### 19.3 Tests
- [ ] **19.3.1** `tests/Feature/ConcurrenciaStockTest.php` — property tests: ventas concurrentes, deadlock-free, lock ASC, 100 hilos

---

## Fase 4 Completar — Retirar Modal "Actualizar Stock"

### 4.1 Eliminar Vista Modal
- [ ] **4.1.1** Eliminar `resources/views/productos/index.blade.php` líneas 254-289 (modal `#stock-modal`)

### 4.2 Eliminar JS
- [ ] **4.2.1** Eliminar de `resources/js/productos/index.js`: `abrirModalStock()`, `enviarActualizacionStock()`, event listeners

### 4.3 Eliminar Controlador/Ruta
- [ ] **4.3.1** Eliminar `ProductoController::updateStock()` (método completo)
- [ ] **4.3.2** Eliminar ruta `productos.updateStock` en `routes/web.php`

### 4.3 UI Reemplazo
- [ ] **4.3.1** En `productos/index.blade.php` — enlace "Ajustar" → `route('ajustes.create', ['producto' => $producto->id])` con tooltip

---

## Verificación Final

### Ejecutar en Orden
```bash
# 1. Migraciones
php artisan migrate --force

# 2. Inicializar CPP existentes
php artisan tinker --execute="app(App\Services\CostoPromedioService::class)->inicializarCppExistentes();"

# 3. Tests
php artisan test --filter="StockMinimo|ValorizacionCostoPromedio|CambioAceiteCorrelativo|ConcurrenciaStock"
php artisan test

# 3. Lint + suite completa
vendor\bin\pint --test
php artisan test

# 4. Seed coherente
php artisan migrate:fresh --seed --force
php artisan tinker --execute="
echo 'Stock minimo > 0: '.\App\Models\Producto::where('stock_minimo', '>', 0)->count().PHP_EOL;
echo 'CPP > 0: '.\App\Models\Producto::where('costo_promedio_ponderado', '>', 0)->count().PHP_EOL;
echo 'Cambio aceite con correlativo: '.\App\Models\CambioAceite::whereNotNull('correlativo')->count().PHP_EOL;
"
```

### Criterios de Finalización
- [ ] 466+ tests pasan (2 fallos preexistentes máx)
- [ ] `vendor\bin\pint --test` -> passed
- [ ] `migrate:fresh --seed` coherente
- [ ] Stock mínimo -> badges correctos (AGOTADO, REPONER, legacy)
- [ ] CPP se actualiza en entradas, no en salidas
- [ ] Reporte valorizado usa CPP > 0, fallback precio_compra
- [ ] Cambio aceite genera CAM-XXXX al confirmar
- [ ] 100 hilos concurrentes venta -> stock nunca negativo, sin deadlock
- [ ] Modal "Actualizar stock" eliminado, enlace "Ajustar" -> ajustes.create
- [ ] Pint passed, tests pass (salvo 2 preexistentes)