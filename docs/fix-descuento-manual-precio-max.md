# Fix: Validación de Descuento Manual — El Total no puede superar el Subtotal

## Problema Identificado

Al activar el botón **"Aplicar descuento manual"** en los módulos de ventas, cambio de aceite (confirmación) e ingresos (confirmación), el campo de **Total** se vuelve editable para que el usuario pueda escribir un precio final libre (un "descuento" manual).

El problema era que **no existía ninguna restricción** sobre el valor ingresado. El usuario podía escribir un monto **mayor** al subtotal original, lo cual generaba cobros incorrectos.

### Error Reproducido

```
Subtotal (precio original): S/ 100.00
Usuario activa "Descuento manual"
Escribe en Total: S/ 150.00
→ Se cobra S/ 150.00 por un servicio/producto de S/ 100.00
```

### Módulos Afectados

| Módulo | Vista | Archivo JS | Elemento ancla |
|--------|-------|-----------|----------------|
| **Ventas** | `ventas.create` | `ventas/create.js` | `#subtotal` |
| **Cambio de Aceite** | `cambio-aceite.confirmar` | `cambio-aceite/confirmar.js` (via `shared.js`) | `#precio` |
| **Cambio de Aceite** | `cambio-aceite.edit` | `cambio-aceite/edit.js` (via `shared.js`) | `#precio` |
| **Ingresos** | `ingresos.confirmar` | `ingresos/confirmar.js` (via `shared.js`) | `#precio` |
| **Ingresos** | `ingresos.edit` | `ingresos/edit.js` (via `shared.js`) | `#precio` |

### Causa Raíz

La función `initMetodoPago()` (en `shared.js` de cambio-aceite e ingresos) y el handler en `ventas/create.js` solo hacían `inputTotal.readOnly = false` al activar el toggle, sin validar que el valor editado no excediera el precio original.

```javascript
// Código original — sin límite
toggleDescManual.addEventListener('change', function () {
    if (this.checked) {
        inputTotal.readOnly = false;
        inputTotal.focus();
    } else {
        inputTotal.readOnly = true;
        if (inputAncla) inputTotal.value = inputAncla.value;
    }
});
```

---

## Solución Implementada

### Lógica

El total editable **nunca debe superar el subtotal (precio original)**. Si hay facturación (IGV 18%) activa, el límite se ajusta a `subtotal × 1.18`.

### Validación en 2 puntos

#### 1. Al activar el toggle de descuento manual

Si el valor actual del total ya supera el subtotal (por ejemplo, porque el usuario editó manualmente antes), se corrige automáticamente:

```javascript
if (this.checked) {
    inputTotal.readOnly = false;
    const subtotal = parseFloat(inputAncla.value || 0);
    const btnFact = document.querySelector('[data-facturacion="on"]');
    const limite = btnFact ? subtotal * 1.18 : subtotal;
    if (parseFloat(inputTotal.value || 0) > limite) {
        inputTotal.value = limite.toFixed(2);
    }
    inputTotal.focus();
}
```

#### 2. En cada pulsación de tecla en el campo Total (`input` event)

Se aplica el límite en tiempo real para evitar que el usuario escriba un valor superior:

```javascript
inputTotal.addEventListener('input', function () {
    if (toggleDescManual.checked) {
        const subtotal = parseFloat(inputAncla.value || 0);
        const btnFact = document.querySelector('[data-facturacion="on"]');
        const limite = btnFact ? subtotal * 1.18 : subtotal;
        if (parseFloat(inputTotal.value || 0) > limite) {
            inputTotal.value = limite.toFixed(2);
        }
    }
    // ... resto de validaciones (mixto, etc.)
});
```

### Consideración de Facturación (IGV 18%)

Cuando el botón de facturación está activo (`data-facturacion="on"`), el total mostrado ya incluye el 18% de IGV. Por lo tanto, el límite se calcula como:

```
límite = subtotal × 1.18
```

Esto permite que el usuario aplique un descuento real sobre el precio con IGV incluido, sin cobrar de más.

---

## Flujo Corregido

```
┌──────────────────────────────────────────────────────────┐
│  USUARIO ACTIVA "DESCUENTO MANUAL"                       │
│  ┌──────────────────────────────────────────────────┐    │
│  │ El total se vuelve editable                      │    │
│  │ Si actual > subtotal → se corrige a subtotal     │    │
│  │ Si hay IGV activo    → límite = subtotal × 1.18  │    │
│  └──────────────────────────────────────────────────┘    │
├──────────────────────────────────────────────────────────┤
│  USUARIO ESCRIBE EN EL CAMPO TOTAL                       │
│  ┌──────────────────────────────────────────────────┐    │
│  │ En cada tecla:                                   │    │
│  │   Si total > límite → se ajusta al límite       │    │
│  │   Si total ≤ límite → se acepta el valor        │    │
│  └──────────────────────────────────────────────────┘    │
├──────────────────────────────────────────────────────────┤
│  USUARIO DESACTIVA "DESCUENTO MANUAL"                    │
│  ┌──────────────────────────────────────────────────┐    │
│  │ El total vuelve al valor original (subtotal)     │    │
│  │ El campo se bloquea (readOnly = true)            │    │
│  └──────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────┘
```

---

## Archivos Modificados

| Archivo | Cambio |
|---------|--------|
| `resources/js/ventas/create.js` | Validación en `toggleDescManual.change` e `inputTotal.input` |
| `resources/js/cambio-aceite/shared.js` | Validación en `initMetodoPago()` — toggle + input |
| `resources/js/ingresos/shared.js` | Validación en `initMetodoPago()` — toggle + input |

> Nota: `cambio-aceite/edit.js`, `cambio-aceite/confirmar.js`, `ingresos/edit.js` e `ingresos/confirmar.js` usan `initMetodoPago()` desde `shared.js`, por lo que quedan cubiertos automáticamente.

---

## Verificación

- **101 tests pasaron** sin regresiones
- **Build de Vite** compiló correctamente
