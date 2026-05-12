# Guía de Implementación: Modo Oscuro Anti-Fatiga

Esta guía detalla el patrón de diseño y las clases de utilidad necesarias para aplicar el Modo Oscuro en todos los módulos del sistema, garantizando consistencia visual y reduciendo la fatiga ocular.

## 1. Tokens de Color (CSS Variables)

Los colores están definidos en `resources/css/app.css` bajo el bloque `@theme`. **Evita usar colores negros puros (#000)**; utiliza siempre los tonos Slate definidos:

- `--color-background-dark`: `#020617` (Fondo general del body)
- `--color-surface-dark`: `#0f172a` (Tarjetas, tablas, contenedores de formularios)
- `--color-border-dark`: `#1e293b` (Bordes y divisiones)
- `--color-text-primary-dark`: `#e2e8f0` (Títulos y textos importantes)
- `--color-text-secondary-dark`: `#94a3b8` (Etiquetas, placeholders y textos secundarios)

---

## 2. Clases de Utilidad Semánticas

Para simplificar el desarrollo, utiliza las siguientes clases preconfiguradas en `app.css`:

| Clase | Uso Principal | Equivalente Tailwind |
| :--- | :--- | :--- |
| `.bg-surface` | Contenedores, Tarjetas, Tablas | `bg-white dark:bg-surface-dark` |
| `.input-main` | Inputs, Selects, Textareas | `bg-white dark:bg-slate-800 ...` |
| `.label-main` | Etiquetas de formulario (`<label>`) | `text-gray-700 dark:text-text-secondary-dark` |
| `.text-primary` | Texto principal | `text-gray-900 dark:text-text-primary-dark` |
| `.text-secondary`| Texto de apoyo | `text-gray-600 dark:text-text-secondary-dark` |
| `.border-main` | Bordes de contenedores | `border-gray-200 dark:border-border-dark` |
| `.divide-main` | Líneas divisorias en tablas | `divide-gray-200 dark:divide-border-dark` |

---

## 3. Patrones por Tipo de Vista

### A. Listados (Tablas)
Estructura recomendada para cualquier tabla:
```html
<div class="bg-surface rounded-lg border border-main overflow-x-auto">
    <table class="min-w-full divide-y divide-main">
        <thead class="bg-gray-50 dark:bg-slate-800/50">
            <tr>
                <th class="px-6 py-6 text-secondary uppercase tracking-wider text-xs font-medium">Columna</th>
            </tr>
        </thead>
        <tbody class="bg-surface divide-y divide-main">
            <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                <td class="px-6 py-8 text-primary">Dato</td>
            </tr>
        </tbody>
    </table>
</div>
```

### B. Formularios (Creación / Edición)
Patrón para campos de entrada:
```html
<div class="mb-5">
    <label for="campo" class="label-main mb-1">Nombre del Campo</label>
    <input type="text" id="campo" class="input-main w-full px-3 py-2 border rounded-lg text-sm">
</div>
```

### C. Modales y Alertas
Para asegurar que los modales no queden blancos:
- Usa `bg-surface` en el contenedor del modal.
- Usa `text-primary` en el título (`h3`).
- Usa `text-secondary` en el cuerpo del mensaje.
- Para el pie del modal (donde van los botones), usa `bg-gray-50 dark:bg-slate-800/50`.

---

## 4. Notas de Mantenimiento

1. **Imágenes y Fotos**: Los bordes de las fotos (`img`) deben usar `border-main` para no resaltar demasiado en fondo oscuro.
2. **Iconos SVG**: Siempre añade `dark:text-text-secondary-dark` o similar a los iconos que no tengan un color de marca específico.
3. **Transiciones**: Añade `transition-colors duration-300` a los contenedores principales para que el cambio de tema sea suave.

---

**Ubicación de Referencia**: `resources/css/app.css` contiene la definición técnica de estas clases.
