# Fix: Validación de Stock — Módulos de Ventas y Cambio de Aceite

## Problema Identificado

Tanto el módulo de **ventas** como el de **cambio de aceite** permitían registrar operaciones incluso cuando la cantidad de productos solicitada superaba el stock real disponible en inventario. Esto generaba **registros erróneos** con cantidades negativas en la columna `stock` de la tabla `productos`.

### Impacto

- **Inventario negativo**: El stock de productos quedaba en valores negativos
- **Inconsistencia financiera**: Los totales no reflejaban la realidad del inventario
- **Sin validación en ningún punto**: Ni el frontend ni el backend verificaban la disponibilidad

### Causa Raíz

Ambos controladores decrementaban el stock directamente sin verificar previamente si la cantidad solicitada estaba dentro del rango disponible:

```php
// Código original — sin validación de stock (común a ambos módulos)
Producto::where('id', $item['producto_id'])
        ->decrement('stock', $item['cantidad']);
```

---

## Módulos Afectados

| Módulo | Controlador | Métodos afectados |
|--------|------------|-------------------|
| **Ventas** | `VentaController` | `store()` |
| **Cambio de Aceite** | `CambioAceiteController` | `store()`, `update()`, `procesarConfirmacion()`, `actualizarTicket()` |

---

## Solución Implementada

### 1. Instalación de SweetAlert2

```bash
npm install sweetalert2
```

---

### 2. Backend — Validación de Stock

#### 2.1 Ventas — `VentaController::store()`

**Archivo:** `app/Http/Controllers/VentaController.php:84-91`

```php
foreach ($request->productos as $item) {
    $producto = Producto::findOrFail($item['producto_id']);
    if ($item['cantidad'] > $producto->stock) {
        return back()->withErrors([
            "productos.{$item['producto_id']}.cantidad" => "La cantidad de \"{$producto->nombre}\" ({$item['cantidad']}) excede el stock disponible ({$producto->stock}).",
        ])->withInput();
    }
}
```

#### 2.2 Cambio de Aceite — `CambioAceiteController`

**Archivo:** `app/Http/Controllers/CambioAceiteController.php`

**En `store()`:** Validación simple contra stock actual:

```php
foreach ($request->productos as $item) {
    $producto = Producto::findOrFail($item['producto_id']);
    if ($item['cantidad'] > $producto->stock) {
        return back()->withErrors([...])->withInput();
    }
}
```

**En `update()`, `procesarConfirmacion()`, `actualizarTicket()`:** Estos métodos primero restauran el stock anterior antes de decrementar el nuevo. La validación calcula el stock "disponible real" sumando lo que se va a restaurar:

```php
$cambioAceite->load('productos');
$oldStockMap = $cambioAceite->productos->pluck('pivot.cantidad', 'id')->toArray();

foreach ($request->productos as $item) {
    $producto = Producto::findOrFail($item['producto_id']);
    $availableStock = $producto->stock + ($oldStockMap[$item['producto_id']] ?? 0);
    if ($item['cantidad'] > $availableStock) {
        return back()->withErrors([...])->withInput();
    }
}
```

**Lógica:** Si un producto tenía cantidad 3 en el ticket anterior y el stock actual es 5, el stock disponible real es 5 + 3 = 8 (porque se va a restaurar esa cantidad antes de decrementar la nueva).

---

### 3. Frontend — Validación con SweetAlert2

#### 3.1 Cambio de Aceite — `create.js`

**Importación:**
```javascript
import Swal from 'sweetalert2';
```

**Al agregar producto** — almacena `stock` en el item:
```javascript
items.push({
    producto_id: producto.id,
    nombre:      producto.nombre,
    cantidad:    1,
    precio:      +parseFloat(producto.precio_venta).toFixed(2),
    total:       +parseFloat(producto.precio_venta).toFixed(2),
    stock:       stock,  // ← Campo agregado
});
```

**Al agregar producto existente** — valida contra stock:
```javascript
if (nuevaCantidad > existente.stock) {
    Swal.fire({
        icon: 'warning',
        title: 'Stock insuficiente',
        html: `No hay suficiente stock de <strong>${existente.nombre}</strong>.<br>Stock disponible: <strong>${existente.stock}</strong>`,
        confirmButtonColor: '#3085d6',
        confirmButtonText: 'Entendido',
    });
    return;
}
```

**Al cambiar cantidad** — SweetAlert2 + auto-corrección:
```javascript
if (stock != null && cantidadIngresada > stock) {
    Swal.fire({
        icon: 'warning',
        title: 'Stock insuficiente',
        html: `La cantidad ingresada (<strong>${cantidadIngresada}</strong>) excede el stock disponible...`,
        confirmButtonColor: '#3085d6',
        confirmButtonText: 'Entendido',
    }).then(() => {
        input.value = stock;
        input.focus();
    });
    items[idx].cantidad = stock;
}
```

#### 3.2 `shared.js` — Atributo HTML `max`

```html
<input type="number" min="1" max="${item.stock ?? ''}" value="${item.cantidad}" ...>
```

---

## Flujo Completo (Después del Fix)

```
┌─────────────────────────────────────────────────────────┐
│  USUARIO AGREGA PRODUCTO                                │
│  ┌─────────────────────────────────────────────────┐    │
│  │ ¿Ya está en la lista?                          │    │
│  │   SÍ → ¿cantidad+1 > stock?                   │    │
│  │          SÍ → SweetAlert "Stock insuficiente"  │    │
│  │                 NO  → incrementar cantidad     │    │
│  │   NO  → agregar con stock = producto.stock     │    │
│  └─────────────────────────────────────────────────┘    │
├─────────────────────────────────────────────────────────┤
│  USUARIO CAMBIA CANTIDAD EN EL INPUT                    │
│  ┌─────────────────────────────────────────────────┐    │
│  │ ¿cantidad > stock?                             │    │
│  │   SÍ → SweetAlert + auto-ajuste al stock       │    │
│  │   NO → aceptar valor                           │    │
│  └─────────────────────────────────────────────────┘    │
├─────────────────────────────────────────────────────────┤
│  USUARIO ENVÍA EL FORMULARIO                            │
│  ┌─────────────────────────────────────────────────┐    │
│  │ Backend: ¿alguna cantidad > stock disponible?  │    │
│  │   SÍ → retornar error + redirección            │    │
│  │   NO → procesar operación + decrementar stock  │    │
│  └─────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────┘
```

---

## Capas de Protección

| Capa | Ubicación | Mecanismo |
|------|-----------|-----------|
| **HTML** | `renderTablaHTML()` | Atributo `max` en `<input type="number">` |
| **JavaScript** | `agregarProducto()` | Bloqueo al intentar agregar producto existente si excede stock |
| **JavaScript** | `actualizarCantidad()` | SweetAlert2 + auto-corrección al stock máximo |
| **Backend** | `VentaController::store()` | Validación server-side contra stock actual |
| **Backend** | `CambioAceiteController::store()` | Validación server-side contra stock actual |
| **Backend** | `CambioAceiteController::update()` | Validación contra stock + cantidad a restaurar |
| **Backend** | `CambioAceiteController::procesarConfirmacion()` | Validación contra stock + cantidad a restaurar |
| **Backend** | `CambioAceiteController::actualizarTicket()` | Validación contra stock + cantidad a restaurar |

---

## Archivos Modificados

```
app/Http/Controllers/VentaController.php           — Validación de stock en store()
app/Http/Controllers/CambioAceiteController.php     — Validación de stock en 4 métodos
resources/js/ventas/create.js                       — SweetAlert2 + validación frontend
resources/js/cambio-aceite/create.js                — SweetAlert2 + validación frontend
resources/js/cambio-aceite/shared.js                — Atributo max en input de cantidad
package.json                                        — Dependencia sweetalert2
```

---

## Verificación

- **101 tests pasaron** sin regresiones
- **Build de Vite** compiló correctamente con la nueva dependencia
