# .kiro/specs/ajustes-kardex-completo/requirements.md

# Ajustes de Inventario + Kardex Completo (Fases 5-6)

## Contexto
- **Documento base**: `docs/pendientes-implementacion-compras.md` §1-2
- **Fases**: 5 (Ajustes) + 6 (Kardex completo con costo y fuentes)
- **Dependencias**: Fases 0-4 completadas (Compras, Recepción, Kardex base)

---

## 1. Requisitos Funcionales — Ajustes de Inventario (Fase 5)

### RF-AJ-01: Tipos de Ajuste
El sistema **DEBE** soportar 5 tipos de ajuste con comportamiento diferenciado:

| Tipo | Efecto Stock | Kardex | Descripción |
|------|--------------|--------|-------------|
| `positivo` | + | ENTRADA (`ajuste_positivo`) | Reposición excepcional, donación, hallazgo |
| `negativo` | - | SALIDA (`ajuste_negativo`) | Retiro sin venta, consumo interno |
| `merma` | - | SALIDA (`merma`) | Deterioro natural, evaporación, caducidad |
| `daño` | - | SALIDA (`daño`) | Rotura, daño en manipulación |
| `conteo_fisico` | ± | ENTRADA/SALIDA (`conteo_fisico`) | Diferencia entre stock sistema vs físico |

### RF-AJ-02: Correlativo Único
- Formato: `AJU-0001` (prefijo `AJU-`, 4 dígitos, secuencia global)
- Generado al crear el ajuste (no en borrador, no hay borrador en ajustes)
- Índice único en BD

### RF-AJ-03: Detalle de Ajuste
Cada ajuste **DEBE** tener ≥1 línea con:
- `producto_id` (FK productos, activo)
- `cantidad` (entero ≠ 0; signo según tipo: + para positivo/conteo_positivo, - para negativo/merma/daño/conteo_negativo)
- `stock_antes` (captura atómica con `lockForUpdate`)
- `stock_despues` (calculado: stock_antes + cantidad)

### RF-AJ-04: Conteo Físico — Lógica Especial
- Usuario ingresa `cantidad_fisica` (stock real contado)
- Sistema calcula `delta = cantidad_fisica - stock_actual`
- Si `delta > 0` → tipo implícito `conteo_fisico` ENTRADA
- Si `delta < 0` → tipo implícito `conteo_fisico` SALIDA
- Si `delta = 0` → rechazar (no hay ajuste necesario)

### RF-AJ-05: Transaccionalidad
- Todo el ajuste (cabecera + líneas + Kardex + actualización stock/inventario) **DEBE** ejecutarse en una transacción única
- Uso de `Producto::lockForUpdate()` por línea ordenado por `producto_id` asc para evitar deadlocks
- Si falla cualquier paso → rollback completo

### RF-AJ-06: Kardex — Fuentes Nuevas
Extender `movimientos_kardex.fuente` (ya `string(30)`) con:
- `ajuste_positivo` → ENTRADA
- `ajuste_negativo` → SALIDA
- `merma` → SALIDA
- `daño` → SALIDA
- `conteo_fisico` → ENTRADA o SALIDA (según delta)

Cada movimiento **DEBE** registrar:
- `costo_unitario` = último costo promedio ponderado del producto (o `precio_compra` si CPP = 0)
- `costo_total` = cantidad × costo_unitario

### RF-AJ-07: Auditoría
- `AjusteInventario::class` en `AuditServiceProvider::$modelos`
- `DetalleAjusteInventario::class` en `AuditService::EXCLUIDOS`
- Acciones: `crear ajuste`, `anular ajuste` (si se implementa anulación futura)

---

## 2. Reglas de Negocio — Ajustes

### RN-AJ-01: Validación por Tipo
| Tipo | Cantidad Permitida | Stock Resultante |
|------|-------------------|------------------|
| `positivo` | > 0 | stock_antes + cantidad |
| `negativo` | > 0 | stock_antes - cantidad ≥ 0 |
| `merma` | > 0 | stock_antes - cantidad ≥ 0 |
| `daño` | > 0 | stock_antes - cantidad ≥ 0 |
| `conteo_fisico` | ≥ 0 (stock físico) | = cantidad_fisica |

### RN-AJ-02: Stock No Negativo
- En **cualquier** salida (negativo, merma, daño, conteo_negativo): `stock_despues ≥ 0`
- Si `stock_antes < cantidad` → rechazar con error explícito: "Stock insuficiente para [producto]"

### RN-AJ-03: Productos Inactivos
- No permitir ajustes sobre productos con `activo = 0`
- Error: "El producto [nombre] está inactivo y no admite ajustes"

### RN-AJ-04: Trazabilidad Obligatoria
- `motivo` obligatorio para `merma`, `daño`, `conteo_fisico` (mín 10 caracteres)
- `observaciones` opcional para todos

### RN-AJ-05: Costo en Kardex
- ENTRADA: `costo_unitario` = CPP del producto (o `precio_compra` si CPP = 0)
- SALIDA: `costo_unitario` = CPP actual del producto (o `precio_compra` si CPP = 0)
- `costo_total = cantidad × costo_unitario` (redondeado 2 decimales)

---

## 3. Requisitos Funcionales — Kardex Completo (Fase 6)

### RF-KC-01: Columnas de Costo en Kardex
Agregar a `movimientos_kardex`:
- `costo_unitario` `decimal(10,2)` nullable
- `costo_total` `decimal(12,2)` nullable

### RF-KC-02: Costo en Entradas por Compra (ya implementado)
- `costo_unitario` = `costo_unitario` del `DetalleCompra`
- `costo_total` = `cantidad × costo_unitario`

### RF-KC-03: Costo en Entradas por Ajuste Positivo
- `costo_unitario` = CPP del producto (o `precio_compra` si CPP = 0)
- `costo_total` = cantidad × costo_unitario

### RF-KC-03: Costo en Salidas (Venta, Cambio Aceite, Ajuste Negativo, Merma, Daño, Conteo Negativo)
- `costo_unitario` = CPP actual del producto (o `precio_compra` si CPP = 0)
- `costo_total` = cantidad × costo_unitario

### RF-KC-04: Fuentes Completas en Kardex
| Fuente | Tipo | Origen ID | Costo |
|--------|------|-----------|-------|
| `venta` | SALIDA | VTA-XXXX | CPP |
| `cambio_aceite` | SALIDA | CAM-XXXX / placa | CPP |
| `compra` | ENTRADA | CMP-XXXX | costo_compra |
| `ajuste_positivo` | ENTRADA | AJU-XXXX | CPP |
| `ajuste_negativo` | SALIDA | AJU-XXXX | CPP |
| `merma` | SALIDA | AJU-XXXX | CPP |
| `daño` | SALIDA | AJU-XXXX | CPP |
| `conteo_fisico` | ENTRADA/SALIDA | AJU-XXXX | CPP |
| `inventario` | ENTRADA | INV-XXXX (legacy) | 0 |

### RF-KC-05: Migración Segura
- `costo_unitario` y `costo_total` **nullable** para compatibilidad con movimientos legacy
- Movimientos existentes sin costo → NULL (no 0)

---

## 4. Casos de Uso

### CU-AJ-01: Ajuste Positivo — Hallazgo de Stock
1. Usuario accede a "Ajustes / Nuevo"
2. Selecciona tipo "Positivo"
3. Agrega líneas: Producto A (cant 5), Producto B (cant 3)
4. Ingresa motivo: "Hallazgo en conteo de bodega sector B"
5. Confirma → Sistema:
   - Crea cabecera AJU-0001
   - Crea líneas con stock_antes/despues
   - Actualiza stock/inventario productos
   - Genera 2 movimientos Kardex ENTRADA fuente=ajuste_positivo
   - Registra auditoría

### CU-AJ-02: Conteo Físico — Diferencia Negativa
1. Usuario selecciona "Conteo Físico"
2. Escanea/ingresa Producto X, cuenta 42 unidades
3. Sistema muestra: "Stock sistema: 47 → Diferencia: -5"
4. Usuario confirma → Sistema:
   - Crea ajuste AJU-0002 tipo conteo_fisico
   - Línea cantidad = -5, stock_antes=47, stock_despues=42
   - Kardex SALIDA fuente=conteo_fisico
   - Actualiza stock/inventario a 42

### CU-AJ-03: Merma — Producto Vencido
1. Usuario selecciona "Merma"
2. Agrega Producto Y cantidad 10
5. Motivo: "Vencimiento lote L-2026-08"
6. Confirma → Kardex SALIDA fuente=merma, costo= CPP

### CU-KC-01: Visualizar Kardex con Costo
1. Usuario accede a Kardex / Producto X
2. Tabla muestra columnas: Fecha, Tipo, Fuente, Origen, Cantidad, Stock Ant/Des, Costo Unit., Costo Total
6. Entradas por compra muestran costo de compra; salidas muestran CPP

---

## 5. Criterios de Aceptación

| ID | Criterio |
|----|----------|
| CA-01 | Crear ajuste positivo con 3 líneas → stock actualizado, 3 Kardex ENTRADA, auditoría |
| CA-02 | Conteo físico con delta -3 → stock -3, Kardex SALIDA fuente=conteo_fisico |
| CA-03 | Merma con stock insuficiente → error "Stock insuficiente", rollback |
| CA-04 | Kardex muestra costo_unitario y costo_total en todas las fuentes |
| CA-05 | Transacción falla en línea 3 → rollback completo (0 Kardex, 0 stock changes) |
| CA-06 | Costo ENTRADA ajuste_positivo = CPP; costo SALIDA merma = CPP |
| CA-07 | Producto inactivo → rechazo con mensaje claro |
| CA-08 | Transacción concurrente 2 usuarios mismo producto → sin deadlock (lock ordenado) |
| CA-09 | ReporteInventario.valorizado usa CPP si > 0, sino precio_compra |