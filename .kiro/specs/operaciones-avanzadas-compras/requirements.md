# .kiro/specs/operaciones-avanzadas-compras/requirements.md

# Operaciones Avanzadas de Compras — Anular Recibida + Concurrencia (Fases 7-8)

## Contexto
- **Documento base**: `docs/pendientes-implementacion-compras.md` §3-4
- **Fases**: 7 (Anular compra recibida) + 8 (Concurrencia lockForUpdate)
- **Dependencias**: Fases 0-6 completadas

---

## 1. Requisitos Funcionales — Anular Compra Recibida (Fase 7)

### RF-AC-01: Alcance de Anulación
- Solo compras en estado `recibida` pueden anularse con salida compensatoria
- Compras `borrador` → anulación simple (ya implementado)
- Compras `anulada` → no accionable

### RF-AC-02: Efectos de Anulación
Al anular una compra `recibida`, el sistema **DEBE** ejecutar atómicamente:

1. **Reversión Stock/Inventario** por cada línea:
   - `stock -= cantidad`
   - `inventario = stock` (mantiene ciclo actual)
   - Validar `stock >= 0` tras resta

2. **Kardex — Salida Compensatoria** por cada línea:
   - Tipo: SALIDA
   - Fuente: `ajuste_negativo`
   - Origen: correlativo de la compra (CMP-XXXX)
   - Cantidad: igual a la línea original
   - Costo unitario: CPP actual del producto

3. **Caja — Ingreso Compensatorio** (Nota de Crédito):
   - Monto: total de la compra
   - Descripción: "Anulación compra CMP-XXXX"
   - Tipo pago: `transferencia` (default) o configurable
   - Requiere caja abierta (validar antes de transacción)

3. **Estado y Auditoría**:
   - Estado → `anulada`
   - `fecha_anulacion` = now()
   - Auditoría: acción `anular compra recibida`

### RF-AC-03: Validaciones Previas
| Validación | Error si Falla |
|------------|----------------|
| Estado = `recibida` | "Solo compras recibidas pueden anularse con reversión" |
| Caja abierta | `error_caja` (modal igual que recepción) |
| Stock suficiente por línea | "Stock insuficiente para revertir [producto]" |

### RF-AC-04: Transaccionalidad
- Todo en **una transacción única** con `lockForUpdate`:
  1. Lock compra (`Compra::lockForUpdate()`)
  2. Lock cada producto por `producto_id` asc
  3. Validar stock ≥ cantidad por línea
  4. Ejecutar reversión + Kardex + Caja + Estado

---

## 2. Reglas de Negocio — Anulación

### RN-AC-01: Irreversibilidad Parcial
- La anulación **NO elimina** la compra original ni sus movimientos Kardex originales
- Genera movimientos **compensatorios** que dejan rastro completo:
  ```
  ENTRADA compra (original)  +20
  SALIDA ajuste_negativo (anulación) -20
  ```
- Historial completo preservado para auditoría

### RN-AC-02: Costo en Salida Compensatoria
- `costo_unitario` = CPP actual del producto (o `precio_compra` si CPP = 0)
- `costo_total` = cantidad × costo_unitario
- Fuente Kardex: `ajuste_negativo`

### RN-AC-03: Caja — Ingreso Compensatorio
- Tipo pago default: `transferencia` (nota de crédito bancaria)
- Monto = total compra original
- Descripción: "Anulación compra {correlativo} ({proveedor})"
- Requiere caja abierta (mismo patrón que recepción)

### RN-AC-03: Irreversibilidad de Anulación
- Una compra `anulada` **NO** puede revertirse (no hay "des-anular")
- Si se requiere reposición → nueva compra

---

## 3. Requisitos Funcionales — Concurrencia Stock (Fase 8)

### RF-CC-01: Problema a Resolver
```
Usuario A ve stock=2 (Producto X)
Usuario B ve stock=2 (Producto X)
A vende 2 → stock=0
B vende 2 → stock=-2  ← OVERSSELL
```

### RF-CC-02: Solución — lockForUpdate en Todas las Salidas
Aplicar `Producto::lockForUpdate()` **ordenado por `producto_id` asc** en:

| Controlador | Método | Línea Clave |
|-------------|--------|-------------|
| `VentaController` | `store()` | Lock cada producto antes de validar/descontar |
| `CambioAceiteController` | `confirmar()` | Lock cada producto antes de descontar |
| `AjusteInventarioService` | `procesarLinea()` (salidas) | Lock por producto_id asc |

### RF-CC-03: Validación Atómica
Dentro de la transacción con lock:
```php
$producto = Producto::lockForUpdate()->findOrFail($id);
if ($producto->stock < $cantidad) {
    throw new \RuntimeException("Stock insuficiente para {$producto->nombre}.");
}
// ... proceder a descontar ...
```

### RF-CC-04: Orden de Locks para Evitar Deadlocks
- **Siempre** ordenar productos por `producto_id` ASC antes de lockear
- Aplicar en: Ventas, Cambio Aceite, Ajustes (salidas), Anulación compra recibida

---

## 4. Reglas de Negocio — Concurrencia

### RN-CC-01: Validación Dentro de Transacción
- La validación `stock >= cantidad` **DEBE** ocurrir dentro de la transacción con lock
- Fuera de transacción → race condition

### RN-CC-02: Mensaje de Error Amigable
- "Stock insuficiente para {producto}. Disponible: {stock_actual}"
- No mostrar error genérico 500

### RN-CC-03: Orden de Locks Global
- **Regla universal**: `ORDER BY producto_id ASC` antes de cualquier `lockForUpdate` en lote
- Aplicar en: Ventas, Cambio Aceite, Ajustes (salidas), Anulación compra recibida

---

## 5. Casos de Uso

### CU-AC-01: Anular Compra Recibida Exitosa
1. Usuario ve compra CMP-0015 estado `recibida` (20 unidades Producto A)
2. Clic "Anular" → Modal confirmación: "Generará salida compensatoria y nota de crédito"
3. Confirma → Sistema:
   - Verifica caja abierta
   - Lock compra + lock Producto A
   - Valida stock ≥ 20
   - Transacción:
     - Stock A: 20 → 0
     - Kardex SALIDA ajuste_negativo CMP-0015 cant 20
     - Caja ingreso compensatorio monto total
     - Estado → anulada
   - Éxito: "Compra anulada con salida compensatoria registrada"

### CU-AC-02: Anulación con Stock Insuficiente
1. Compra recibida 50 Producto B
2. Ventas consumen 30 → stock actual = 20
3. Usuario intenta anular compra → Error: "Stock insuficiente para Producto B. Disponible: 20, requerido: 50"
4. Rollback completo, compra sigue `recibida`

### CU-CC-01: Venta Concurrente — Usuario A gana
1. Stock Producto X = 5
2. Usuario A y B abren venta simultánea
3. A agrega 5, B agrega 5
4. A confirma primero → lock, valida stock=5≥5, descuenta → stock=0
5. B confirma → lock (espera), valida stock=0<5 → Error "Stock insuficiente para X. Disponible: 0"
6. Rollback B, stock final = 0

### CU-CC-02: Cambio Aceite Concurrente
1. Stock Filtro Y = 2
2. Dos cambios de aceite simultáneos usan 1 cada uno
3. Primero lock → valida 2≥1 → descuenta → stock=1
4. Segundo lock (espera) → valida 1≥1 → descuenta → stock=0
5. Ambos exitosos

---

## 6. Criterios de Aceptación

| ID | Criterio |
|----|----------|
| CA-AC-01 | Anular compra recibida → stock - cantidad, Kardex salida ajuste_negativo, caja ingreso compensatorio |
| CA-AC-02 | Anular compra con stock insuficiente → error, rollback, estado sigue recibida |
| CA-AC-02 | Anular compra borrador → anulación simple (sin Kardex, sin caja) — comportamiento actual |
| CA-CC-01 | 2 ventas concurrentes mismo producto → una éxito, otra error stock insuficiente |
| CA-CC-02 | Deadlock libre: locks ordenados por producto_id ASC |
| CA-CC-03 | Venta + Cambio Aceite simultáneos mismo producto → ambos éxito si stock suficiente |
| CA-CC-03 | Stock nunca negativo tras concurrencia extrema (100 hilos concurrentes) |