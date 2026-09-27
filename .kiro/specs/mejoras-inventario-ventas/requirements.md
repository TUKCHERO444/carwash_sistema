# .kiro/specs/mejoras-inventario-ventas/requirements.md

# Mejoras Inventario y Ventas — Stock Mínimo, CPP, Cambio Aceite, Concurrencia Ventas, Retirar Modal (Fases 9, 10, 18, 19, 4)

## Contexto
- **Documento base**: `docs/pendientes-implementacion-compras.md` §5-6, 18-19, 4
- **Fases**: 9 (Stock Mínimo), 10 (CPP), 18 (Cambio Aceite Correlativo), 19 (Concurrencia Ventas), 4 Completar (Retirar Modal)
- **Dependencias**: Fases 0-8 completadas

---

## 1. Fase 9 — Stock Mínimo Real (Criterio de Abastecimiento)

### RF-SM-01: Campo `stock_minimo` en Productos
- Columna: `stock_minimo` `integer` `default(0)` `unsigned` after `inventario`
- Significado: umbral mínimo de stock para alerta de reposición
- Valor 0 = sin alerta (comportamiento legacy)

### RF-SM-02: Lógica de Alerta
```php
// En Producto model
public function estaEnAlerta(): bool {
    return $this->stock > 0 && $this->stock_minimo > 0 && $this->stock <= $this->stock_minimo;
}

public function estaAgotado(): bool {
    return $this->stock === 0;
}

public function getEstadoAlertaAttribute(): string {
    if ($this->estaAgotado()) return 'agotado';
    if ($this->estaEnAlerta()) return 'alerta';
    return 'normal';
}
```

### RF-SM-03: UI — Badges de Alerta
| Estado | Badge | Prioridad |
|--------|-------|-----------|
| Agotado (stock=0) | `bg-red-600` "AGOTADO" | Alta |
| Alerta (stock ≤ stock_minimo) | `bg-yellow-600` "REPONER (mín: X)" | Media |
| Normal | Sin badge / verde | Baja |

### RF-SM-04: Compatibilidad Legacy
- Config `config('app.stock_alert_mode')` = `'legacy' | 'minimo' | 'ambos'`
- Default: `'ambos'` (muestra ambos badges durante transición)
- Legacy: alerta 75% consumo (cálculo actual)

### RF-SM-05: Factory / Seeder
```php
// ProductoFactory
'stock_minimo' => $this->faker->numberBetween(0, 20),

// ProductoSeeder
'stock_minimo' => $faker->numberBetween(0, 10),
```

---

## 2. Fase 10 — Valorización Costo Promedio Ponderado (CPP)

### RF-CPP-01: Campo en Productos
- `costo_promedio_ponderado` `decimal(10,4)` `default(0)` after `precio_compra`
- Precisión 4 decimales para evitar redondeo acumulativo

### RF-CPP-02: Fórmula de Actualización
```cpp
// Al recibir ENTRADA con costo > 0
$cpp_nuevo = (($stock_actual - $cantidad_nueva) * $cpp_actual + $cantidad_nueva * $costo_nuevo) / $stock_actual;
```
- `stock_actual` = stock **después** de sumar la entrada
- `cantidad_nueva` = cantidad de la entrada entrante
- `costo_nuevo` = costo unitario de la entrada
- `cpp_actual` = CPP antes de la entrada (0 si no existía)

### RF-CPP-03: Comportamiento en Salidas
- **CPP NO cambia en salidas** (método PMP/Promedio Ponderado)
- Solo se actualiza en ENTRADAS con costo > 0

### RF-CPP-04: Integración en Operaciones
| Operación | Actualiza CPP |
|-----------|---------------|
| Compra recibida (costo > 0) | SÍ |
| Ajuste positivo (costo > 0) | SÍ |
| Ajuste entrada conteo_fisico | SÍ (costo = CPP actual) |
| Ventas / Salidas | NO |
| Ajuste negativo / merma / daño | NO |

### RF-CPP-05: Valorizado en Reportes
- `ReporteInventarioService::resumenValorizado()`:
  - Si `costo_promedio_ponderado > 0` → usa CPP
  - Si `costo_promedio_ponderado = 0` → fallback a `precio_compra`
  - `valorizado = stock × costo_usado`

### RF-CPP-06: Inicialización
- Productos existentes con `precio_compra > 0` → CPP = `precio_compra`
- Productos con `precio_compra = 0` → CPP = 0 (se actualizará en próxima entrada)

---

## 3. Fase 18 — Cambio de Aceite: Correlativo CAM-XXXX

### RF-CA-01: Campo Correlativo
- Migración: `add_correlativo_to_cambio_aceites_table.php`
- `correlativo` `string(20)` `unique` `nullable` after `id`
- Formato: `CAM-0001` (prefijo `CAM-`, 4 dígitos, secuencia global)

### RF-CA-02: Generación en Confirmación
```php
// En CambioAceiteController::confirmar() o CambioAceiteService
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

### RF-CA-03: Kardex — Origen = Correlativo
- Al confirmar cambio de aceite → Kardex SALIDA `fuente=cambio_aceite`, `origen_id=CAM-XXXX`
- Mantener `placa` en campo separado (ya existe `automotor_id` → placa via relación)

### RF-CA-03: KardexSeeder — Usar Correlativo
```php
$origenId = $cambio->correlativo ?? 'CAM-'.str_pad($cambio->id, 4, '0', STR_PAD_LEFT);
```

### RF-CA-04: Vistas
- `index`: columna "Correlativo"
- `show`: mostrar correlativo + placa (via `$cambio->automotor->placa`)
- `index.js` / `edit.js` — sin cambios (correlativo se genera en confirmar)

---

## 4. Fase 19 — Concurrencia Ventas (lockForUpdate)

### RF-CV-01: Aplicar lockForUpdate en VentaController::store()
Ver diseño técnico en `operaciones-avanzadas-compras/design.md` §4.2

### RF-CV-02: Validación Atómica Dentro de Transacción
```php
$producto = Producto::lockForUpdate()->findOrFail($linea['producto_id']);
if ($producto->stock < $cantidad) {
    throw new \RuntimeException(
        "Stock insuficiente para {$producto->nombre}. Disponible: {$producto->stock}"
    );
}
```

### RF-CV-03: Orden de Locks ASC
- `ORDER BY id ASC` antes de `lockForUpdate()` en lote
- Aplicar en: Ventas, Cambio Aceite, Ajustes (salidas), Anulación compra recibida

---

## 5. Fase 4 Completar — Retirar Modal "Actualizar Stock"

### RF-RS-01: Eliminar Componente Modal
- Eliminar `resources/views/productos/index.blade.php` líneas 254-289 (modal `#stock-modal`)
- Eliminar lógica JS en `resources/js/productos/index.js` → `abrirModalStock()`, `enviarActualizacionStock()`

### RF-RS-02: Eliminar Controlador/Ruta
- Eliminar `ProductoController::updateStock()` (líneas ~243-282)
- Eliminar ruta `productos.updateStock` en `routes/web.php`

### RF-RS-03: Reemplazo en UI
- En tabla productos (`index.blade.php`): enlace "Ajustar" → `route('ajustes.create')`
- Tooltip: "Gestionar ajustes de inventario (reposición, merma, conteo, etc.)"

---

## 5. Casos de Uso Resumen

### CU-SM-01: Alerta Stock Mínimo
1. Producto A: stock=5, stock_minimo=10
2. Venta consume 2 → stock=3
3. UI muestra badge "REPONER (mín: 10)"
4. Usuario clic "Ajustar" → redirige a ajustes.create con producto preseleccionado

### CU-CPP-01: CPP se Actualiza en Recepción
1. Producto X: stock=10, CPP=20.00
2. Compra recibida: 20 unidades a 25.00
3. Nuevo CPP = ((10×20) + (20×25)) / 30 = 23.33
4. Reporte valorizado usa 23.33 × 30 = 699.90

### CU-CPP-02: Fallback a precio_compra
1. Producto Y: CPP=0, precio_compra=15, stock=10
2. Reporte valorizado = 10 × 15 = 150

### CU-CA-01: Confirmar Cambio Aceite → Correlativo
1. Cambio aceite pendiente → clic "Confirmar"
2. Sistema genera CAM-0015
3. Kardex SALIDA fuente=cambio_aceite origen=CAM-0015
4. Vista show muestra "CAM-0015" + placa

---

## 6. Criterios de Aceptación

| ID | Criterio |
|----|----------|
| CA-SM-01 | Producto stock=5, min=10 → badge "REPONER (mín: 10)" |
| CA-SM-02 | Producto stock=0 → badge "AGOTADO" |
| CA-SM-03 | Config `legacy` muestra badge 75% consumo; `minimo` muestra stock_minimo |
| CA-CPP-01 | Entrada 20u a 25 → CPP actualizado correctamente ((10×20 + 20×25)/30) |
| CA-CPP-02 | Venta NO cambia CPP |
| CA-CPP-03 | Reporte valorizado usa CPP si > 0, sino precio_compra |
| CA-CA-01 | Confirmar cambio aceite → correlativo CAM-XXXX, Kardex origen=CAM-XXXX |
| CA-CC-01 | 100 hilos concurrentes venta mismo producto → stock nunca negativo |
| CA-RS-01 | Modal "Actualizar stock" eliminado; enlace "Ajustar" → ajustes.create |
| CA-CA-02 | KardexSeeder usa correlativo CAM-XXXX (o fallback id) |