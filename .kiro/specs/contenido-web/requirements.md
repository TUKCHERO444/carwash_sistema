# Documento de Requisitos — `contenido-web`

## Introducción

El módulo `contenido-web` crea un **panel independiente de gestión de contenidos** dentro del admin que controla el **display general de la página pública**: qué secciones se muestran en el inicio, los títulos/intros de las páginas de productos, servicios y marcas, y qué marcas aparecen (curaduría con orden). Está diseñado de forma extensible (tabla clave/valor) para controlar más cosas a futuro sin nuevas tablas de configuración.

**Dos capas claramente separadas:**

1. **Display general → panel `contenido-web`**: secciones on/off, textos por página, curaduría de marcas.
2. **Datos reales → toggles activar/inactivar en los CRUDs**: qué productos concretos se muestran (botón existente en `Producto`) y qué servicios (toggle de `servicios-web`). El panel **no crea entidades**, solo decide qué se muestra y cómo.

**Alcance de esta iteración (confirmado):** solo display general. Los **datos del negocio** (teléfono, horario, dirección, redes — hoy en `config/carwash.php`) **no** se migran al panel en esta iteración; se mantienen en `config/carwash.php` para una fase futura.

La página `/nuestros-productos` (mosaico) pasa a mostrar **categorías reales** de BD con productos activos (decisión previa), enlazando a `publica.productos.categoria`. El mosaic se muestra u oculta y sus textos provienen del panel.

---

## Glosario

- **Display general**: Contenido "promocional/de capa superior" de una página pública (secciones, textos, selección de marcas), distinto de los datos de entidad (productos, servicios).
- **ContenidoWeb**: Entidad clave/valor que guarda la configuración del display general (`clave` única, `valor`, `tipo`).
- **ContenidoWebService**: Servicio de lectura con fallback a valores por defecto; las páginas públicas funcionan en tests sin seed.
- **Curaduría de marcas**: Selección de marcas reales que se muestran en la web junto con su orden de aparición (persistida como JSON en `marcas_web`).
- **Fallback (default)**: Valor que se usa cuando una clave no existe aún en BD; cada clave tiene un default espejo del estado actual del sitio.

---

## Requisitos

### Requisito 1: Tabla clave/valor con defaults

**User Story:** Como Administrador, quiero que la configuración del sitio se persista en BD de forma genérica y extensible.

#### Criterios de Aceptación

1. THE Sistema SHALL crear la tabla `contenido_web` con `clave` (string 100, único), `valor` (text nullable), `tipo` (enum `bool` | `string` | `json`) y timestamps.
2. THE modelo `ContenidoWeb` SHALL existir con `$fillable` y casts `bool`/`json` según `tipo`.
3. THE Sistema SHALL definir un set de defaults por clave, espejo del estado actual del sitio, accesible desde `ContenidoWebService`.
4. THE defaults SHALL incluir al menos:
   - Toggles: `inicio_mostrar_marcas`, `inicio_mostrar_servicios`, `inicio_mostrar_productos`, `inicio_mostrar_proyecto`, `productos_mostrar_mosaico` → `true`.
   - Textos: `productos_titulo`, `productos_intro`, `servicios_titulo`, `servicios_intro`, `marcas_titulo`, `marcas_intro` → textos actuales de cada página.
   - Curaduría: `marcas_web` → `[]` (vacío = todas las marcas).
5. IF una clave no existe en BD, THE Sistema SHALL usar su default (sin lanzar error).

---

### Requisito 2: Pantalla de edición del display general

**User Story:** Como Administrador, quiero editar en una sola pantalla las secciones, textos y marcas de la web.

#### Criterios de Aceptación

1. THE ruta `GET /contenido-web` SHALL renderizar la vista `contenido-web.edit` con una sola pantalla organizada por secciones.
2. THE sección "Página de inicio" SHALL permitir activar/desactivar las secciones: marcas, servicios, productos destacados y último proyecto.
3. THE sección "Página de productos" SHALL permitir activar/desactivar el mosaico y editar `productos_titulo`/`productos_intro`.
4. THE sección "Página de servicios" SHALL permitir editar `servicios_titulo`/`servicios_intro`.
5. THE sección "Página de marcas" SHALL permitir editar `marcas_titulo`/`marcas_intro` y la curaduría: mostrar/ocultar cada marca real + orden numérico.
6. THE Sistema SHALL mostrar los valores actuales en BD, o los defaults si la clave aún no existe.
7. THE Sistema SHALL persistir los cambios vía `PUT /contenido-web` y redirigir con flash `success` "Contenido de la web actualizado correctamente."

---

### Requisito 3: Persistencia upsert y auditoría

**User Story:** Como Administrador, quiero que mis cambios se guarden sin duplicados y queden registrados en auditoría.

#### Criterios de Aceptación

1. THE Sistema SHALL hacer **upsert** por clave (crear si no existe, actualizar si existe) en una sola operación de guardado.
2. THE booleans SHALL persistirse como `'1'`/`'0'`; la curaduría de marcas como JSON ordenado.
3. THE Sistema SHALL registrar una acción de auditoría `'actualizar contenidos web'`.
4. THE modelo `ContenidoWeb` SHALL estar registrado en `AuditServiceProvider::$modelos` para que sus cambios individuales también queden auditados por el observer.

---

### Requisito 4: Permiso y navegación

**User Story:** Como Administrador, quiero acceder al panel desde el menú lateral con control de permisos.

#### Criterios de Aceptación

1. THE Sistema SHALL crear el permiso `acceso-contenido-web` en `PermissionSeeder`.
2. THE rutas GET/PUT `/contenido-web` SHALL estar protegidas por `permission:acceso-contenido-web`.
3. WHILE el usuario autenticado no tiene el permiso, THE Sistema SHALL retornar HTTP 403.
4. THE navbar del layout (`layouts/app.blade.php`) SHALL mostrar el enlace "Contenido de la web" bajo una sección "Sitio web" (sidebar desktop y bottom nav móvil), visible solo con el permiso.

---

### Requisito 5: Página de inicio — secciones y datos reales

**User Story:** Como visitante, quiero un inicio que refleje (o no) cada sección según lo configurado, con datos reales.

#### Criterios de Aceptación

1. WHEN `inicio_mostrar_marcas = false`, THE sección de marcas del inicio SHALL ocultarse.
2. WHEN `inicio_mostrar_servicios = false`, THE sección de servicios SHALL ocultarse (ver `servicios-web`).
3. WHEN `inicio_mostrar_productos = false`, THE sección de productos destacados SHALL ocultarse.
4. WHEN `inicio_mostrar_proyecto = false`, THE sección "último proyecto" SHALL ocultarse.
5. WHEN el inicio muestra marcas, THE Sistema SHALL usar las marcas curadas (`marcas_web`) como modelos reales (`$marca->nombre`), con fallback a todas las marcas ordenadas por nombre.
6. WHEN el inicio muestra productos, THE Sistema SHALL usar los últimos `Producto` con `activo = true` (take 6) con su marca.
7. THE Sistema SHALL eliminar los arrays hardcodeados de `PaginaInicioController::index()` y los marcar con comentario según su fuente.
8. THE inicio SHALL mantener la sección "Boletín" y los sellos de confianza incondicionales.

---

### Requisito 6: Página `/nuestros-productos` — mosaico con categorías reales

**User Story:** Como visitante, quiero ver el mosaico de categorías con categorías reales del inventario y enlazando a cada colección.

#### Criterios de Aceptación

1. WHEN `productos_mostrar_mosaico = false`, THE sección del mosaico SHALL ocultarse (la página conserva su cabecera).
2. WHEN se muestra, THE Sistema SHALL listar las `Categoria` que tienen al menos un `Producto` con `activo = true`, con el contador de productos activos.
3. THE mosaico SHALL renderizar cada tile con el partial `promo-categoria-producto` y enlazar a `route('publica.productos.categoria', ['categoria' => $categoria['slug']])`.
4. IF no hay categorías con productos activos, THE Sistema SHALL mostrar "Aún no hay categorías de productos disponibles."
5. THE layout de columnas (2-1-2) SHALL adaptarse a cualquier cantidad de categorías (reparto cíclico por grupos de 5), en vez de asumir exactamente 5 tiles.
6. THE título/intro de la página SHALL venir de `productos_titulo`/`productos_intro`.

---

### Requisito 7: Página `/nuestras-marcas` — marcas curadas

**User Story:** Como visitante, quiero ver en la página de marcas solo las marcas seleccionadas en el panel y con su texto de cabecera configurado.

#### Criterios de Aceptación

1. THE `marcas()` SHALL retornar las marcas curadas (`marcas_web`) en su orden, con fallback a todas ordenadas por nombre.
2. IF la curaduría está vacía o no existe, THE Sistema SHALL mostrar todas las marcas (fallback).
3. THE vista `publica.marcas` SHALL iterar modelos reales (`$marca->nombre`) conservando el diseño wordmark actual.
4. THE título/intro de la página SHALL venir de `marcas_titulo`/`marcas_intro`.
5. WHEN la curaduría solo contiene una marca, THE página SHALL mostrar al menos esa marca (nunca vacía).

---

### Requisito 8: JS modularizado (panel)

**User Story:** Como desarrollador, quiero que el panel siga la modularización JS del proyecto.

#### Criterios de Aceptación

1. IF la pantalla requiere lógica de frontend (reordenar marcas), THE módulo `resources/js/contenido-web/edit.js` SHALL implementarla sin bloques `<script>` inline.
2. THE módulo SHALL registrarse en `vite.config.js` y cargarse con `@vite([...])` al final del `content`.
3. THE formulario SHALL funcionar sin JS (envío estándar POST/PUT); el JS solo mejora la UX de la curaduría.