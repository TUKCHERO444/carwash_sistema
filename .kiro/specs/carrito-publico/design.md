# Diseño Técnico — Carrito de compra público (`/cuenta/carrito`)

## Visión General

Carrito-cotización persistido en BD para cuentas públicas, sin tocar ventas/stock/Kardex: `carritos` 1:1 por `user_id` + `carrito_items` con precio snapshot; botones en detalle + cards (forms POST que funcionan sin JS, mejora progresiva con un módulo `fetch`); página de carrito con cantidades/subtotales; PDF proforma con DomPDF; contador en header vía view composer. Todo tras `['auth','cliente','can:acceso-cuenta']`; guest → `cuenta.login`.

Stack: Laravel 12 / PHP 8.2, Blade + Tailwind 4 (Vite 7, tokens dark `.bg-surface/.input-main/...`), SQLite dev, tests en memoria (`phpunit.xml`), `America/Lima` (`now()`/Carbon, `->toDateString()`), DomPDF 3.1.

---

## Arquitectura

```mermaid
sequenceDiagram
    actor Cliente
    participant Web as routes/web.php (/cuenta/carrito)
    participant MW as auth+cliente+can + throttle
    participant Ctrl as CarritoController
    participant DB as carritos/carrito_items/productos
    participant Audit as AuditService/registros_auditoria

    Cliente->>Web: POST /cuenta/carrito/agregar (producto_id, cantidad)
    Web->>MW: zona cuenta + throttle + csrf
    MW->>Ctrl: agregar()
    Ctrl->>DB: transaction: carrito firstOrCreate(user) + item updateOrCreate<br/>(valida activo + 1<=cant<=stock; snapshot solo si nuevo)
    Ctrl->>Audit: cuenta/carrito_agregar
    Ctrl-->>Cliente: 302 back + success | JSON {total, conteo}
```

### Decisiones de diseño

- **BD, no sesión.** El carrito vive en tablas (`bigint`, FKs, `UNIQUE`) porque: solo existe para cuentas (1:1 natural con `users`), sobrevive dispositivos, se audita y se integra al flujo BD actual (ventas/Kardex). La sesión (`database` driver) sigue solo para auth.
- **Precio snapshot, no vivo.** `precio_unitario` se congela al crear el ítem: el PDF y los subtotales son deterministas aunque el operador cambie `precio_venta` después. Si el ítem ya existe y se suma cantidad, se mantiene su snapshot (cotización estable). Rationale D2.
- **Sin reserva de stock.** Agregar/actualizar valida `cantidad <= stock` vigente pero no descuenta ni aparta. El único descuento sigue en `VentaController::store` (transacción + Kardex). El PDF lo declara ("no reserva stock"). Evita oversell por abandono y no toca el flujo operativo.
- **`producto_id` restrict (no cascade).** Un producto con historial de carrito no se borra físicamente mientras tenga ítems (igual filosofía que ventas); el panel ya usa borrado lógico/activo para esto.
- **Guest redirige, no oculta (D4).** Los botones se renderizan siempre; el POST de guest cae en `auth` → `cuenta.login` con `info`. Una sola plantilla, sin bifurcaciones de vista, y el login ya enlaza a registro.
- **Forms primero, JS después.** Cada botón es `<form method="POST">` + `@csrf` (cero dependencia JS). El módulo `resources/js/cuenta/carrito.js` intercepta submits con `fetch` (header `Accept: application/json`), actualiza el contador y muestra toast; ante fallo de red hace submit nativo. Sin entradas Vite innecesarias: un solo módulo para detalle+cards+carrito.
- **Contador vía view composer.** `View::composer('publica.partials.header', ...)` con `CarritoService::conteoParaHeader($user)`: `0` para guest/no-Cliente, suma de cantidades en una query para Cliente. Sin N+1, sin tocar controladores públicos.
- **PDF reutilizando `ReportePdfService`.** Si su firma es `descargar($vista, $datos, $nombre)`-like, se reutiliza; si no, `CarritoPdfService` mínimo con idéntica convención (`loadView → a4 portrait → download`). Vista dedicada `cuenta.carrito.pdf` sin layout.
- **Ownership por scoping, sin Policy.** Relación 1:1 `user → carrito`: todo acceso pasa por `auth()->user()->carrito` o `where user_id`; un ítem ajeno es inalcanzable (404/403). No se crean Policies nuevas.
- **Rutas estáticas antes que resource** (gotcha `routes/web.php`); el grupo carrito es todo estático, se declara antes de cualquier resource de la zona.

---

## Componentes e Interfaces

### 1. Migraciones (aditivas, `bigint`, Lima)

| Migración | Cambio |
|---|---|
| `xxxx_create_carritos_table` | `id bigint PK`, `user_id FK->users cascade UNIQUE`, timestamps. |
| `xxxx_create_carrito_items_table` | `id bigint PK`, `carrito_id FK->carritos cascadeOnDelete`, `producto_id FK->productos restrictOnDelete` (ver nota restrict), `cantidad int`, `precio_unitario decimal(10,2)`, `UNIQUE(carrito_id, producto_id)`, timestamps, índices `(carrito_id) (producto_id)`. |

Nota restrict: el panel no borra productos con movimiento (usa `activo`); el FK `restrict` lo garantiza a nivel BD también para carritos.

### 2. Modelos

- `Carrito`: fillable `user_id`; `user(): BelongsTo`, `items(): HasMany(CarritoItem)`; `total(): Attribute` = `Σ cantidad*precio_unitario` (decimal 2); `conteo(): Attribute` = `Σ cantidad`.
- `CarritoItem`: fillable `carrito_id, producto_id, cantidad, precio_unitario`; `carrito(): BelongsTo`, `producto(): BelongsTo`; `subtotal(): Attribute`.
- `User`: `carrito(): HasOne`.
- `Producto`: sin cambios (se lee `activo, stock, precio_venta, foto`).
- Registrar `Carrito/CarritoItem` en `AuditServiceProvider::$modelos` + `AuditService::AUDITABLE` solo si se quiere auditoría automática de Eloquent; los eventos de negocio se registran manual (`carrito_*`, `modulo=cuenta`, actor = sesión). `EXCLUIDOS` no aplica (no son pivotes efímeras del flujo operativo).

### 3. `CarritoService` (`app/Services/CarritoService.php`)

Único punto de escritura (análogo a `CajaService` para caja):

```php
agregar(User $user, int $productoId, int $cantidad): CarritoItem  // valida activo+stock, firstOrCreate carrito, suma o crea (snapshot si nuevo), audita
actualizarCantidad(User $user, CarritoItem $item, int $cantidad): CarritoItem // ownership (carrito.user_id), valida tope stock, audita
quitar(User $user, CarritoItem $item): void
vaciar(User $user): void
resumen(User $user): array  // items con producto, total, conteo (para vista + header + PDF)
conteoParaHeader(?User $user): int  // 0 si guest/no-Cliente/pendiente
```

Validaciones: `producto activo`, `1 <= cantidad <= stock` (stock vigente leído con `lockForUpdate` dentro de transacción en `agregar/actualizar` para concurrencia de pestañas). Errores: `ValidationException` con mensajes ES junto al campo (`cantidad`, `producto_id`).

### 4. Controladores `app/Http/Controllers/Cuenta/*`

| Controlador | Métodos | Notas |
|---|---|---|
| `CarritoController` | `index / agregar / actualizar / quitar / vaciar / pdf` | `index` = `resumen()` + vista (vacío → estado vacío); `agregar` valida `producto_id exists+activo, cantidad 1..stock`, responde redirect `back()+success` o JSON `{ok, conteo, total, mensaje}` si `expectsJson`; `pdf` = vacío→redirect con aviso, si no `loadView('cuenta.carrito.pdf', resumen+cliente+fecha) → download(carrito-YYYYMMDD-HHMM.pdf)` + auditoría. |

### 5. Rutas `routes/web.php` (aditivas, zona privada cuenta)

```php
Route::middleware(['auth','cliente','can:acceso-cuenta'])->prefix('cuenta')->name('cuenta.')->group(function () {
    // ... existentes ...
    Route::get('/carrito', ...)->name('carrito.index');
    Route::get('/carrito/pdf', ...)->name('carrito.pdf'); // ANTES de items/{item}
    Route::post('/carrito/agregar', ...)->middleware('throttle:60,1')->name('carrito.agregar');
    Route::patch('/carrito/items/{item}', ...)->middleware('throttle:60,1')->name('carrito.actualizar');
    Route::delete('/carrito/items/{item}', ...)->name('carrito.quitar');
    Route::delete('/carrito', ...)->name('carrito.vaciar');
});
```

`{item}` se resuelve por scoping manual (`$user->carrito->items()->findOrFail`) — 404 ante ID ajeno (no filtra existencia).

### 6. Vistas + JS

| Vista | Cambio |
|---|---|
| `publica/partials/card-producto-grande.blade.php` | Form `POST cuenta.carrito.agregar` (`producto_id` + `cantidad=1` hidden) con botón `+` `absolute` esquina, `group-hover:opacity-100`, visible siempre en táctil (`max-sm:opacity-100`); solo si `activo && stock>0`. Sin lógica inline. |
| `publica/partials/card-producto-pequena.blade.php` | Igual, tamaño compacto. |
| `producto-detalle.blade.php` | Junto al CTA actual: selector cantidad (`input number 1..stock`, `max=stock`) + botón "Agregar al carrito"; agotado → se mantiene `Agotado`. |
| `publica/partials/header.blade.php:94-106` | `href="#"` → `cuenta.carrito.index` (Cliente) o `cuenta.login` (guest); contador `0` → `conteoParaHeader`. |
| `cuenta/carrito/index.blade.php` (nueva) | Tabla `overflow-x-auto`, `paginate(10)` si aplica, forms `PATCH/DELETE` por línea + vaciar + botón PDF + total; dark tokens; `@vite(['resources/js/cuenta/carrito.js'])` al final de `@section('content')`. |
| `cuenta/carrito/pdf.blade.php` (nueva) | Solo HTML+CSS inline apto DomPDF (sin layout, sin Tailwind CDN — DomPDF no ejecuta JS; estilos en `<style>`), tabla líneas + total + leyenda no-reserva. |
| `resources/js/cuenta/carrito.js` (nuevo) | Intercepta `form[data-carrito]` con `fetch` + `X-CSRF-TOKEN`; actualiza `[data-carrito-conteo]` y toast `[data-carrito-toast]`; fallback submit nativo. Entrada en `vite.config.js` `input`. |

Sin `<script>` inline salvo `window.* = @json(...)` si hiciera falta (no previsto).

### 7. Contador, correo, auditoría, limpieza

- View composer en `AppServiceProvider::boot()`: `View::composer('publica.partials.header', fn → ['carritoConteo' => CarritoService::conteoParaHeader(auth()->user())])`. Solo esa parcial (no global, para no pesar el panel).
- Sin correos en esta fase (el PDF se descarga, no se envía).
- Auditoría manual por evento (`carrito_agregar/actualizar/quitar/vaciar/pdf`, `modulo=cuenta`, `auditable = CarritoItem/Carrito`, actor sesión, sin secretos).
- Scheduler (`routes/console.php`): purga carritos de cuentas inactivas/ítems > 90 días (D3) con query directa; nunca toca ventas/Kardex.

---

## Modelos de Datos

```mermaid
erDiagram
    users {
        bigint id PK
    }
    carritos {
        bigint id PK
        bigint user_id FK_UK
    }
    carrito_items {
        bigint id PK
        bigint carrito_id FK
        bigint producto_id FK
        int cantidad
        decimal precio_unitario
    }
    productos {
        bigint id PK
        decimal precio_venta
        int stock
        boolean activo
    }
    users ||--o| carritos : "tiene"
    carritos ||--o{ carrito_items : "contiene"
    productos ||--o{ carrito_items : "cotizado en"
```

Convenciones: PK `bigint`, `cascadeOnDelete` en `carrito_id` y `user_id`, `restrictOnDelete` en `producto_id`, `UNIQUE(user_id)` + `UNIQUE(carrito_id, producto_id)`, `decimal(10,2)` igual que productos, timestamps `now()` Lima, cantidades `int` siempre `>= 1`.

---

## Propiedades de Corrección

*Tag: `// Feature: carrito-publico, Property {N}: ...`. PHPUnit + faker, ≥100 iteraciones.*

### Propiedad 1: Agregar crea/actualiza con snapshot y nunca excede stock
*Para cualquier* `(producto activo con stock S, cantidad C válida)`, tras `agregar`, el ítem queda con `cantidad` acumulada y `precio_unitario = precio_venta` al crear; *para cualquier* `C > S` o producto inagotado/inactivo, el sistema rechaza y no persiste nada. **Valida: Req. 1.3, 6.2.**

### Propiedad 2: Totales exactos
*Para cualquier* carrito, `total = Σ cantidad×precio_unitario` (2 decimales) y `conteo = Σ cantidad`, antes y después de actualizar/quitar. **Valida: Req. 3.1.**

### Propiedad 3: Snapshot estable ante cambio de precio
*Para cualquier* ítem creado a precio P, si el operador cambia `precio_venta` a P', el subtotal del ítem sigue usando P (el PDF también). **Valida: Req. 1.3, 5.2.**

### Propiedad 4: Ownership total
*Para cualquier* par de clientes A≠B, A no ve ni modifica ítems de B (listado sin filas ajenas; update/delete ajeno → 404/403). **Valida: Req. 3.4.**

### Propiedad 5: Guest nunca muta ni ve
*Para cualquier* endpoint de carrito, sin sesión → redirect a `cuenta.login` y cero filas creadas/modificadas. **Valida: Req. 1.4, 2.3, 3.6, 5.5.**

### Propiedad 6: PDF determinista
*Para cualquier* carrito no vacío, `GET pdf` descarga con nombre `carrito-*.pdf`, `content-type: application/pdf`, y su contenido refleja exactamente líneas×snapshot y total (verificado vía texto extraído o datos de la vista); carrito vacío → redirect sin PDF. **Valida: Req. 5.1–5.3.**

### Propiedad 7: El carrito nunca mueve stock
*Para cualquier* secuencia de agregar/actualizar/quitar/vaciar/pdf, `productos.stock` queda idéntico. **Valida: Req. 6.2.**

---

## Manejo de Errores

| Escenario | Comportamiento |
|---|---|
| Producto inexistente/inactivo o `cantidad` fuera de `[1, stock]` | 422/`back()->withErrors` junto al campo; JSON `{ok:false, errors}` si `expectsJson`; nada persiste. |
| Stock cambió entre vista y POST (otro compró en panel) | Se valida contra stock vigente con `lockForUpdate`; si ya no alcanza, error "Solo quedan N unidades". |
| Ítem ajeno (ID manipulado) | `findOrFail` scopeado → 404; sin filtrar existencia. |
| Carrito vacío + PDF | Redirect al carrito con aviso; sin excepción. |
| Guest en cualquier endpoint | Redirect `cuenta.login` con `info` (vía `auth` + handler existente). |
| Cuenta suspendida/desactivada | `EnsureCliente` la desloguea (flujo existente). |
| DomPDF falla (memoria/vista) | 302 con error genérico + log; nunca 500 con stack al cliente. |
| Concurrencia (doble clic agregar) | Transacción + `lockForUpdate` + `UNIQUE(carrito_id, producto_id)` (segundo request suma, no duplica). |
| Seeders/migraciones re-ejecutadas | `firstOrCreate`/columnas aditivas idempotentes; `migrate:fresh --seed` en dev. |

Seguridad: `@csrf` en todo POST/PATCH/DELETE, `throttle:60,1` en mutaciones, `Gate`/scoping por `user_id`, `password`/tokens nunca en logs, `SESSION_SECURE_COOKIE=true` en prod (existente).

---

## Estrategia de Pruebas

Dual: **ejemplo** (`tests/Feature/Cuenta/Carrito*Test.php`) + **propiedades** (`CarritoPropertyTest.php`, faker ≥100 iter, tag `Feature: carrito-publico, Property {N}`) + **JS** (`tests/js/cuenta/carrito.property.test.js` con `fast-check` solo si el módulo exporta lógica pura testeable en Node — p. ej. formateo de total; si es solo DOM/fetch, se omite con justificación).

| Test ejemplo | Requisito |
|---|---|
| `GET producto-detalle` muestra botón agregar si hay stock; `Agotado` si no | 1.1, 1.2 |
| Card con stock muestra form `+`; sin stock no | 2.1, 2.2 |
| `POST agregar` válido crea carrito+ítem snapshot y redirige con `success`; JSON si `expectsJson` | 1.3 |
| Guest en agregar/ver/pdf → `cuenta.login`, nada persiste | 1.4, 2.3, 3.6, 5.5 |
| Actualizar cantidad válida recalcula; excedida rechaza; quitar/vaciar limpian | 3.2, 3.3 |
| A no ve ni toca ítems de B | 3.4 |
| Header muestra conteo del Cliente y `0` + link login al guest | 4.1–4.3 |
| PDF descarga `carrito-*.pdf` `application/pdf`; vacío redirige | 5.1–5.3 |
| Auditoría `carrito_*` existe sin secretos; stock intacto tras operar | 6.3, 6.2 |

Propiedades 1–7 del § anterior. Mocks: ninguno externo (sin APIs ni Cloudinary en el flujo); `RefreshDatabase`; rol `Cliente` vía `Permission::firstOrCreate + givePermissionTo` o `AuthSeeder`; tiempos relativos `now()` (Lima); `SESSION_DRIVER=array`. Casos límite: `cantidad` 0/negativa/gigante, stock 0/1, producto desactivado a mitad de flujo, precio con 2 decimales, doble submit concurrente, carrito de 50 líneas, `producto_id` inexistente/ajeno.
