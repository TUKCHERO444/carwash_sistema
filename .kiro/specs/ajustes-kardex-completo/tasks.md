# .kiro/specs/ajustes-kardex-completo/tasks.md

# Tareas — Ajustes de Inventario + Kardex Completo (Fases 5-6)

## Fase 5 — Módulo de Ajustes de Inventario

### 5.1 Migraciones
- [ ] **5.1.1** `create_ajustes_inventario_table.php` — cabecera con correlativo AJU-XXXX, tipo enum, motivo, observaciones, user_id
- [ ] **5.1.2** `create_detalle_ajustes_inventario_table.php` — líneas con cantidad firmada, stock_antes/despues, FK ajustes+productos
- [ ] **5.1.3** Ejecutar migraciones y verificar en MySQL + SQLite

### 5.2 Modelos
- [ ] **5.2.1** `AjusteInventario.php` — fillable, casts, relaciones, constantes TIPOS, helpers `esEntrada()`, `esSalida()`, `esConteo()`, `etiquetaTipo()`
- [ ] **5.2.2** `DetalleAjusteInventario.php` — pivot con cantidad, stock_antes/despues
- [ ] **5.2.3** Actualizar `Producto.php` — relación `ajustes()` (belongsToMany con pivot)

### 5.3 Servicios
- [ ] **5.3.1** `AjusteInventarioService.php` — `crear()`, `prepararLineas()`, `procesarLinea()`, `resolveMovimiento()`, `siguienteCorrelativo()`
- [ ] **5.3.2** Actualizar `KardexService.php` — método `registrar()` con `costo_unitario`/`costo_total`, actualizar `registrarEntrada()`, `registrarSalida()`, `registrarEntradaCompra()` para aceptar costo
- [ ] **5.3.3** `CostoPromedioService.php` — `obtenerCpp()`, `actualizarPorEntrada()`
- [ ] **5.3.4** Tests unitarios para `CostoPromedioService` (property tests con fast-check)

### 5.4 Controlador
- [ ] **5.4.1** `AjusteInventarioController.php` — index, create, store, show
- [ ] **5.4.2** Validaciones por tipo (motivo obligatorio para merma/daño/conteo)
- [ ] **5.4.3** Manejo de `conteo_fisico` (campo `conteo_fisico` vs `cantidad`)
- [ ] **5.4.4** Manejo de errores: `RuntimeException` → redirect con error; transacción automática

### 5.4 Vistas
- [ ] **5.4.1** `resources/views/ajustes/index.blade.php` — tabla paginada, filtros tipo/fecha, badges tipo
- [ ] **5.4.2** `resources/views/ajustes/create.blade.php` — líneas dinámicas, campo condicional conteo_fisico, select productos activos con stock actual
- [ ] **5.4.3** `resources/views/ajustes/show.blade.php` — cabecera, líneas, panel Kardex generado
- [ ] **5.4.4** `@vite('resources/js/ajustes/create.js')` en create y edit

### 5.5 Frontend (JS)
- [ ] **5.5.1** `resources/js/ajustes/create.js` — líneas dinámicas, validación ≥1 línea, cantidad ≠ 0, campo condicional conteo_fisico, sincronización hidden inputs
- [ ] **5.5.2** Registrar en `vite.config.js` → `resources/js/ajustes/create.js`
- [ ] **5.5.3** Tests property `tests/js/ajustes/create.property.test.js` (fast-check: ≥1 línea, cantidad≠0, conteo_fisico delta correcto)

### 5.5 Rutas y Permisos
- [ ] **5.5.1** `routes/web.php` — `Route::resource('ajustes', ...)` bajo `permission:acceso-ajustes`
- [ ] **5.5.2** `PermissionSeeder.php` — agregar `acceso-ajustes` en bloque Inventario/Servicios
- [ ] **5.5.3** `layouts/app.blade.php` — enlace "Ajustes" en Gestión Administrativa, `$gestionAdministrativaActive` incluye `ajustes.*`

### 5.6 Auditoría
- [ ] **5.6.1** `AuditServiceProvider.php` — agregar `AjusteInventario::class` a `$modelos`
- [ ] **5.6.2** `AuditService.php` — agregar `DetalleAjusteInventario::class` a `EXCLUIDOS`
- [ ] **5.6.3** `AccionesAuditoriaController.php` — agregar `crear ajuste` a `$acciones`

### 5.7 Tests Funcionales
- [ ] **5.7.1** `tests/Feature/AjusteInventarioTest.php` — 8 property tests (ver requirements CA-01 a CA-09)
- [ ] **5.7.2** `tests/js/ajustes/create.property.test.js` — 3 property tests fast-check

---

## Fase 6 — Kardex Completo (Costo + Fuentes Faltantes)

### 6.1 Migración Costo en Kardex
- [ ] **6.1.1** `add_costo_to_movimientos_kardex_table.php` — `costo_unitario` decimal(10,2) nullable, `costo_total` decimal(12,2) nullable
- [ ] **6.1.2** Ejecutar migración en MySQL y SQLite; verificar compatibilidad legacy (NULL en movimientos antiguos)

### 6.2 Modelo y Servicio Kardex
- [ ] **6.2.1** `MovimientoKardex.php` — agregar `costo_unitario`, `costo_total` a `$fillable` y `$casts`
- [ ] **6.2.2** `KardexService.php` — método `registrar()` con `costo_unitario`/`costo_total`; actualizar `registrarEntrada()`, `registrarSalida()`, `registrarEntradaCompra()` para aceptar costo
- [ ] **6.2.3** `CostoPromedioService::obtenerCpp()` — fallback a `precio_compra` si CPP = 0

### 6.3 Integración en Operaciones Existentes
- [ ] **6.3.1** `CompraController::recibir()` — pasar `costo_unitario` del detalle a `registrarEntradaCompra()`
- [ ] **6.3.2** `VentaController::store()` — pasar CPP a `KardexService::registrarSalida()`
- [ ] **6.3.3** `CambioAceiteController::confirmar()` — pasar CPP a `registrarSalida()`
- [ ] **6.3.4** `AjusteInventarioService::procesarLinea()` — pasar CPP según tipo (entrada/salida)
- [ ] **6.3.4** `AjusteInventarioService` — llamar `CostoPromedioService::actualizarPorEntrada()` tras ENTRADA con costo > 0

### 6.4 Fuentes Completas en Kardex
- [ ] **6.4.1** Verificar que `fuente` en `movimientos_kardex` admite todas: `venta`, `cambio_aceite`, `compra`, `ajuste_positivo`, `ajuste_negativo`, `merma`, `daño`, `conteo_fisico`, `inventario`
- [ ] **6.4.2** Verificar que `fuente` = `string(30)` admite valores futuros

### 6.5 Vistas Kardex
- [ ] **6.5.1** `resources/views/kardex/index.blade.php` — agregar columnas `Costo Unit.`, `Costo Total` en tabla
- [ ] **6.5.2** `resources/views/kardex/show.blade.php` — mostrar costo en detalle de movimiento

### 6.5 Tests Kardex con Costo
- [ ] **6.5.1** `tests/Feature/KardexCostoTest.php` — property tests:
  - ENTRADA compra → costo_unitario = costo_compra, costo_total = cant × costo
  - SALIDA venta → costo_unitario = CPP, costo_total = cant × CPP
  - ENTRADA ajuste_positivo → costo = CPP
  - SALIDA merma → costo = CPP
  - Movimientos legacy → costo = NULL
- [ ] **6.5.2** Verificar `ReporteInventarioService::resumenValorizado()` usa CPP si > 0

---

## Verificación Final

### Ejecutar en Orden
```bash
# 1. Migraciones
php artisan migrate --force

# 2. Tests unitarios y funcionales
php artisan test --filter="AjusteInventario"
php artisan test --filter="KardexCosto"
php artisan test --filter="CostoPromedio"

# 3. Suite completa + lint
php artisan test
vendor\bin\pint --test

# 4. Verificación migración + seed
php artisan migrate:fresh --seed --force
php artisan tinker --execute="
echo 'Ajustes: '.\App\Models\AjusteInventario::count().PHP_EOL;
echo 'Kardex con costo: '.\App\Models\MovimientoKardex::whereNotNull('costo_unitario')->count().PHP_EOL;
echo 'CPP > 0: '.\App\Models\Producto::where('costo_promedio_ponderado', '>', 0)->count().PHP_EOL;
"
```

### Criterios de Finalización
- [ ] 466+ tests pasan (2 fallos preexistentes máx)
- [ ] `vendor\bin\pint --test` → passed
- [ ] `migrate:fresh --seed` coherente (stock > 0 construido desde compras/ajustes)
- [ ] Kardex muestra costo en todas las fuentes nuevas
- [ ] CPP se actualiza tras entradas con costo > 0