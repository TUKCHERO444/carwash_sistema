# Fix: Validación de Descuento Manual — Valores 0/negativos y exceso de subtotal

## Problema Identificado

El fix anterior (`fix-descuento-manual-precio-max.md`) limitaba el total al subtotal, pero faltaban dos validaciones:

1. **Valores 0 o negativos**: El usuario podía escribir `0`, `-50` o cualquier valor no positivo en el campo de total con descuento manual activo.
2. **Sin SweetAlert al exceder**: Si el usuario ese reemplazabascribía un valor mayor al subtotal, se silenciosamente  el valor, sin informar al usuario de por qué cambió.

### Error Reproducido

```
Caso 1 — Valor negativo:
Subtotal: S/ 100.00
Usuario activa "Descuento manual"
Escribe: -50
→ Se procesaba un cobro de -S/ 50.00

Caso 2 — Valor cero:
Subtotal: S/ 100.00
Escribe: 0
→ Se procesaba un cobro de S/ 0.00

Caso 3 — Sin feedback al exceder:
Subtotal: S/ 100.00
Escribe: 150
→ Se cambiaba silenciosamente a 100 sin explicar al usuario
```

### Causa Raíz

El handler del evento `input` en el campo total solo verificaba `> limite` y clampsaba el valor, sin verificar `<= 0` y sin mostrar ninguna alerta al usuario:

```javascript
// Código anterior — solo clamp silencioso
if (parseFloat(inputTotal.value || 0) > limite) {
    inputTotal.value = limite.toFixed(2);
}
```

---

## Solución Implementada

### Validación en el campo Total (`input` event)

Se reemplazó el clamp silencioso por una lógica que:

1. Verifica si el valor es **0 o negativo** → restaura subtotal + SweetAlert de advertencia
2. Verifica si el valor **excede el subtotal** (ajustado por IGV si aplica) → clampsar + SweetAlert de advertencia

```javascript
if (toggleDescManual.checked) {
    const subtotal = parseFloat(inputAncla.value || 0);
    const btnFact = document.querySelector('[data-facturacion="on"]');
    const limite = btnFact ? subtotal * 1.18 : subtotal;
    const entered = parseFloat(inputTotal.value || 0);

    if (entered <= 0) {
        inputTotal.value = subtotal.toFixed(2);
        Swal.fire({
            icon: 'warning',
            title: 'Valor no válido',
            text: 'El precio no puede ser 0 ni negativo.',
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'Entendido',
        });
    } else if (entered > limite) {
        inputTotal.value = limite.toFixed(2);
        Swal.fire({
            icon: 'warning',
            title: 'Precio excede el subtotal',
            text: `El precio no puede superar S/ ${limite.toFixed(2)}.`,
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'Entendido',
        });
    }
}
```

### Import de SweetAlert2 en shared.js

Se agregó `import Swal from 'sweetalert2'` a los archivos compartidos:

- `resources/js/cambio-aceite/shared.js`
- `resources/js/ingresos/shared.js`

> `ventas/create.js` ya importaba SweetAlert2.

---

## Flujo Corregido

```
┌──────────────────────────────────────────────────────────────┐
│  USUARIO EDITA EL CAMPO TOTAL (descuento manual activo)      │
│  ┌──────────────────────────────────────────────────────┐    │
│  │ valor ≤ 0  → restaurar subtotal + SweetAlert         │    │
│  │              "El precio no puede ser 0 ni negativo"  │    │
│  ├──────────────────────────────────────────────────────┤    │
│  │ valor > límite → clampsar al límite + SweetAlert     │    │
│  │                  "El precio no puede superar S/ X"   │    │
│  ├──────────────────────────────────────────────────────┤    │
│  │ 0 < valor ≤ límite → aceptar sin intervención       │    │
│  └──────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────┘
```

---

## Mensajes SweetAlert

| Condición | Título | Texto |
|-----------|--------|-------|
| `valor ≤ 0` | Valor no válido | El precio no puede ser 0 ni negativo. |
| `valor > subtotal` | Precio excede el subtotal | El precio no puede superar S/ {limite}. |

---

## Archivos Modificados

| Archivo | Cambio |
|---------|--------|
| `resources/js/ventas/create.js` | `input` handler: validación ≤ 0 + > límite con SweetAlert |
| `resources/js/cambio-aceite/shared.js` | `import Swal` + `input` handler: validación ≤ 0 + > límite con SweetAlert |
| `resources/js/ingresos/shared.js` | `import Swal` + `input` handler: validación ≤ 0 + > límite con SweetAlert |

> Los archivos `cambio-aceite/confirmar.js`, `cambio-aceite/edit.js`, `ingresos/confirmar.js` e `ingresos/edit.js` usan `initMetodoPago()` desde sus respectivos `shared.js`, por lo que quedan cubiertos automáticamente.

---

## Verificación

- **101 tests pasaron** sin regresiones
- **Build de Vite** compiló correctamente
