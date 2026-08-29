# Integración: Nuevos Campos en Entidades y Módulos de Gestión

> **Estado:** ✅ IMPLEMENTADO — migraciones aplicadas, backend y frontend integrados.
> **Alcance:** Servicios, Productos, Trabajadores y Clientes (BD + backend + frontend).
>
> ### Decisiones confirmadas por el usuario
> 1. `descripcion` (servicios/productos): **opcional**.
> 2. Apellidos paterno y materno: **obligatorios** en formulario (NULL en BD para legado).
> 3. `dni`: obligatorio, exactamente 8 dígitos y **único** entre trabajadores.

---

## 0. Evidencia de Ejecución

| Paso | Resultado |
|---|---|
| Pre-migración (`nombre` máx 26 chars en 10 filas) | ✅ Sin riesgo de recorte |
| Migraciones ×3 ejecutadas | ✅ DONE (2026_08_22_000001/2/3) |
| `php artisan view:cache` | ✅ Todas las vistas Blade compilan |
| `npm run build` (Vite) | ✅ Build exitoso, entries nuevos incluidos |
| `php artisan test` | ✅ 101 passed (3103 assertions) |
| Verificación BD (`SHOW COLUMNS`) | ✅ Estructuras según plan |
| Reglas regex (14 casos de prueba) | ✅ Comportamiento correcto (vacío lo salta `nullable` en Laravel) |
| Vitest `tests/js/users/toggle.property.test.js` | ⚠️ Fallo PRE-EXISTENTE ajeno a esta integración (`document is not defined`, entorno node; archivo no modificado aquí) |

Estructura final verificada:

```
trabajadores: id · dni char(8) NULL UNIQUE · nombre varchar(50)
              · apellido_paterno varchar(50) NULL · apellido_materno varchar(50) NULL
              · foto text NULL · estado · timestamps
servicios:    ... · descripcion varchar(100) NULL (tras nombre)
productos:    ... · descripcion varchar(100) NULL (tras nombre)
```

---

## 9. Ronda 2: Validación de Vehículos y Servicios (IMPLEMENTADA)

Restricciones de formato aplicadas a formularios de creación/edición:

| Módulo | Campo | Servidor | Cliente |
|---|---|---|---|
| Vehículos | Nombre | `required, max:30, regex alfanumérico` | `maxlength=30`, `data-filter="alphanumeric"` |
| Vehículos | Descripción | `nullable, max:100, regex alfanumérico` | `maxlength=100`, `data-filter="alphanumeric"` |
| Servicios | Nombre | `required, max:30, regex alfanumérico` (antes `max:100` sin formato) | `maxlength=30`, `data-filter="alphanumeric"` |
| Servicios | Descripción | ya cubierta en ronda 1 (`max:100`, alfanumérico) | ídem |

Regex aplicado: `/^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ ]+$/u` — letras (con acentos/ñ), números y espacios; sin símbolos.

**Cambios técnicos:**
- `VehiculoController`: constante `ALFANUMERICO_RULE`; reglas nuevas en `store()` y `update()`.
- `ServicioController`: constante `NOMBRE_RULES` (max 30 + alfanumérico).
- Vistas `vehiculos/create|edit` y `servicios/create|edit`: atributos maxlength + data-filter + textos de ayuda.
- **Filtrado global:** `initInputFilters()` se ejecuta ahora desde `app.js` para cualquier página con `[data-filter]` (con guard anti doble-binding); los entry points de trabajadores dejaron de llamarlo explícitamente.
- `input-filters.js`: la normalización inicial ya NO recorta valores que excedan el límite (evita truncar datos legados al abrir el formulario); el límite se aplica durante edición activa y por validación server-side.

⚠️ **Datos legados que exceden los nuevos límites** (se rechazarán al intentar editar esos registros hasta corregirlos):
- `servicios.nombre`: 1 fila con 33 caracteres (límite 30)
- `vehiculos.descripcion`: 1 fila con 102 caracteres (límite 100)

No se redimensionaron columnas BD a propósito: truncar datos existentes sería destructivo. Si deseas normalizarlos, indícalo y ajusto esos registros o las columnas.

---

## 10. Ronda 3: Clientes — Campos de Identidad y Validaciones (IMPLEMENTADA)

Restricciones de formato y nuevos campos aplicados a los módulos de creación/edición del módulo Clientes:

| Módulo | Campo | BD | Servidor | Cliente |
|---|---|---|---|---|
| Clientes | DNI | varchar(8) NULL UNIQUE *(sin cambio)* | `required, string, digits:8, regex numérico, unique` | `maxlength=8`, `inputmode=numeric`, `data-filter="digits"` |
| Clientes | Nombres | varchar(100) → **varchar(50)** NULL | `required, max:50, regex solo letras` (antes `max:100` sin formato) | `maxlength=50`, `data-filter="letters"` |
| Clientes | Apellido paterno *(nuevo)* | **varchar(50)** NULL | `required, max:50, regex solo letras` | `maxlength=50`, `data-filter="letters"` |
| Clientes | Apellido materno *(nuevo)* | **varchar(50)** NULL | `required, max:50, regex solo letras` | ídem |
| Clientes | Teléfono | varchar(20) *(sin cambio)* | `nullable, string, digits:9, regex numérico` (antes `max:20` libre) | `maxlength=9`, `inputmode=numeric`, `data-filter="digits"` |

Regex "solo letras": idéntico al de trabajadores — `/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u` — admite acentos, ñ y ü, con espacios internos simples; rechaza números, símbolos y espacios dobles.

**Cambios técnicos:**
- Migración `2026_08_22_000004_resize_nombre_add_apellidos_to_clientes_table.php`: redimensiona `nombre` a varchar(50) y crea `apellido_paterno` / `apellido_materno` varchar(50) NULL después de `nombre`.
- `ClienteController`: constante `SOLO_LETRAS_RULE`; método privado `validationRules(?Cliente)` compartido por `store()` y `update()` (el `unique` del DNI se auto-excluye en edición); `buscarPorPlaca()` añade `nombre_completo` al JSON.
- `app/Models/Cliente.php`: `$fillable` += apellidos; casts; accessor `nombre_completo` (concatena solo las partes no nulas → compatible con registros legados).
- Vistas `clientes/create|edit`: Nombres + Ap. Paterno + Ap. Materno + Teléfono con filtros en vivo vía `initInputFilters()` (global desde `app.js`, sin entry points nuevos en `vite.config.js`) y textos de ayuda por campo.
- Vista `clientes/index`: columna Nombre muestra `$cliente->nombre_completo`; aria-labels de Editar/Eliminar actualizados.
- `resources/js/buscador-placa.js`: el resumen de "Cliente Frecuente" muestra `nombre_completo || nombre`.

### Decisiones de diseño

1. Apellidos **obligatorios** en formulario; NULL en BD para legado (mismo patrón que trabajadores).
2. Teléfono sigue siendo opcional, pero si se ingresa debe ser exactamente 9 dígitos.
3. DNI conserva la unicidad entre clientes (preexistente) y ahora exige exactamente 8 dígitos numéricos (`digits:8` + regex, defensa en profundidad).

### Evidencia de Ejecución

| Paso | Resultado |
|---|---|
| Pre-migración (`LENGTH(nombre)` máx = 26 sobre 20 filas) | ✅ Sin riesgo de recorte al pasar a varchar(50) |
| Migración ejecutada | ✅ DONE (`2026_08_22_000004`) |
| Estructura BD (`SHOW COLUMNS`) | ✅ nombre varchar(50) · apellido_paterno/materno varchar(50) NULL |
| Reglas regex (16 casos de prueba) | ✅ Comportamiento correcto |
| `php artisan view:cache` | ✅ Todas las vistas Blade compilan |
| `npm run build` (Vite) | ✅ Build exitoso |
| `php artisan test` | ✅ 101 passed (3103 assertions) |

⚠️ **Datos legados:** registros con apellidos vacíos o teléfono ≠ 9 dígitos se rechazarán al intentar editarlos hasta completar/corregir dichos campos (mismo criterio que Ronda 2). No se normalizó nada automáticamente para evitar truncamientos destructivos.

**Sin cambios (se preservan):** flujos inline de `IngresoController` y `CambioAceiteController` (validan sus propios campos de cliente con reglas antiguas); al ser los apellidos NULLables, esos flujos siguen creando clientes sin romperse. Vistas de ingresos/cambio-aceite/ventas continúan leyendo `->nombre` (disponible `nombre_completo` para migración visual futura).

---

## 1. Estado Actual (Análisis)

### 1.1 Stack

| Componente | Tecnología |
|---|---|
| Framework | Laravel 12 (Blade + Vite, sin SPA) |
| BD | MySQL (`carwash_sistema`) |
| Imágenes | Cloudinary (`cloudinary-labs/cloudinary-laravel ^3.0`, DSN en `.env`) |
| Validación | FormRequest inline en controladores (`$request->validate()`) |
| JS | Módulos ES por módulo (`resources/js/<modulo>/*.js`) registrados explícitamente en `vite.config.js` |

### 1.2 Tabla `servicios`

| Campo | Tipo actual | Observación |
|---|---|---|
| id | bigint PK | |
| **nombre** | varchar(100) | Sin validación de formato (acepta símbolos) |
| precio | decimal(8,2) | |
| estado/timestamps | — | |

- Modelo `app/Models/Servicio.php`: `$fillable = ['nombre', 'precio']`.
- Controlador `ServicioController`: valida solo `required|string|max:100` y `numeric`.
- Vistas `create.blade.php` / `edit.blade.php`: input texto plano, sin filtro de caracteres.
- Vista `index.blade.php`: columnas Nombre / Precio / Acciones.

### 1.3 Tabla `productos`

| Campo | Tipo actual | Observación |
|---|---|---|
| id | bigint PK | |
| **nombre** | varchar(150) | Sin validación de formato |
| precio_compra / precio_venta | decimal(8,2) | |
| stock / inventario | int | |
| activo | boolean | |
| **foto** | text | URL absoluta de Cloudinary (o ruta local legacy) |
| categoria_id / marca_id | FK | |

- Modelo `Producto.php`: accessor `getFotoUrlAttribute()` que resuelve URLs http vs locales.
- Controlador `ProductoController`: flujo Cloudinary completo:
  - `store()`: sube con `Cloudinary::uploadApi()->upload($file->getRealPath())` → guarda `secure_url`.
  - `update()`: si hay foto nueva, extrae `public_id` de la URL anterior y la destruye en Cloudinary antes de subir la nueva.
  - `destroy()`: elimina imagen de Cloudinary/storage fuera de la transacción.
- Frontend foto: formulario `enctype="multipart/form-data"`, preview local vía `resources/js/productos/shared.js → initFotoPreview()`, invocado por `create.js` y `edit.js`.
- Index híbrido: filas server-side (Blade) + re-render AJAX (`ProductoController@buscar` → JSON → `index.js buildRow()`).

### 1.4 Tabla `trabajadores`

| Campo | Tipo actual | Observación |
|---|---|---|
| id | bigint PK | |
| **nombre** | varchar(100) | Almacena nombre completo libre; regla `unique` en validación |
| estado | boolean | |

**No existen**: `dni`, apellidos separados, `foto`.

- Modelo `Trabajador.php`: `$fillable = ['nombre', 'estado']`.
- Controlador `TrabajadorController`: valida `nombre` (required, string, max:100, unique) + estado. Sin manejo de imágenes.
- Formularios `create/edit`: solo campo nombre + select estado.
- **Dependencias externas (no se romperán):** `CambioAceiteController` e `IngresoController` usan `Trabajador::where('estado', true)` y muestran `$trabajador->nombre` en selects/listados de `ingresos/*`, `cambio-aceite/*`. Como la columna `nombre` se conserva (solo se redimensiona), esos flujos siguen funcionando intactos.

### 1.5 Infraestructura reutilizable detectada

- `docs/flujo-cloudinary-productos.md`: documenta el flujo de referencia a replicar.
- `resources/js/productos/shared.js → initFotoPreview(inputId, previewId, bloqueId)`: exportable a otros módulos.
- `vite.config.js`: los nuevos entry points JS deben registrarse manualmente.
- Permisos de rutas ya definidos (`acceso-trabajadores`, resource completo).

---

## 2. Cambios Propuestos (Estado Futuro)

### 2.1 Resumen de campos nuevos/modificados

| Tabla | Campo | Tipo BD | Posición | Regla de validación (servidor) | Filtro cliente (input) |
|---|---|---|---|---|---|
| servicios | **descripcion** *(nuevo)* | varchar(100) NULL | después de `nombre` | `nullable, string, max:100, regex alfanumérico` | Solo letras/números/espacio, máx 100 |
| productos | **descripcion** *(nuevo)* | varchar(100) NULL | después de `nombre` | `nullable, string, max:100, regex alfanumérico` | Solo letras/números/espacio, máx 100 |
| trabajadores | **dni** *(nuevo)* | char(8) NULL + UNIQUE | primero tras `id` | `required, digits:8, numeric regex, unique:trabajadores,dni` | Solo dígitos, exactamente 8, bloquea letras/símbolos |
| trabajadores | **nombre** *(modificado)* | varchar(100) → **varchar(50)** | — | `required, string, max:50, regex solo letras` — **se elimina el `unique` actual** | Solo letras (con acentos/ñ) y espacios internos |
| trabajadores | **apellido_paterno** *(nuevo)* | varchar(50) NULL | después de `nombre` | `required, string, max:50, regex solo letras` | Solo letras |
| trabajadores | **apellido_materno** *(nuevo)* | varchar(50) NULL | después de `apellido_paterno` | `required, string, max:50, regex solo letras` | Solo letras |
| trabajadores | **foto** *(nuevo)* | text NULL | después de `apellido_materno` | `nullable, image, mimes:jpg,jpeg,png,webp, max:2048` | Input file + preview (flujo Cloudinary de productos) |

> **Decisiones de diseño (punto de confirmación):**
> 1. `descripcion` es opcional (NULL en BD). Si lo quieres obligatorio, cambiar a `required`.
> 2. `dni` es NOT-required a nivel BD (filas legadas no tienen DNI) pero **obligatorio y único** a nivel formulario/API futura.
> 3. Apellidos obligatorios en formularios nuevos/edición; NULL en BD para compatibilidad con registros existentes.
> 4. Regex de "solo letras" admite acentos españoles y ñ (son letras del idioma); rechaza números, símbolos y espacios dobles.
> 5. Regex de `descripcion` admite letras, números y espacios (texto descriptivo); rechaza símbolos (`!@#$%`, etc.).

### 2.2 Expresiones regulares

```
Solo letras (nombres/apellidos):
  /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/

Alfanumérico con espacios (descripciones):
  /^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ ]+$/

DNI (redundante con digits:8, defensa en profundidad):
  /^[0-9]{8}$/
```

---

## 3. Archivos a Crear

| # | Archivo | Contenido |
|---|---|---|
| 1 | `database/migrations/2026_08_22_000001_add_descripcion_to_servicios_table.php` | `string('descripcion',100)->nullable()->after('nombre')` |
| 2 | `database/migrations/2026_08_22_000002_add_descripcion_to_productos_table.php` | ídem |
| 3 | `database/migrations/2026_08_22_000003_add_dni_apellidos_foto_to_trabajadores_table.php` | `char('dni',8)->nullable()->unique()`, `string('apellido_paterno',50)->nullable()`, `string('apellido_materno',50)->nullable()`, `text('foto')->nullable()`, redimensiona `nombre` a 50 con `->change()` |
| 4 | `resources/js/utils/input-filters.js` | Utilidad compartida: `initInputFilters(root)` que conecta inputs con `data-filter="letters|alphanumeric|digits"` bloqueando teclas/pégado inválido en vivo |
| 5 | `resources/js/trabajadores/create.js` | Importa `initFotoPreview` (de `productos/shared.js`) + `initInputFilters` |
| 6 | `resources/js/trabajadores/edit.js` | ídem (preview de nueva foto sobre la actual) |

## 4. Archivos a Modificar

| # | Archivo | Cambio |
|---|---|---|
| 7 | `app/Models/Servicio.php` | Agregar `descripcion` a `$fillable` |
| 8 | `app/Models/Producto.php` | Agregar `descripcion` a `$fillable` |
| 9 | `app/Models/Trabajador.php` | `$fillable` += dni, apellido_paterno, apellido_materno, foto. Accesor `foto_url` (copiar patrón de Producto). Accesor opcional `nombre_completo` |
| 10 | `app/Http/Controllers/ServicioController.php` | Validación de `descripcion` en `store()` y `update()` |
| 11 | `app/Http/Controllers/ProductoController.php` | Ídem en `store()`, `update()`; incluir `descripcion` en JSON de `buscar()` |
| 12 | `app/Http/Controllers/TrabajadorController.php` | Nueva validación completa (dni/apellidos/foto/nombre); subida Cloudinary en `store()`; reemplazo con borrado de imagen anterior en `update()`; borrado en `destroy()` (replicando `ProductoController`) |
| 13 | `resources/views/servicios/create.blade.php` | Campo Descripción tras Nombre (`maxlength=100`, `data-filter="alphanumeric"`, contador) |
| 14 | `resources/views/servicios/edit.blade.php` | ídem con `old('descripcion', $servicio->descripcion)` |
| 15 | `resources/views/servicios/index.blade.php` | Columna "Descripción" |
| 16 | `resources/views/productos/create.blade.php` | Campo Descripción tras Nombre |
| 17 | `resources/views/productos/edit.blade.php` | ídem |
| 18 | `resources/views/productos/index.blade.php` | Columna "Descripción" (fila Blade) |
| 19 | `resources/js/productos/index.js` | Renderizar `p.descripcion` en `buildRow()` |
| 20 | `resources/views/trabajadores/create.blade.php` | Formulario nuevo: DNI, Nombre, Ap. Paterno, Ap. Materno, Foto (preview), Estado; `enctype=multipart`; filtros en vivo |
| 21 | `resources/views/trabajadores/edit.blade.php` | ídem + imagen actual/placeholder + preview nueva imagen |
| 22 | `resources/views/trabajadores/index.blade.php` | Columnas: Foto (miniatura), DNI, Trabajador (`nombre_completo`), Estado, Acciones |
| 23 | `vite.config.js` | Registrar entries `trabajadores/create.js` y `trabajadores/edit.js` |

**Sin cambios (se preservan):** `routes/web.php`, permisos, `IngresoController`, `CambioAceiteController`, vistas de ingresos/cambio-aceite/ventas (siguen leyendo `->nombre`).

---

## 5. Flujo Cloudinary para Foto de Trabajador (réplica del módulo Productos)

```
CREATE:
  form multipart → input file (jpg/png/webp ≤2MB)
    → preview local (FileReader, initFotoPreview)
    → store(): Cloudinary::uploadApi()->upload(realPath) → $result['secure_url'] → Trabajador::create(foto)

UPDATE (si sube foto nueva):
    → extraer public_id de URL anterior (último segmento sin extensión)
    → Cloudinary::uploadApi()->destroy(public_id)  (try/catch silencioso)
    → upload nuevo → update(foto)

DESTROY:
    → destroy(public_id) si es URL http (fuera de transacción) → delete()
```

Modelo expone `foto_url` (URL directa si empieza con `http`, si no `asset('storage/...')`).

## 6. Estrategia de Migración y Riesgos

| Riesgo | Mitigación |
|---|---|
| Registros con `nombre` > 50 caracteres fallarían el `->change()` en MySQL strict | Pre-verificar con query antes de migrar; documentado en paso 1 de ejecución. Si existen, se decide recorte manual |
| Filas legadas sin DNI/apellidos/foto | Columnas creadas NULLables; formularios exigen datos completos solo en nuevos registros/ediciones |
| Unicidad de `nombre` eliminada | Sustituida por unicidad real en `dni` (único identificador de persona; base para futura API RENIEC) |
| Módulos externos muestran `->nombre` | Columna se mantiene; pasarán a mostrar solo nombres de pila. Disponible `nombre_completo` para migraciones visuales futuras |

## 7. Plan de Ejecución (tras tu confirmación)

1. **Verificación pre-migración:** consultar longitudes actuales de `trabajadores.nombre`.
2. Crear las 3 migraciones → `php artisan migrate`.
3. Actualizar modelos (fillable + accesores).
4. Actualizar controladores (validaciones + Cloudinary trabajador).
5. Actualizar vistas (servicios, productos, trabajadores) + `productos/index.js`.
6. Crear `utils/input-filters.js` + entries JS de trabajadores; registrar en `vite.config.js`.
7. **Verificación:** `php artisan migrate:status`, build de vite (`npm run build`), tests (`php artisan test --filter=Producto`, suite vitest `npm run test`), revisión manual de flujos create/edit de los 3 módulos.
8. Actualizar este documento a estado "IMPLEMENTADO" con evidencia.

## 8. Cómo Quedarán los Formularios (mock textual)

```
TRABAJADOR — Crear/Editar                    SERVICIO / PRODUCTO — Crear/Editar
┌──────────────────────────────┐             ┌──────────────────────────────┐
│ DNI*        [12345678] 8dig │             │ Nombre*      [___________]   │
│ Nombres*    [Juan______] L   │             │ Descripción  [___________]   │
│ Ap. Paterno*[Quispe_____L   │             │   (letras+números, 100 máx)  │
│ Ap. Materno*[García_____] L │             │ ...resto de campos igual...  │
│ Foto        [Elegir archivo] │             └──────────────────────────────┘
│             (preview 128px)  │
│ Estado*     [Activo ▼]       │
└──────────────────────────────┘
L = solo letras · 8dig = exactamente 8 dígitos · * = obligatorio
```
