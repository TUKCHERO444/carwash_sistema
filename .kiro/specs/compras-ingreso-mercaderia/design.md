# Diseño — Compras e Ingreso de Mercadería

**Feature:** `compras-ingreso-mercaderia`
**Fases cubiertas:** 0 a 3

## 1. Arquitectura general

El módulo no introduce un segundo sistema de inventario. Se apoya en las tres
piezas que ya existen y las extiende:

```
CajaService (caja activa + egresos)
        ▲
CompraController::recibir  ──►  KardexService  ──►  movimientos_kardex
        │                                              ▲
        ├──►  productos.stock / productos.inventario ──┘
        └──►  productos.precio_compra
```

La única pieza estructural nueva es el par `compras` / `detalle_compras`, que captura
la operación comercial antes de que se convierta en movimiento de inventario.

## 2. Modelo de datos

### 2.1 `compras`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigIncrements | PK |
| `correlativo` | string(20) | unique, `CMP-0001` |
| `proveedor_id` | foreignId | → `proveedores` id, restrict |
| `fecha` | date | |
| `tipo_documento` | string(20) nullable | factura, boleta, guia, nota_credito, sin_documento |
| `numero_documento` | string(50) nullable | |
| `estado` | enum | `borrador`, `recibida`, `anulada`; default `borrador` |
| `subtotal` | decimal(10,2) | default 0 |
| `total` | decimal(10,2) | default 0 |
| `observaciones` | text nullable | |
| `user_id` | foreignId | → `users` id, restrict |
| `caja_id` | foreignId nullable | → `cajas` id, restrict |
| `egreso_caja_id` | unsignedBigInteger nullable | → `egresos_caja` id, restrict |
| `fecha_recepcion` | timestamp nullable | |
| timestamps | | |

Índices: `estado`, `fecha`, `proveedor_id`, unique `correlativo`.

### 2.2 `detalle_compras`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigIncrements | PK |
| `compra_id` | foreignId | → `compras` id, cascade |
| `producto_id` | foreignId | → `productos` id, restrict |
| `cantidad` | integer | ≥ 1 |
| `costo_unitario` | decimal(10,2) | > 0 |
| `subtotal` | decimal(10,2) | cantidad × costo_unitario |

Unique `(compra_id, producto_id)`.

### 2.3 Cambios en tablas existentes

| Tabla | Cambio | Fase |
|---|---|---|
| `productos` | Sin cambio de esquema. `precio_compra` admite `0`; `stock` e `inventario` salen de los formularios. | 0 |
| `egresos_caja` | `tipo_pago` amplía a `efectivo, yape, transferencia, tarjeta`. | 0 |
| `movimientos_kardex` | `fuente`: enum(3) → string(30); se añade `compra`. | 3 |

## 3. Fase 0 — Preparación

### 3.1 Validación condicional del precio

El problema: `precio_venta` exige `gte:precio_compra`, pero si `precio_compra` es
opcional la regla no puede ser estática.

Solución: un método privado en `ProductoController` que arme las reglas de precio
según el valor recibido.

```php
private function reglasPrecio(array $data): array
{
    $precioCompra = (float) ($data['precio_compra'] ?? 0);

    $precioVenta = ['required', 'numeric', 'gt:0'];

    if ($precioCompra > 0) {
        $precioVenta[] = 'gte:precio_compra';
    }

    return [
        'precio_compra' => ['nullable', 'numeric', 'min:0'],
        'precio_venta' => $precioVenta,
    ];
}
```

Se usa el mismo método en `store` y `update`, para no duplicar la lógica y no dejar
que las reglas divergan (requisito 11).

El mensaje `precio_venta.gte` se conserva en el array de mensajes personalizados de
ambos métodos.

### 3.2 Cierre del paso directo

En `update` se elimina `stock` e `inventario` de las reglas y del arreglo `$data`. Con
ello, un POST con esos campos los ignora silenciosamente: no se validan, no se
escriben, y la vista deja de ofrecerlos (requisito 5).

`updateStock` no se toca: es el camino del modal, que sigue vigente hasta la Fase 4.

### 3.3 Cálculo del total en el servidor

El formulario envía `subtotal` y `total` por comodidad de la interfaz, pero el
controlador los ignora y los recalcula:

```php
$lineas = collect($validated['detalle'])->map(function (array $linea) {
    $cantidad = (int) $linea['cantidad'];
    $costo = (float) $linea['costo_unitario'];

    return [
        'producto_id' => $linea['producto_id'],
        'cantidad' => $cantidad,
        'costo_unitario' => $costo,
        'subtotal' => round($cantidad * $costo, 2),
    ];
});

$subtotal = round($lineas->sum('subtotal'), 2);
```

Se usa `round(..., 2)` en cada línea y en el total, para que la suma de los subtotales
sea exactamente el total almacenado (requisitos 21 y 22), sin arrastrar errores de
coma flotante.

## 4. Fase 1 — Producto sin existencias

`store` deja de aceptar `inventario` y persiste con `stock = 0` e `inventario = 0`.
Se elimina el bloque que generaba el correlativo `INV-XXXX` y la entrada de Kardex.

Consecuencia directa en la base de datos: el producto recién creado tiene
`inventario = 0`, lo que hace que los productos con alerta por 75 % consumido se
reseteen al entrar al ciclo, igual que ya ocurre con cada reposición.

## 5. Fase 2 — Módulo de compras

### 5.1 Modelos

`Compra` con casts `decimal:2` en `subtotal` y `total`, `date` en `fecha`,
`datetime` en `fecha_recepcion`, y relaciones `proveedor()`, `detalles()`,
`user()`, `caja()`, `egresoCaja()`.

La relación hacia el usuario se llama `user()` y no `usuario()`, para seguir la
convención de `Venta`, `Caja`, `Lavado`, `CambioAceite` y `EgresoCaja`, que son los
modelos con columna `user_id`. Solo `MovimientoKardex` y `RegistroAuditoria` usan
`usuario()`.

`DetalleCompra` con `compra()` y `producto()`.

`Proveedor::compras()` como `hasMany`.

**Trampa del pluralizador:** el resource necesita `->parameters(['compras' => 'compra'])`
porque `Str::singular('compras')` devuelve `compra` pero Laravel infiere mal el
parámetro en algunas rutas. Es el mismo caso que obliga a `->parameters(['proveedores' => 'proveedor'])` en el módulo de proveedores.

### 5.2 Transiciones

```php
private const TRANSICIONES = [
    'borrador' => ['recibida', 'anulada'],
    'recibida' => [],
    'anulada'  => [],
];
```

Todo método destructivo consulta la tabla antes de actuar y devuelve un error
explícito si el estado no lo permite. El mensaje para `recibida` explica que la
reversión no está disponible en esta fase, en lugar de un 403 genérico (requisito 28).

### 5.3 Auditoría

`Compra::class` entra en `$modelos` de `AuditServiceProvider`. `DetalleCompra::class`
entra en `AuditService::EXCLUIDOS`, junto a `MovimientoKardex`: un pivote no genera
auditoría, porque duplica el ruido de su cabecera sin aportar información.

## 6. Fase 3 — Recepción

### 6.1 Migración de `fuente`: spike bloqueante

Cambiar el tipo de una columna en SQLite implica recrear la tabla, y
`movimientos_kardex` tiene claves foráneas hacia `productos` y `users`. Antes de
escribir la migración se ejecuta un spike sobre SQLite in-memory (la configuración de
`phpunit.xml`) que verifica que el `->change()` funciona.

Si el spike falla, las alternativas son mantener el ENUM y añadir el valor con una
sentencia condicionada por driver, o conservar el ENUM y resolver el alcance en el
modelo. No se avanza sin resultado.

### 6.2 Orden de la transacción

El orden importa, porque caja e inventario deben quedar consistentes entre sí:

1. `Compra::lockForUpdate()` sobre la compra.
2. Verificar caja abierta. Fallar aquí no deja rastro.
3. Verificar que los productos existen y están activos.
4. Generar el correlativo si falta.
5. Por cada línea: `Producto::lockForUpdate()`, sumar al `stock`, copiar el resultado
   a `inventario`, registrar entrada de Kardex, actualizar `precio_compra`.
6. Registrar el egreso en caja.
7. Marcar `recibida`, guardar `caja_id` y `egreso_caja_id`.

Los bloqueos se toman en orden de `producto_id` ascendente, no en el orden de las
líneas del formulario, para evitar interbloqueos (deadlock) cuando dos operadores
reciben compras que comparten productos.

### 6.3 Preservación de invariantes

Se preservan las dos invariantes que el sistema ya tiene:

- **Atomicidad stock/Kardex:** ambos se escriben en la misma transacción.
- **Correlativo dentro de la transacción:** se calcula después de bloquear, no antes.

Y se agrega una tercera, que el sistema no tiene todavía y que la Fase 8 extenderá
a venta y cambio de aceite: **bloqueo de fila sobre el producto durante la
declaración de la operación**.

### 6.4 Advertencia de margen

Tras actualizar los `precio_compra`, se comparan con los `precio_venta`. Los
productos cuyo costo nuevo supere el precio de venta se devuelven en la respuesta
para que el operador los vea. No bloquean la operación: podría ser una corrección
del precio de venta pendiente, y bloquear exigiría una validación de umbral que el
sistema no tiene.

## 7. Cadena de seeders

Con productos naciendo en cero, el orden actual produce salidas sobre stock
inexistente. Nuevo orden:

```
ProductoSeeder        → stock 0
CompraSeeder          → compras recibidas (cantidades pequeñas)
KardexSeeder          → entradas de esas compras
VentaSeeder           → ventas
CambioAceiteSeeder    → cambios confirmados
KardexSeeder          → salidas de ventas y cambios
```

`KardexSeeder` deja de fabricar entradas `INV-` a partir de `producto.inventario`.

## 8. Riesgos técnicos

| Riesgo | Manejo |
|---|---|
| `->change()` de ENUM a string falla en SQLite | Spike bloqueante antes de la migración |
| Interbloqueo en la recepción | Bloqueo de productos en orden ascendente de `producto_id` |
| Regresión en reportes de valorizado | `precio_compra = 0` en lugar de `null`: `ReporteInventarioService` multiplica stock × precio_compra |
| Doble vía de abastecimiento durante la transición | Etiqueta visible en el modal (requisito 61) |
| Reordenar seeders rompe los datos demo | Tarea obligatoria de la Fase 3, con verificación del reporte de inventario |
