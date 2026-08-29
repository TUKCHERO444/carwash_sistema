# Patrón de Validación de Formularios

Documento de referencia que define el patrón estándar de validación usado en los formularios del sistema. Sirve como guía para saber **qué capas de validación existen**, **cómo se implementa cada una** y **cuándo usarlas**, de modo que cualquier formulario nuevo o existente siga el mismo criterio.

> Base de referencia: módulos `ventas`, `ingresos`, `cambio-aceite` y `vehiculos`, que ya implementan el patrón completo.

---

## 1. Filosofía: Validación en Capas (Defensa en Profundidad)

Nunca confiar en una sola capa. Un dato debe pasar por hasta **4 filtros** antes de persistirse:

| Capa | Dónde vive | Rol |
|------|------------|-----|
| **1. Filtrado en vivo** | Frontend — `data-filter` | Evita escribir caracteres inválidos mientras se teclea |
| **2. Validación en submit** | Frontend — `Validation.validate()` | Bloquea el envío si hay campos requeridos vacíos, grupos sin marcar o tablas vacías |
| **3. Validación condicional** | Frontend — JS específico + SweetAlert2 | Reglas de negocio (stock, descuento, montos mixtos, etc.) |
| **4. Validación server-side** | Backend — Controlador `$request->validate()` | Fuente de verdad. Protege aunque el cliente esté manipulado/offline |

La **capas 3 y 4** son las que dependen de la lógica de negocio de **cada formulario** ("los formularios varían"). Las capas 1 y 2 son mecánicas y reutilizables.

---

## 2. Capa 1 — Filtrado en vivo (`input-filters.js`)

### Responsabilidad
Sanitiza el valor al momento de escribir o pegar. Es **preventivo** (no muestra errores, simplemente impide caracteres no permitidos).

### Archivo
`resources/js/utils/input-filters.js` — se inicializa **globalmente** en `resources/js/app.js:6,12`:

```js
import { initInputFilters } from './utils/input-filters.js';
// dentro de DOMContentLoaded:
initInputFilters();
```

Al estar global, cualquier input con `data-filter` queda cubierto automáticamente **sin importar el módulo**.

### Atributos soportados en las vistas

| Atributo | Valores | Efecto |
|----------|---------|--------|
| `data-filter="letters"` | solo letras (incluye acentos, ü, ñ) y espacios simples | borra símbolos y números |
| `data-filter="alphanumeric"` | letras, números y espacios simples (sin símbolos) | borra símbolos |
| `data-filter="digits"` | solo dígitos `0-9` | borra letras y símbolos |
| `data-length="N"` | número | corta el valor a N caracteres (además de `maxlength`) |

> Para los filtros `letters` y `alphanumeric` se colapsan los espacios múltiples y se eliminan los espacios al inicio.

### Ejemplo de uso (vista cliente)

```html
<input type="text" name="dni" data-filter="digits" data-length="8" maxlength="8" ...>
<input type="text" name="nombre" data-filter="letters" maxlength="50" ...>
```

### Regla de decisión
- **DNI / teléfono / RUC** → `data-filter="digits"`
- **Solo texto (nombres, apellidos)** → `data-filter="letters"`
- **Placa / códigos mixtos** → `data-filter="alphanumeric"`
- **Números que admiten decimales (precios, montos)** → **no** usar `data-filter="digits"` (bloquearía el punto decimal). Usar `type="number"` + `step="0.01"` + `min`.

---

## 3. Capa 2 — Validación en submit (`validation.js`)

### Responsabilidad
En el evento `submit` del formulario, valida de forma genérica y bloquea el envío si detecta errores, mostrando mensajes inline y haciendo scroll al primer error.

### Archivo
`resources/js/utils/validation.js` — exporta un objeto `Validation` con el método `validate(form)`.

### Cómo se conecta (patrón en cada módulo)

```js
import { Validation } from '../utils/validation.js';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-venta'); // id del <form>
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!Validation.validate(form)) {
                e.preventDefault(); // bloquea el envío
            }
        });
    }
});
```

> **Importante**: el archivo JS del módulo debe estar registrado como entrypoint en `vite.config.js` y referenciado con `@vite()` en la vista.

### Qué valida `Validation.validate(form)`

Lo hace en orden, limpia errores previos y marca `isValid = false` ante el primer fallo por categoría:

#### a) Inputs requeridos — atributo `required`
Todo input/textarea/select con el atributo `required` no puede quedar vacío.

```html
<input type="number" name="precio" required ...>
<select name="cliente_id" required>...</select>
```

#### b) Grupos de checkboxes — `data-validate-group`
Útil cuando **al menos N** opciones de un grupo deben estar marcadas (p. ej. servicios, trabajadores).

```html
<input type="checkbox" value="1" data-validate-group="servicios" data-validate-min="1">
```

- `data-validate-min="N"` = mínimo de opciones marcadas (por defecto 0).
- El mensaje se muestra junto al grupo (`error-msg-{groupName}` o dentro del contenedor).

#### c) Tablas dinámicas — `data-validate-table`
Útil cuando una tabla debe tener al menos N filas (p. ej. productos de una venta, servicios de un ingreso).

```html
<table id="tabla-detalle"
       data-validate-table
       data-validate-min-rows="1"
       data-validate-error-id="error-detalle">
    <tbody>...</tbody>
</table>
<p id="error-detalle" class="hidden mt-2 text-xs text-red-600"></p>
```

- `data-validate-min-rows="N"` = mínimo de filas (por defecto 1).
- Se cuentan las filas que **no** tengan la clase `no-items-row` (el mensaje "No hay items agregados").
- El mensaje se pinta en el elemento referenciado por `data-validate-error-id`.

### Al detectar errores
- Marca cada campo con la clase `.input-error`.
- Crea/muestra un `<p class="error-message">` con el mensaje.
- Al escribir de nuevo en el campo, limpia el error (`{ once: true }`).
- Hace `scrollIntoView` y `focus()` al primer elemento con error.

---

## 4. Capa 3 — Validación condicional (JS del módulo + SweetAlert2)

### Responsabilidad
Reglas de negocio propias de cada formulario que requieren feedback claro al usuario (normalmente con **SweetAlert2**).

### Ejemplos ya implementados

#### Stock insuficiente (ventas / cambio-aceite)

Al agregar un producto que ya existe y supera el stock, o al escribir una cantidad mayor al stock:

```js
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

#### Porcentaje de descuento ≤ 100

```js
if (parseFloat(this.value) > 100) {
    this.value = 100;
    errorEl?.classList.remove('hidden');
}
```

#### Total editable (descuento manual) — ni 0/negativo ni mayor al subtotal

```js
const limite = btnFact ? subtotal * 1.18 : subtotal;
if (entered <= 0) {
    inputTotal.value = subtotal.toFixed(2);
    Swal.fire({ icon: 'warning', title: 'Valor no válido', text: 'El precio no puede ser 0 ni negativo.' });
} else if (entered > limite) {
    inputTotal.value = limite.toFixed(2);
    Swal.fire({ icon: 'warning', title: 'Precio excede el subtotal', text: `El precio no puede superar S/ ${limite.toFixed(2)}.` });
}
```

#### Pago mixto — la suma de montos debe igualar el total

Se muestra/oculta una alerta en la vista según el cálculo, y se re-valida al escribir en cada monto.

> **Regla**: toda condición que impida un valor debe (idealmente) corregir el valor y explicar al usuario. Si además es crítica, debe replicarse en el backend.

---

## 5. Capa 4 — Validación server-side (Controlador)

### Responsabilidad
Fuente de verdad. Aunque el frontend falle o sea manipulado, el backend debe validar igual.

### Patrón
En el método `store()` / `update()` del controlador, antes de persistir:

```php
$request->validate([
    'nombre'  => ['required', 'string', 'max:150', 'unique:categorias,nombre'],
    'precio'  => ['required', 'numeric', 'gt:0'],
]);
```

Con mensajes personalizados cuando haga falta:

```php
], [
    'productos.required' => 'Debe agregar al menos un producto a la venta.',
    'productos.min'      => 'Debe agregar al menos un producto a la venta.',
]);
```

### Reglas típicas por tipo de campo

| Campo | Reglas comunes |
|-------|----------------|
| DNI (Perú, 8 dígitos) | `['required', 'string', 'digits:8', 'regex:/^[0-9]{8}$/', $uniqueDni]` |
| Teléfono (9 dígitos) | `['nullable', 'string', 'digits:9', 'regex:/^[0-9]{9}$/']` |
| Nombres (solo letras) | `['required', 'string', 'max:50', SOLO_LETRAS]` |
| Nombre único | `['required', 'string', 'max:150', 'unique:tabla,nombre']` (create) |
| Nombre único al editar | `['required', 'string', 'max:150', 'unique:tabla,nombre,' . $record->id]` (update) |
| Precios / montos | `['required', 'numeric', 'gt:0']` |
| Reglas dinámicas (tablas) | `['required', 'array', 'min:1']` + reglas por ítem (`productos.*.cantidad`, etc.) |

### Validación de negocio adicional (p. ej. stock)

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

---

## 6. Cómo se reflejan los errores server-side en la vista

Las vistas de Blade muestran los errores de Laravel y marcan el campo como inválido:

```blade
<input type="text" name="dni" value="{{ old('dni') }}"
       class="... {{ $errors->has('dni') ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : '' }}">
@error('dni')
    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
@enderror
```

Además del bloqueo genérico de errores de tabla:

```blade
@error('productos')
    <div id="productos-error" role="alert" class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-400 text-sm text-red-600">
        {{ $message }}
    </div>
@enderror
```

> Los formularios usan `novalidate` en la etiqueta `<form>` porque la validación visual se delega a nuestro motor JS (`Validation.validate`), no a la nativa del navegador.

---

## 7. Estado por módulo / formulario

Leyenda: ✅ aplicado · ⚠️ parcial · ❌ pendiente

| Módulo (form) | `data-filter` | `Validation.validate` | Validación de negocio | Server-side |
|---------------|:---:|:---:|:---:|:---:|
| Ventas (create) | — | ✅ | ✅ | ✅ |
| Ingresos (create/edit/confirmar) | — | ✅ | ✅ | ✅ |
| Cambio de aceite (create/edit/confirmar) | — | ✅ | ✅ | ✅ |
| Vehículos (create/edit) | ✅ | ✅ | ✅ | ✅ |
| Clientes (create/edit) | ✅ | ❌ | — | ✅ |
| Trabajadores (create/edit) | ✅ | ❌ | — | ✅ |
| Productos (create/edit) | ✅ | ❌ | — | ✅ |
| Servicios (create/edit) | ✅ | ❌ | — | ✅ |
| Categorías (create/edit) | ❌ | ❌ | — | ✅ |
| Marcas (create/edit) | ❌ | ❌ | — | ✅ |
| Roles (create/edit) | ❌ | ❌ | — | ✅ |
| Users (create/edit) | ❌ | ❌ | — | ✅ |

> Este listado es el punto de partida para continuar completando las validaciones de forma ordenada.

---

## 8. Checklist para implementar validación en un formulario nuevo

1. [ ] **Frontend — filtrado en vivo**: añadir `data-filter` y `data-length` donde aplique (no en números decimales).
2. [ ] **Frontend — validación en submit**: asegurar que el JS del módulo importe `Validation` y enganche el `submit` con `Validation.validate(form)`.
3. [ ] **Frontend — atributos**: `required` en campos obligatorios, `data-validate-group`/`data-validate-min` para grupos, `data-validate-table`/`data-validate-min-rows`/`data-validate-error-id` para tablas dinámicas.
4. [ ] **Frontend — negocio**: si hay reglas (stock, descuento, montos, fechas, comparaciones), implementarlas con SweetAlert2 y corregir el valor cuando sea posible.
5. [ ] **Backend**: replicar todas las reglas en `$request->validate()` del controlador (create y update), con mensajes personalizados.
6. [ ] **Vista**: mostrar errores con `@error` + `$errors->has(...)` y mantener `novalidate`.
7. [ ] **Registrar entrypoint** del JS del módulo en `vite.config.js` y referenciarlo con `@vite()`.
8. [ ] **Probar** el flujo feliz y los flujos de error; correr la suite de tests y el build de Vite.

---

## 9. Archivos clave del patrón

| Archivo | Rol |
|---------|-----|
| `resources/js/utils/validation.js` | Motor genérico de validación en submit |
| `resources/js/utils/input-filters.js` | Filtrado en vivo por `data-filter` |
| `resources/js/app.js` | Inicialización global de `initInputFilters()` |
| `resources/js/ventas/create.js` | Ejemplo de referencia (patrón completo) |
| `app/Http/Controllers/*Controller.php` | Validación server-side por módulo |
| `resources/views/**/create|edit.blade.php` | Vistas con `data-*`, `required`, `@error` y `novalidate` |
