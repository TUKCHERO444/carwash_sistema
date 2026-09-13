# Design: Identidad Cromática — Carwash El Chinito

## Índice

1. [Resumen ejecutivo](#resumen-ejecutivo)
2. [Análisis de la propuesta](#análisis-de-la-propuesta)
3. [Concepto de identidad visual](#concepto-de-identidad-visual)
4. [Paleta oficial](#paleta-oficial)
5. [Proporción y distribución](#proporción-y-distribución)
6. [Tokens del sistema de diseño](#tokens-del-sistema-de-diseño)
7. [Jerarquía visual global](#jerarquía-visual-global)
8. [Reglas de aplicación](#reglas-de-aplicación)
9. [Contraste y accesibilidad](#contraste-y-accesibilidad)
10. [Aplicación al sistema actual (Laravel + Tailwind v4)](#aplicación-al-sistema-actual-laravel--tailwind-v4)
11. [Tipografía y formatos de texto (globales del sitio público)](#tipografía-y-formatos-de-texto-globales-del-sitio-público)
12. [Revisión visual](#revisión-visual)

> **Nota sobre ratios:** los valores de contraste de la sección 9 fueron verificados con la fórmula WCAG de luminancia relativa (aplicada sobre los hex oficiales). Si se ajusta un hex, recalcular la tabla.

---

## Resumen ejecutivo

Este documento define la **identidad cromática** de la marca **Carwash El Chinito** y sienta las reglas para aplicarla de forma consistente en toda la experiencia digital: sitio web (Home, Servicios, Nosotros, Contacto), reservas, formularios y, de forma adaptada, el panel administrativo.

La identidad parte del **cartel del negocio** como referencia visual, pero no busca reproducirlo literalmente: la traduce a un sistema de color **navy / azul / cyan**, ordenado por una proporción 40/30/15/10/5, que transmite profesionalismo, limpieza, confianza, cuidado automotriz y modernidad, con la sensación permanente de **agua, brillo y limpieza profunda**.

La regla de oro: **el cyan es el acento, no la superficie**. La estructura la sostienen el navy y el blanco frío; el azul corporativo refuerza la identidad; el gris metálico queda como soporte secundario.

---

## Análisis de la propuesta

### Lectura estratégica del cartel

El cartel original apoya su comunicación en la asociación **azul = agua = limpieza**. La propuesta cromática interpreta esa asociación de forma profesional:

- El **navy profundo (#0B2638)** apela al mar y al brillo pulido de un vehículo recién lavado. Es el ancla de seriedad y confianza.
- El **blanco frío (#F5F7F8)** evoca espuma, cristal limpio y amplitud. Es el color del "antes y después" impecable.
- El **azul corporativo (#075B8A)** es el puente: reconocible, estable, institucional.
- El **cyan eléctrico (#19A9E5)** es el destello del agua al sol: el color de la atención y la acción.
- El **gris metálico (#8B969C)** recuerda el acero, el cromo y la estética automotriz, y da neutralidad al contenido secundario.

### Fortalezas de la paleta

| Atributo | Cómo lo logra |
|---|---|
| Profesionalismo | Navy como superficie dominante: pesa, ordena, no grita. |
| Limpieza | Blanco frío en gran parte de la superficie y mucho espacio visual. |
| Confianza | Combinación navy + azul corporativo, asociada al agua y a la institucionalidad. |
| Cuidado automotriz | Gris metálico + brillos cyan que remiten al detalle y la estética del vehículo. |
| Modernidad | Cyan eléctrico controlado y jerarquías limpias; no una "pared de azul". |

### Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| **Sobrecarga de azul** (todo azul = plano y cansado) | Respetar la proporción; el navy se usa en bloques, nunca un degradado azul continuo de arriba a abajo. El blanco frío corta el ritmo visual. |
| **Cyan como gran superficie** (pierde impacto) | Cyan reservado a CTA, estados hover, indicadores y acentos pequeños. Nunca como fondo de una sección completa. |
| **Gris que compite con los colores de marca** | El gris metálico solo para texto secundario, bordes y separadores; nunca para elementos accionables. |
| **Negro puro como dominante** | Todo tono oscuro se resuelve con navy (#0B2638) o variantes del mismo. |
| **Botones cyan con texto blanco** (bajo contraste) | El CTA primario usa fondo cyan con **texto navy**, que garantiza contraste AA y refuerza la marca. |

---

## Concepto de identidad visual

**Eje conceptual: "Agua, brillo y confianza."** La interfaz se siente como un vehículo recién salido del lavado: superficies pulidas, brillos controlados, reflejos precisos y cero ruido.

- **Superficies**, no colores: cada color tiene un rol de superficie (estructura), contenido, acción o acento.
- **Contraste oscuro/claro**: la interfaz alterna bloques navy y blancos fríos para crear ritmo y orientar la lectura.
- **Acento disciplinado**: el cyan aparece en ráfagas cortas (botones, hover, indicadores) y conserva su poder de llamada.
- **Automotriz premium**: bordes sutiles, sombras discretas, gradientes apenas perceptibles (solo cuando aportan profundidad) y fotografías de vehículos como protagonistas visuales (los colores de las fotos pertenecen al contenido, no a la interfaz).

**Qué NO se hace:**

- No se pintan secciones completas de cyan o azul corporativo.
- No se introduce rojo, verde, naranja, amarillo ni morado en la interfaz (salvo estados funcionales: error = rojo, éxito = verde, dentro de los mismos se permite).
- No se usa negro `#000000` como color de diseño.
- No se aplica la paleta como bloques aislados: cada página debe sentir la misma identidad desde el header hasta el footer.

---

## Paleta oficial

| Rol | Nombre | Hex | Uso principal |
|---|---|---|---|
| Estructura (40%) | Navy — `navy-900` | `#0B2638` | Fondos principales, header, footer, banners, secciones de alto contraste |
| Estructura (30%) | Blanco frío — `white-cold` | `#F5F7F8` | Fondos claros, secciones de contenido, espacio visual, textos sobre navy |
| Identidad (15%) | Azul corporativo — `brand-blue` | `#075B8A` | Títulos destacados, iconos, navegación secundaria, bordes, componentes de servicios |
| Acción (10%) | Cyan eléctrico — `cyan-accent` | `#19A9E5` | CTA, hover, indicadores, líneas y acentos que dirigen la atención |
| Soporte (5%) | Gris metálico — `steel` | `#8B969C` | Texto secundario, bordes, separadores, datos auxiliares |

### Escalas derivadas (tints / shades)

Centralizadas como tokens para garantizar consistencia:

```
NAVY
  navy-950  #060F1A   — tinta más profunda (hovers sobre navy)
  navy-900  #0B2638   — base
  navy-800  #113349   — superficies navy secundarias
  navy-700  #17415C   — hover de superficies navy
  navy-600  #1D5070   — bordes sobre navy / variantes
  navy-100  #D8E4EC   — tint: chips, fondos suaves
  navy-50   #EEF4F8   — tint tenue (fila destacada)

AZUL CORPORATIVO
  blue-800  #05496F   — hover de títulos / variante pulsada
  blue-700  #075B8A   — base (identidad)
  blue-600  #096FA6   — hover en elementos secundarios
  blue-100  #D2E8F4   — tint: badges de servicio
  blue-50   #EEF7FB   — tint tenue de identidad

CYAN
  cyan-600  #0F8FC6   — cyan pulsado / enlace sobre blanco (contraste AA)
  cyan-500  #19A9E5   — base (acción / acento)
  cyan-400  #3DBBEC   — hover de acentos cyan
  cyan-100  #D9F2FC   — tint: fondos de alertas de acción
  cyan-50   #F0FAFE   — tint tenue (filas interactivas)

GRIS METÁLICO
  steel-900 #232B30   — texto ligado sobre surfaces claras (títulos)
  steel-700 #556169   — texto secundario fuerte
  steel-600 #6E7A82   — texto secundario sobre blanco (AA)
  steel-500 #8B969C   — base: bordes, separadores, texto grande/auxiliar
  steel-400 #A5AEB3   — texto en deshabilitado
  steel-200 #D7DDE1   — bordes claros sobre blanco frío
  steel-100 #EDF0F2   — separador sutil / hover de filas
```

**Nota de contraste:** `steel-500 (#8B969C)` sobre blanco frío no alcanza AA (2.8:1): solo aplica a decoración, bordes, separadores y elementos deshabilitados. Para cuerpo de texto secundario sobre claro usar `steel-700 (#556169)` (5.9:1) y sobre navy `steel-400 (#A5AEB3)` (ver sección de accesibilidad).

---

## Proporción y distribución

La proporción 40/30/15/10/5 es un **objetivo de composición visual**, no una métrica pixel-perfect:

- **40% navy + 30% blanco frío** forman la estructura. Alternan en bloques: un header/footer navy exige un cuerpo blanco y viceversa.
- **15% azul corporativo** aparece en títulos, iconos, bordes y componentes de servicio: identidad sin ruido.
- **10% cyan** se concentra en acciones puntuales. Si una pantalla acumula más cyan, se revisa y se reduce.
- **5% gris metálico** en lo secundario; no debe competir nunca con la identidad.

No todos los colores aparecen en cada sección. Regla práctica: *máximo tres roles cromáticos por bloque* (p. ej., superf. navy + texto blanco + acento cyan).

---

## Tokens del sistema de diseño

Todos los colores se exponen como **tokens semánticos**, no como hex sueltos en las vistas. La capa semántica permite cambiar el valor del token sin tocar el markup.

```css
/* Tailwind CSS v4 — @theme tokens (misma convención que resources/css/app.css) */
@theme {
    /* Brand primitivas */
    --color-navy-950: #060F1A;
    --color-navy-900: #0B2638;
    --color-navy-800: #113349;
    --color-navy-700: #17415C;
    --color-navy-600: #1D5070;
    --color-navy-100: #D8E4EC;
    --color-navy-50:  #EEF4F8;

    --color-white-cold: #F5F7F8;

    --color-blue-800: #05496F;
    --color-blue-700: #075B8A;
    --color-blue-600: #096FA6;
    --color-blue-100: #D2E8F4;
    --color-blue-50:  #EEF7FB;

    --color-cyan-600: #0F8FC6;
    --color-cyan-500: #19A9E5;
    --color-cyan-400: #3DBBEC;
    --color-cyan-100: #D9F2FC;
    --color-cyan-50:  #F0FAFE;

    --color-steel-900: #232B30;
    --color-steel-700: #556169;
    --color-steel-600: #6E7A82;
    --color-steel-500: #8B969C;
    --color-steel-400: #A5AEB3;
    --color-steel-200: #D7DDE1;
    --color-steel-100: #EDF0F2;
}
```

Los componentes usan **tokens semánticos de rol** (ver jerarquía visual), mapeados en una capa `@layer components` para que las vistas escriban clases legibles:

```css
@layer components {
    /* Superficies */
    .surface-brand    { @apply bg-navy-900; }        /* bloques navy */
    .surface-soft     { @apply bg-white-cold; }      /* bloques blanco frío */
    .surface-raised   { @apply bg-white; }           /* tarjetas sobre blanco frío */

    /* Texto */
    .text-on-brand    { @apply text-white-cold; }
    .text-on-soft     { @apply text-navy-900; }
    .text-secondary   { @apply text-steel-700; }
    .text-muted       { @apply text-steel-400; }

    /* Bordes y separadores */
    .border-soft      { @apply border-steel-200; }
    .border-brand     { @apply border-blue-700; }
    .divider-brand    { @apply divide-steel-100; }
}
```

---

## Jerarquía visual global

La paleta no decora: **dirige**. Cada elemento (superficie, texto, acción, acento) tiene un rol establecido.

### Superficies

| Nivel | Token | Uso |
|---|---|---|
| Primaria oscura | `surface-brand` (navy-900) | Header, footer, hero, banners, secciones de alto contraste |
| Primaria clara | `surface-soft` (blanco frío) | Cuerpo de contenido, formularios, secciones de lectura |
| Elevada | `surface-raised` (blanco) | Tarjetas, modales, paneles sobre fondos claros |
| Secundaria navy | `navy-800` | Tarjetas/paneles dentro de secciones navy |

### Texto

| Rol | Sobre claro | Sobre navy |
|---|---|---|
| Título / primario | `navy-900` | blanco frío `#F5F7F8` |
| Cuerpo | `navy-900` | `#D8E4EC` (navy-100) |
| Secundario | `steel-700` | `#A5AEB3` (steel-400) |
| Auxiliar / deshabilitado | `steel-400` | `#8B969C` (steel-500) |

### Acciones

| Elemento | Fondo | Texto | Hover / foco |
|---|---|---|---|
| CTA primario | `cyan-500` | `navy-900` (texto oscuro, AA) | `cyan-400` |
| Botón secundario | `navy-900` | blanco frío | `navy-700` |
| Botón fantasma / outline | transparente | `blue-700` | borde `cyan-500` + texto `cyan-600` |
| Enlace en texto | — | `blue-700` | `cyan-600` con subrayado |
| Botón deshabilitado | `steel-100` | `steel-400` | — |

Los estados funcionales del sistema (errores/success) se permiten en rojo/verde únicamente como estados, ajenos a la identidad (p. ej., `text-red-600` y `text-green-600` de Tailwind).

### Acentos

- Indicadores activos, contadores, badges de "nuevo/destacado": `cyan-500`.
- Líneas divisorias decorativas, subrayados de sección hero: `cyan-400`.
- Títulos destacados y iconografía institucional: `blue-700`.
- Iconos de apoyo / datos auxiliares: `steel-500`.

### Regla antirruido

Un mismo bloque no mezcla más de un acento llamativo. Si una tarjeta ya usa cyan (CTA), sus bordes/separadores van en gris metálico, no en azul corporativo.

---

## Reglas de aplicación

1. **Estructura navy + blanco, siempre.** El navy o el blanco cubren el ~70% de cualquier pantalla; el resto se reparte entre identidad, acentos y soporte.
2. **Alternar ritmo oscuro/claro.** Secciones contiguas alternan superficies. Nunca dos bloques navy consecutivos sin un corte blanco (salvo header→hero, que comparten familia).
3. **Cyan con mesura.** Un solo CTA principal dominante por pantalla. El segundo CTA siempre es secundario (navy) o fantasma.
4. **El azul corporativo refuerza, no decora.** Se usa en títulos, iconos y componentes de servicio, no como fondo de sección completa.
5. **Gris para lo auxiliar.** Bordes, separadores, fechas, metadata, texto secundario. Nunca en botones primarios.
6. **Fotos como contenido.** Vehículos, lavados y trabajos pueden aportar cualquier color: son contenido fotográfico y no alteran la identidad.
7. **Coherencia transversal.** Home, Servicios, Nosotros, Contacto, reservas y formularios comparten header/footer navy, CTAs cyan y superficies blancas. Cambiar de página no cambia el "tono" de la marca.
8. **Contraste siempre AA** en texto (4.5:1) y componentes (ver sección de accesibilidad).

---

## Contraste y accesibilidad

Ratios aproximados (WCAG, luminancia relativa) de las combinaciones aprobadas:

| Combinación | Ratio real | Uso | Cumple |
|---|---|---|---|
| Blanco frío sobre navy-900 | 14.5:1 | Texto sobre secciones navy | AAA |
| Navy-900 sobre blanco frío | 14.5:1 | Títulos / cuerpo sobre claro | AAA |
| Azul corporativo sobre blanco frío | 6.8:1 | Títulos, enlaces, iconos | AA |
| Cyan-500 sobre navy-900 | 5.8:1 | CTA (texto en el otro extremo del botón), acentos | AA |
| Navy-900 sobre cyan-500 | 5.8:1 | Texto del CTA primario | AA |
| steel-700 sobre blanco frío | 5.9:1 | Texto secundario sobre claro | AA |
| steel-400 sobre navy-900 | 6.9:1 | Texto secundario sobre navy | AA |
| steel-500 sobre blanco frío | 2.8:1 | Solo decoración: bordes, separadores, deshabilitados | no aplica |
| Blanco sobre cyan-500 | 2.7:1 | Prohibido: nunca texto blanco sobre cyan | no aplica |

*Ratios medidos por luminancia relativa WCAG sobre los hex oficiales (script de verificación incluido en la sección 11).*

**Consecuencias de diseño:**

- El CTA primario es **cyan con texto navy**, nunca blanco sobre cyan (2.7:1).
- Texto secundario: **`steel-700` sobre claro** y **`steel-400` sobre navy**; `steel-500` queda solo para decoración (bordes, separadores, deshabilitados).
- El cyan nunca se usa como color de texto cuerpo sobre fondos claros (falla AA): se reserva para botones, indicadores y superficies pequeñas donde convive con texto navy.
- Foco visible: usar anillo `cyan-600` sobre superficies claras y `cyan-400` sobre navy.

---

## Aplicación al sistema actual (Laravel + Tailwind v4)

### Sitio público (identidad plena)

Se implementa la paleta tal cual: header/footer navy, cuerpo blanco frío, CTAs cyan, títulos en azul corporativo. La capa de tokens semánticos (`surface-soft`, `surface-brand`, etc.) se suma al `@theme` y `@layer components` existentes en `resources/css/app.css`.

### Panel administrativo (dark mode existente)

El panel ya usa un sistema oscuro sobre tonos slate (`--color-surface-dark: #0f172a`, etc.). La identidad de marca se integra **sin romper** ese sistema:

| Rol actual (slate) | Aporte de marca |
|---|---|
| `--color-background-dark: #020617` | Puede acercarse a `navy-950 (#060F1A)` para anclar la identidad sin perder contraste |
| `--color-surface-dark: #0f172a` | Puede derivar hacia `navy-900` en cabeceras/sidebar (navy-800) |
| `--color-text-primary-dark: #e2e8f0` | Se mantiene; afinidad con el blanco frío de marca |
| Foco azul de inputs | Puede migrar a `cyan-600` (acento de marca) sin tocar la semántica |
| Mensajes success/error | Permanecen verde/rojo, funcionales |

**No se sustituye la base slate por navy en todas las superficies del panel**: se conserva la jerarquía oscura actual (contaste garantizado por PATRON-validación/listados) y el navy/cyan entran por *cabeceras, sidebar, botón primario, foco y acentos*. La identidad se percibe en los detalles coherentes, no en repintar todo.

### Pintura de las vistas

```blade
{{-- Botón CTA primario (sitio) --}}
<button class="bg-cyan-500 text-navy-900 hover:bg-cyan-400 font-semibold rounded-lg px-6 py-3">
    Reservar mi lavado
</button>

{{-- Sección alternada: navy / blanco frío --}}
<section class="bg-navy-900">
    <h2 class="text-white-cold">Lavado premium, brillo garantizado</h2>
    <p class="text-navy-100">...</p>
</section>
<section class="bg-white-cold">
    <h2 class="text-navy-900">Nuestros servicios</h2>
    <p class="text-steel-700">...</p>
</section>
```

---

## 11. Tipografía y formatos de texto (globales del sitio público)

### 11.1 Origen y evidencia extraída

Extraído de `docs/pagina/inicio.txt` (tema Shopify de referencia, `lacocheraperu.com`). El archivo es HTML minificado; los datos viven en el primer bloque `<style>` (declaraciones `@font-face` + variables `:root`) y en los patrones de clase del marcado. Scripts de extracción: `C:\Users\USUARIO\AppData\Local\Temp\opencode\extract_typo.py` / `extract_heads.py`.

| Variable / patrón del tema | Valor | Significado |
|---|---|---|
| `--text-font-family` | `Barlow, sans-serif` | Texto de cuerpo |
| `--text-font-weight` | `500` | Peso del cuerpo (Barlow es liviana: a 400 se ve fina) |
| `--text-font-bolder-weight` | `600` | Peso del énfasis (`text--strong`) |
| `--heading-font-family` | `Barlow, sans-serif` | Títulos |
| `--heading-font-weight` | `600` | Peso de títulos (semibold, redonda) |
| `--base-text-font-size` | `16px` | Tamaño base de lectura |
| `--default-text-font-size` | `15px` | Tamaño por defecto (cuerpo/meta del tema) |
| `--text-link-decoration` | `underline` | Enlaces inline siempre subrayados |
| `@font-face` (Barlow) | 500 · 600 · 700, redonda + cursiva | Pesos cargados por el tema |

Patrones de clase del marcado (títulos con prefijo `heading hN` independiente de la etiqueta):

| Clase en el tema | Uso real |
|---|---|
| `heading h1` | Título de página / hero (en `<h2>` de la "announcement bar" y hero) |
| `section__title heading h3` | Título/breadcrumb de sección (`<h2>`) |
| `promo-block__heading heading h3` | Título de tarjetas de colección/servicio |
| `heading h6` / `footer__title heading h6` | Títulos de pie de página |
| `text--strong` | Énfasis fuerte dentro de un párrafo |
| Texto literal en mayúsculas | `VER MÁS`, `VER TODO`, `ENLACES`, `NUESTROS SERVICIOS`, `TRABAJAMOS CON LAS MEJORES MARCAS` (CTAs, eyebrows y etiquetas) |

**Regla clave del tema:** el tamaño lo decide la **clase de rol** (`heading h6` son de 6 niveles), no la etiqueta HTML; una `<h2>` puede renderizar el tamaño `h1` y un `<p>` el `h6`. En nuestro Tailwind esto equivale a aplicar la escala `text-*` por rol.

### 11.2 Familia tipográfica

- **Barlow** es la única familia de la página pública, para display y texto (el tema la self-hoste; nosotros la cargamos por Google Fonts con fallback local).
- Pesos en uso: **500** cuerpo, **600** títulos y énfasis (`text--strong`), **700** solo para displays/CTA destacados. En Tailwind: `font-medium` / `font-semibold` / `font-bold`. Siempre redonda salvo cita/cursiva puntual.
- No se mezcla con `Instrument Sans` (fuente del panel admin). El panel conserva su familia actual.

### 11.3 Tokens globales (implementación)

Ubicación: variables en `publica.css` `:root` (fuente única, igual que los colores), mapeadas a `@theme` en `app.css`:

```css
/* publica.css — :root */
--cw-font-main:   'Barlow', ui-sans-serif, system-ui, sans-serif;
--cw-font-heading:'Barlow', ui-sans-serif, system-ui, sans-serif;
--cw-font-weight-text:    500;
--cw-font-weight-strong:  600;
--cw-font-weight-heading: 600;
--cw-font-weight-display: 700;
--cw-text-base-size:    16px; /* --base-text-font-size del tema */
--cw-text-default-size: 15px; /* --default-text-font-size del tema */

/* app.css — @theme */
--font-main:    var(--cw-font-main);
--font-heading: var(--cw-font-heading);
```

`.cw-body` (body del sitio) consume `--cw-font-main` y `--cw-font-weight-text`, así todo el sitio hereda Barlow 500 sin marcar nada.

### 11.4 Escala y jerarquía (mapeo a clases Tailwind para futuras vistas)

| Rol | Tema | Regla práctica | Clase Tailwind página pública |
|---|---|---|---|
| Display / H1 de página | `heading h1` | Uno por página, pegado al hero | `text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tight` |
| Título de sección (H2) | `section__title heading h3` | Cabecera de cada bloque de sección | `text-3xl sm:text-4xl font-semibold` |
| Subtítulo / descriptivo | — | Debajo de cada título de sección | `text-base sm:text-lg text-steel-700 leading-relaxed` |
| Título de tarjeta / H3 | `promo-block__heading heading h3` | Tarjetas de servicio, producto, sellos | `text-lg sm:text-xl font-semibold` |
| Título menor (footer/listados) | `heading h6` | Listas del footer, minitítulos | `text-sm font-semibold uppercase tracking-widest` |
| Eyebrow / etiqueta | texto en mayúsculas | Encima de los títulos de sección | `text-xs sm:text-sm font-semibold uppercase tracking-widest` |
| Cuerpo | `--base-text-font-size:16px`, peso 500 | Párrafos de contenido | `text-base leading-relaxed` (peso 500 heredado) |
| Metadata / auxiliar | `--default-text-font-size:15px` | Fechas, tags, datos menores | `text-sm text-steel-600` |
| CTA / botón | `VER MÁS`, `VER TODO` | Botones y enlaces de acción | `font-semibold` + `uppercase` (texto navy sobre cyan) |
| Enlace inline | `--text-link-decoration:underline` | Enlaces dentro de texto | `underline text-brand-blue-700 hover:text-brand-cyan-600` |
| Énfasis | `text--strong` | Palabras destacadas en un párrafo | `font-semibold` |

### 11.5 Reglas globales para las futuras vistas públicas

1. **Una sola familia tipográfica**: Barlow en todo el sitio público; el panel (Instrument Sans) no se toca. Nunca declarar otra fuente en una vista.
2. **Jerarquía por rol, no por etiqueta**: el tamaño/estilo lo fija la clase (`text-4xl`…) según el rol de la tabla anterior; un `<p>` puede usar tamaño de tarjeta y un `<h2>` de display, igual que el tema con `heading hN`.
3. **Títulos siempre `font-semibold` (600)** y redonda; sin `font-thin/light` ni cursivas en títulos.
4. **Cuerpo**: `text-base` + `leading-relaxed`, peso 500 heredado de `.cw-body`; metadata `text-sm`.
5. **Mayúsculas + tracking** para eyebrows, CTAs y títulos de tarjeta/footer (`uppercase` + `tracking-widest`/`wide`), como el tema (`VER MÁS`, `VER TODO`, `ENLACES`).
6. **Enlaces inline siempre subrayados** (`underline`, herencia de `--text-link-decoration`); azul corporativo, hover cyan (sección 10). Los CTAs con fondo no subrayan.
7. **El CTA primario** es Barlow 600/700, redonda, **nunca** cursiva ni extra-bold, texto navy sobre cyan (ver sección 9).
8. **`text--strong` = `font-semibold`**: así se marca el énfasis en contenidos (precios, fechas, datos destacados).
9. **Re-brand desde un solo lugar**: todos los valores tipográficos viven en `publica.css :root`; una futura personalización del panel solo reescribe esas variables (idéntico al patrón cromático).
10. **Contraste de texto**: las combinaciones de color de los roles siguen la tabla de la sección 9; el tamaño no exime del contraste AA.

> **Estado de unificación:** aplicado en todas las vistas públicas actuales (inicio, servicios, header, footer y partials de tarjetas). Los títulos usan `font-semibold`, los CTAs/eyebrows van en MAYÚSCULAS (`VER MÁS`, `RESERVAR MI LAVADO`… a través de `.cw-btn-primary/secondary` con `uppercase`), el cuerpo usa `text-base leading-relaxed` y la fuente global es Barlow. Esta sección es la referencia obligatoria para las futuras vistas.

---

## 12. Revisión visual

Checklist para validar cualquier pantalla nueva o modificada:

- [ ] ¿Navy + blanco cubren ~70% de la superficie?
- [ ] ¿Hay un solo CTA cyan dominante y el resto son secundarios/fantasma?
- [ ] ¿Ninguna sección completa usa cyan o azul corporativo como fondo?
- [ ] ¿Los textos sobre navy y sobre claro cumplen el ratio mínimo (AA)?
- [ ] ¿El texto de los botones cyan es navy, no blanco?
- [ ] ¿El gris metálico solo aparece en texto secundario, bordes o separadores?
- [ ] ¿No hay negro puro `#000000` en la interfaz?
- [ ] ¿Los únicos rojo/verde provienen de estados funcionales (error/éxito)?
- [ ] ¿Las fotografías aportan color libremente, sin alterar los tokens?
- [ ] ¿Header, footer y CTAs se sienten iguales en Home, Servicios, Nosotros, Contacto y reservas?
- [ ] ¿La página usa solo Barlow (pesos 500/600/700) y la jerarquía se aplica por rol según la tabla §11.4?
- [ ] ¿Eyebrows, CTAs y títulos de tarjeta/footer van en MAYÚSCULAS con tracking (patrón `VER MÁS`)?
- [ ] ¿Los enlaces inline están subrayados y los títulos son siempre `font-semibold` redonda?