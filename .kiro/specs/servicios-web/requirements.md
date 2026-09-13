# Documento de Requisitos — `servicios-web`

## Introducción

El módulo `servicios-web` conecta la página pública de servicios del sitio (`/nuestros-servicios`) con la **entidad real `Servicio`** del módulo de Gestión Administrativa. Hasta ahora la página pública muestra un mosaico de categorías ficticias hardcodeadas; con este módulo la página se alimenta de los `Servicio` reales registrados en el panel, y cada servicio se muestra u oculta con su **toggle activar/inactivar** en el CRUD (el mismo mecanismo que ya usa `Producto`).

A diferencia de productos/marcas (cuyo *display general* se controla desde el panel independiente de contenidos `contenido-web`), los **servicios** se publican directamente desde su entidad: un servicio visible en la web es simplemente un `Servicio` con `activo = true`. La cabecera (título/intro) de la página sí proviene del panel de contenidos.

Se añaden al catálogo de servicios los campos de publicación: `activo` (bool), `orden` (int), `icono` (string) e `imagen` (string, Cloudinary). Además del toggle AJAX, los formularios de creación/edición crecen con estos campos.

---

## Glosario

- **Servicio**: Entidad que representa un trabajo que el taller puede realizar (ej. "Cambio de aceite", "Alineación y balanceo"), con nombre, descripción y precio.
- **Toggle activar/inactivar**: Mecanismo AJAX (PATCH, JSON) que alterna el campo `activo` de una entidad; es el mismo patrón de `ProductoController::toggleStatus()` + `productos/index.js`.
- **Cloudinary**: Proveedor externo de imágenes (`CLOUDINARY_URL` en `.env`). Sube/reemplaza/borra la `imagen` de un servicio.
- **Contenido web / panel de contenidos**: Módulo independiente (`contenido-web`) que controla el *display general* de las páginas públicas (títulos/intros, secciones). La página de servicios consume de ahí `servicios_titulo`/`servicios_intro`.
- **Auditoría**: Registro de acciones en `registros_auditoria` vía `AuditService`/`AuditModelObserver`.

---

## Requisitos

### Requisito 1: Campos de publicación en el catálogo

**User Story:** Como Administrador, quiero indicar si un servicio se publica en la web, su orden de aparición, su ícono y una imagen, para controlar qué servicios ven los clientes.

#### Criterios de Aceptación

1. THE tabla `servicios` SHALL tener los campos nuevos: `activo` (bool, default `true`), `orden` (int unsigned, default 0), `icono` (string nullable, default `'sparkles'`) e `imagen` (string nullable).
2. THE modelo `Servicio` SHALL incluir los campos nuevos en `$fillable`.
3. THE Sistema SHALL ofrecer un conjunto fijo de íconos válidos para `icono` (`sparkles`, `shield`, `oil`, `layers`, `paint`, `wrench`, `droplets`, `car`).
4. WHEN el servicio se crea sin `icono`, THE Sistema SHALL persistir `'sparkles'`.

---

### Requisito 2: Listado con estado y toggle activar/inactivar

**User Story:** Como Administrador, quiero ver el estado (Activo/Inactivo) de cada servicio y poder cambiar su visibilidad web desde el listado.

#### Criterios de Aceptación

1. THE vista `servicios.index` SHALL mostrar una columna "Estado" con un badge (`Activo`/`Inactivo`) por fila.
2. THE vista `servicios.index` SHALL mostrar un botón de toggle por fila ("Activar"/"Inactivar") que cambia el estado vía AJAX sin recargar.
3. WHEN el botón se presiona, THE Sistema SHALL enviar `PATCH /servicios/{servicio}/toggle-status` y retornar JSON `{success: true, activo: <bool>}`.
4. THE toggle SHALL registrar una acción de auditoría (`'toggle estado'`).
5. IF ocurre un error, THE Sistema SHALL retornar JSON `{success: false, message: ...}` con HTTP 500 y el frontend SHALL mostrar el mensaje.
6. THE Sistema SHALL insertar y actualizar las filas de la tabla sin respetar `activo` en el listado (se muestran todos los servicios, activos o no).

---

### Requisito 3: Formularios con campos de publicación

**User Story:** Como Administrador, quiero definir los campos web de un servicio al crearlo o editarlo.

#### Criterios de Aceptación

1. THE campo `nombre`, `descripcion` y `precio` SHALL mantener sus reglas de validación actuales.
2. THE Sistema SHALL aceptar y validar `activo` (bool), `orden` (int ≥ 0), `icono` (uno de la lista fija) e `imagen` (archivo de imagen opcional).
3. WHEN `imagen` se envía en edición, THE Sistema SHALL reemplazar la imagen anterior: borrar la previa en Cloudinary (si existía) y subir la nueva.
4. WHEN `imagen` no se envía, THE Sistema SHALL conservar la imagen existente.
5. WHEN se valida correctamente, THE Sistema SHALL redirigir a `servicios.index` con flash `success` y los mensajes actuales.

---

### Requisito 4: Gestión de imagen Cloudinary

**User Story:** Como Administrador, quiero que la imagen de un servicio se suba/reemplace/elimine correctamente en Cloudinary.

#### Criterios de Aceptación

1. THE upload SHALL usar `Cloudinary::uploadApi()->upload()` y persistir la URL segura (`secure_url`) en `servicios.imagen`.
2. WHEN existe imagen anterior, THE Sistema SHALL extraer el `public_id` de la URL y llamar `Cloudinary::uploadApi()->destroy($publicId)` antes de subir la nueva.
3. WHEN el servicio se elimina, THE Sistema SHALL destruir su imagen en Cloudinary **fuera de la transacción** de borrado.
4. IF Cloudinary falla al borrar, THE Sistema SHALL ignorar el error sin bloquear la operación (patrón existente en `ProductoController`).

---

### Requisito 5: Página pública `/nuestros-servicios` desde BD

**User Story:** Como visitante, quiero ver la lista de servicios que realmente ofrece el lavadero, con su descripción, precio e imagen.

#### Criterios de Aceptación

1. WHEN el usuario accede a `GET /nuestros-servicios`, THE `PaginaInicioController::servicios()` SHALL devolver **solo** los `Servicio` con `activo = true`, ordenados por `orden` (asc) y luego por `nombre`.
2. THE vista `publica.servicios` SHALL renderizar un grid de tarjetas de servicio reales (ícono, nombre, descripción, precio en formato `S/ X,XXX.XX` e imagen si existe), reemplazando el mosaico de categorías ficticias.
3. THE título e intro del encabezado de la página SHALL venir del panel de contenidos (`servicios_titulo` / `servicios_intro`).
4. WHEN no hay servicios activos, THE Sistema SHALL mostrar un estado vacío: "Aún no tenemos servicios disponibles."
5. THE Sistema SHALL conservar los sellos de confianza de la página.

---

### Requisito 6: Sección "Servicios" del inicio desde BD

**User Story:** Como visitante, quiero ver en el inicio los servicios destacados reales.

#### Criterios de Aceptación

1. WHEN el usuario accede a `GET /`, THE `PaginaInicioController::index()` SHALL devolver `$servicios` como los `Servicio` activos ordenados (`orden`, `nombre`), limitados a 3.
2. IF la sección está desactivada en el panel de contenidos (`inicio_mostrar_servicios = false`), THE sección SHALL ocultarse por completo.
3. THE tarjeta de servicio del inicio SHALL conservar su diseño actual (ícono por `active`, nombre, descripción).

---

### Requisito 7: Control de acceso

**User Story:** Como Administrador con permiso de servicios, quiero que el toggle y los campos nuevos estén protegidos.

#### Criterios de Aceptación

1. THE ruta `PATCH /servicios/{servicio}/toggle-status` SHALL estar bajo `middleware(['auth', 'permission:acceso-servicios'])`.
2. WHILE el usuario autenticado no tiene `acceso-servicios`, THE Sistema SHALL retornar HTTP 403 al intentar el toggle.
3. THE toggle SHALL aceptar solo usuarios autenticados (redirect a `/login` en caso contrario).

---

### Requisito 8: JS modularizado

**User Story:** Como desarrollador, quiero que el toggle de servicios siga la modularización JS del proyecto.

#### Criterios de Aceptación

1. THE módulo `resources/js/servicios/index.js` SHALL inicializar el toggle de estado por delegación.
2. THE patrón del toggle (badge + botón + delegación) SHALL extraerse a un módulo compartido `resources/js/toggle-status.js` reutilizable, y `resources/js/productos/index.js` SHALL refactorizarse para consumirlo sin cambiar su comportamiento.
3. THE módulo SHALL registrarse en el array `input` de `vite.config.js` y cargarse con `@vite([...])` al final del `@section('content')` de `servicios/index`.
4. THE módulo SHALL localizar el badge dentro de la misma fila (`closest('tr')`), sin depender de ids globales.