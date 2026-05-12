# Documento de Diseño Técnico: Modal de Actualización de Stock

## Visión General

La funcionalidad añade un **modal de actualización de stock** a la tabla de listado de productos (`/productos`). El administrador puede ingresar una cantidad adicional directamente desde la fila del producto, sin navegar a la pantalla de edición completa.

La lógica de negocio central es simple pero precisa:

```
Nuevo_Stock = stock_actual + Cantidad_Adicional
producto.stock     = Nuevo_Stock
producto.inventario = Nuevo_Stock
```

Ambos campos se actualizan en la misma transacción de base de datos, marcando el inicio de un nuevo ciclo de inventario.

La implementación sigue los patrones ya establecidos en el proyecto: **Laravel + Blade + Tailwind CSS + JavaScript vanilla** (sin frameworks JS). No se introduce ninguna dependencia nueva.

---

## Arquitectura

El flujo completo es:

```
[Tabla productos.index]
        │
        │  clic en botón "Actualizar stock"
        ▼
[Modal HTML (Blade)] ──── datos del producto via data-attributes ────►
        │
        │  submit del formulario (fetch/XHR)
        ▼
[PATCH /productos/{producto}/stock]
        │
        │  ProductoController@updateStock
        ▼
[DB::transaction → UPDATE productos SET stock=X, inventario=X]
        │
        │  JSON response {success, nuevo_stock}
        ▼
[JS actualiza celda de stock en la tabla + cierra modal + muestra flash]
```

### Decisiones de diseño

| Decisión | Alternativa descartada | Razón |
|---|---|---|
| Ruta PATCH dedicada `/productos/{producto}/stock` | Reutilizar `productos.update` | Evita enviar todos los campos del producto; semántica clara y validación mínima |
| Fetch API (JSON) en lugar de form submit tradicional | Form submit con redirect | Permite actualizar la celda en la tabla sin recargar la página (Req. 4.3) |
| Modal HTML puro en Blade | Componente Alpine.js | El proyecto no usa Alpine.js de forma consistente; JS vanilla es suficiente |
| Un único modal reutilizable para todos los productos | Un modal por fila | Menor footprint en el DOM; datos cargados via `data-*` attributes al abrir |

---

## Componentes e Interfaces

### 1. Ruta nueva

```php
// routes/web.php — dentro del grupo middleware(['auth', 'role:Administrador'])
Route::patch('productos/{producto}/stock', [ProductoController::class, 'updateStock'])
     ->name('productos.updateStock');
```

### 2. Método `ProductoController@updateStock`

**Responsabilidades:**
- Validar `cantidad_adicional`: requerido, entero, mínimo 1, máximo 9999.
- Calcular `nuevo_stock = producto->stock + cantidad_adicional`.
- Actualizar `stock` e `inventario` dentro de `DB::transaction`.
- Devolver JSON `{success: true, nuevo_stock: N}` o JSON de error con código HTTP apropiado.

**Firma:**
```php
public function updateStock(Request $request, Producto $producto): \Illuminate\Http\JsonResponse
```

### 3. Vista `productos/index.blade.php` — cambios

**Botón en cada fila:**
```html
<button
    type="button"
    data-stock-btn
    data-producto-id="{{ $producto->id }}"
    data-producto-nombre="{{ $producto->nombre }}"
    data-producto-stock="{{ $producto->stock }}"
    data-update-url="{{ route('productos.updateStock', $producto) }}"
    aria-label="Actualizar stock de {{ $producto->nombre }}"
    class="...">
    Actualizar stock
</button>
```

**Modal (único, al final del `@section('content')`):**
```html
<div id="stock-modal" role="dialog" aria-modal="true" aria-labelledby="stock-modal-title"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm mx-4 p-6">
        <h2 id="stock-modal-title" class="text-lg font-semibold text-gray-800 mb-1">
            Actualizar stock
        </h2>
        <p id="stock-modal-nombre" class="text-sm text-gray-600 mb-4"></p>

        <div class="mb-4 p-3 bg-gray-50 rounded-lg">
            <span class="text-xs text-gray-500">Stock actual</span>
            <p id="stock-modal-stock-actual" class="text-2xl font-bold text-gray-800"></p>
        </div>

        <form id="stock-modal-form" novalidate>
            @csrf
            <input type="hidden" id="stock-modal-producto-id" name="producto_id">
            <input type="hidden" id="stock-modal-url" name="_url">

            <label for="stock-modal-cantidad" class="block text-sm font-medium text-gray-700 mb-1">
                Cantidad adicional
            </label>
            <input type="number" id="stock-modal-cantidad" name="cantidad_adicional"
                   min="1" max="9999" step="1"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Ej: 50">
            <p id="stock-modal-error" role="alert" class="hidden mt-1 text-xs text-red-600"></p>
        </form>

        <div class="flex justify-end gap-2 mt-6">
            <button type="button" id="stock-modal-cancel"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                Cancelar
            </button>
            <button type="button" id="stock-modal-submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                Confirmar
            </button>
        </div>
    </div>
</div>
```

### 4. Módulo JavaScript `stock-modal.js`

Archivo: `resources/js/stock-modal.js` (importado desde `app.js`).

**Responsabilidades:**
- Escuchar clics en `[data-stock-btn]` → poblar y abrir el modal.
- Manejar cierre por botón Cancelar, clic en backdrop y tecla `Escape`.
- Enviar `fetch` PATCH con `Content-Type: application/json` y el token CSRF.
- Manejar respuesta exitosa: actualizar celda de stock en la tabla, cerrar modal, mostrar flash.
- Manejar errores de validación (422) y errores de servidor (500): mostrar mensaje en el modal.

**API pública del módulo:**
```js
// Inicialización automática al cargar el DOM
document.addEventListener('DOMContentLoaded', () => initStockModal());
```

---

## Modelos de Datos

### Tabla `productos` — campos afectados

| Campo | Tipo | Descripción |
|---|---|---|
| `stock` | `integer` | Unidades disponibles actualmente. Se actualiza con `Nuevo_Stock`. |
| `inventario` | `integer` | Stock inicial del ciclo vigente. Se actualiza con `Nuevo_Stock`. |

No se requieren migraciones nuevas. Los campos ya existen.

### Request payload (cliente → servidor)

```json
{
    "cantidad_adicional": 50
}
```

### Response payload (servidor → cliente)

**Éxito (HTTP 200):**
```json
{
    "success": true,
    "nuevo_stock": 150
}
```

**Error de validación (HTTP 422):**
```json
{
    "message": "El campo cantidad adicional es obligatorio.",
    "errors": {
        "cantidad_adicional": ["El campo cantidad adicional es obligatorio."]
    }
}
```

**Error de servidor (HTTP 500):**
```json
{
    "success": false,
    "message": "Error al actualizar el stock. Intente nuevamente."
}
```

---

## Propiedades de Corrección

*Una propiedad es una característica o comportamiento que debe mantenerse verdadero en todas las ejecuciones válidas del sistema — esencialmente, una declaración formal sobre lo que el sistema debe hacer. Las propiedades sirven como puente entre las especificaciones legibles por humanos y las garantías de corrección verificables por máquina.*

### Propiedad 1: El modal muestra el stock correcto del producto seleccionado

*Para cualquier* producto con un valor de `stock` S, cuando el modal se abre para ese producto, el valor de stock mostrado como referencia visual debe ser exactamente S.

**Valida: Requisitos 1.4**

---

### Propiedad 2: El campo de cantidad se inicializa vacío al abrir el modal

*Para cualquier* producto, cuando el modal se abre, el campo `cantidad_adicional` debe estar vacío o en cero, independientemente de cualquier apertura previa del modal.

**Valida: Requisitos 1.3**

---

### Propiedad 3: Valores no numéricos son rechazados

*Para cualquier* string que no represente un número entero válido enviado como `cantidad_adicional`, el servidor debe rechazar la solicitud con un error de validación (HTTP 422).

**Valida: Requisitos 2.2**

---

### Propiedad 4: Valores fuera del rango [1, 9999] son rechazados

*Para cualquier* valor entero menor o igual a cero, o mayor a 9999, enviado como `cantidad_adicional`, el servidor debe rechazar la solicitud con un error de validación (HTTP 422).

**Valida: Requisitos 2.3, 2.4**

---

### Propiedad 5: El cálculo del nuevo stock es la suma exacta

*Para cualquier* producto con `stock` S y cualquier `cantidad_adicional` C válida (1 ≤ C ≤ 9999), después de una actualización exitosa, el valor de `stock` del producto debe ser exactamente S + C.

**Valida: Requisitos 3.1**

---

### Propiedad 6: Stock e inventario son siempre iguales tras la actualización

*Para cualquier* actualización de stock exitosa, los campos `stock` e `inventario` del producto deben tener el mismo valor (S + C) después de la operación.

**Valida: Requisitos 3.2**

---

### Propiedad 7: Rollback ante error preserva los valores originales

*Para cualquier* producto con `stock` S e `inventario` I, si la transacción de actualización falla, los valores de `stock` e `inventario` deben permanecer en S e I respectivamente.

**Valida: Requisitos 3.4**

---

### Propiedad 8: Cancelar el modal no modifica el producto

*Para cualquier* producto, si el modal se abre y luego se cancela (sin confirmar), los valores de `stock` e `inventario` del producto deben permanecer sin cambios.

**Valida: Requisitos 4.4**

---

### Propiedad 9: Usuarios sin rol Administrador reciben HTTP 403

*Para cualquier* usuario autenticado que no tenga el rol `Administrador`, una solicitud PATCH a `/productos/{producto}/stock` debe devolver HTTP 403.

**Valida: Requisitos 5.2**

---

## Manejo de Errores

| Escenario | Comportamiento del servidor | Comportamiento del cliente |
|---|---|---|
| `cantidad_adicional` vacío | HTTP 422 con mensaje de validación | Muestra error bajo el campo en el modal |
| `cantidad_adicional` no numérico | HTTP 422 con mensaje de validación | Muestra error bajo el campo en el modal |
| `cantidad_adicional` ≤ 0 o > 9999 | HTTP 422 con mensaje de validación | Muestra error bajo el campo en el modal |
| Producto no encontrado | HTTP 404 (Route Model Binding automático) | Muestra mensaje de error genérico en el modal |
| Usuario no autenticado | HTTP 401 / redirect a login | Redirige a login |
| Usuario sin rol Administrador | HTTP 403 | Muestra mensaje de error genérico en el modal |
| Error de base de datos durante transacción | HTTP 500 con JSON de error | Muestra mensaje de error genérico en el modal |
| Error de red (fetch falla) | — | Muestra mensaje "Error de conexión. Intente nuevamente." |

**Principio general:** Los errores de validación (422) muestran el mensaje específico del campo. Los errores de servidor (4xx/5xx) muestran un mensaje genérico para no exponer detalles internos.

---

## Estrategia de Testing

### Enfoque dual

La feature combina lógica de negocio pura (cálculo de stock, validación) con comportamiento de UI (modal, fetch). Se usan dos tipos de tests complementarios:

- **Tests unitarios/de feature (PHPUnit):** verifican la lógica del controlador, validaciones, respuestas HTTP y comportamiento de la transacción.
- **Tests de propiedades (PBT con PHPUnit + generadores manuales):** verifican propiedades universales sobre el cálculo de stock y las validaciones.

### Tests de propiedades (PBT)

Se usa **PHPUnit** con generadores de datos aleatorios implementados en el propio test (no se introduce una librería PBT externa, ya que el proyecto no tiene ninguna instalada). Cada test de propiedad ejecuta **mínimo 100 iteraciones** con valores generados aleatoriamente.

**Tag de referencia:** `// Feature: stock-update-modal, Property N: <texto>`

| Propiedad | Descripción del test | Iteraciones |
|---|---|---|
| P5: Cálculo exacto | Para S ∈ [0, 10000] y C ∈ [1, 9999] aleatorios, verificar que stock resultante = S + C | 100 |
| P6: Stock == Inventario | Para S y C aleatorios válidos, verificar que stock == inventario tras la actualización | 100 |
| P3: Rechazo de no numéricos | Para strings no numéricos aleatorios, verificar HTTP 422 | 100 |
| P4: Rechazo fuera de rango | Para enteros ≤ 0 y > 9999 aleatorios, verificar HTTP 422 | 100 |
| P7: Rollback preserva valores | Simular fallo en transacción, verificar que stock e inventario no cambian | 50 |
| P9: Autorización por rol | Para usuarios sin rol Administrador, verificar HTTP 403 | 50 |

### Tests de ejemplo (PHPUnit Feature Tests)

- Apertura del modal: verificar que la vista contiene el botón y el modal HTML.
- Cierre sin cambios (P8): verificar que no se realizó ninguna petición al servidor.
- Actualización exitosa: verificar respuesta JSON `{success: true, nuevo_stock: N}`.
- Campo vacío (Req. 2.1): verificar HTTP 422 con mensaje de campo obligatorio.
- Valor > 9999 (Req. 2.4): verificar HTTP 422.
- Producto inexistente (Req. 5.3/5.4): verificar HTTP 404.
- Usuario no autenticado (Req. 5.1): verificar redirect a login.

### Cobertura objetivo

- `ProductoController@updateStock`: 100% de ramas (validación, transacción, error).
- Módulo JS `stock-modal.js`: tests manuales de integración en navegador (fuera del scope de PHPUnit).
