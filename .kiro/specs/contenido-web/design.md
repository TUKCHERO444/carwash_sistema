# Documento de Diseño Técnico — `contenido-web`

## Visión General

Crea un **panel independiente de gestión de contenidos** del cual dependerá el *display general* de la página pública. La configuración se guarda en una tabla clave/valor `contenido_web` leída a través de `ContenidoWebService` (con fallback a defaults constantes, de modo que las páginas y tests funcionan incluso sin seed). El panel edita en una sola pantalla: toggles de secciones (inicio, mosaico de productos), textos por página (productos, servicios, marcas) y curaduría de marcas (qué marcas y en qué orden).

Este spec NO toca los datos del negocio (`config/carwash.php`) ni crea/edita entidades; solo decide el display general. Los datos reales concretos (productos/servicios) siguen controlándose con los toggles activar/inactivar de sus CRUDs.

---

## Arquitectura

```
GET/PUT /contenido-web   (permission:acceso-contenido-web)
        │
        ▼
ContenidoWebController
    ├── edit()   → contenido-web.edit  (secciones: inicio, productos, servicios, marcas)
    └── update() → upsert por clave + AuditService::anotarAccion('actualizar contenidos web') → redirect
        │
        ▼
ContenidoWebService (singleton)
    ├── DEFAULTS / claves()        (definición + metadata de cada clave)
    ├── all() → mapa clave => valor (1 query por request)
    ├── bool(clave) / text(clave) / json(clave)   (fallback a defaults)
    └── marcasWeb() → Marca curadas+ordenadas (fallback: todas por nombre)
        │
        ▼
contenido_web (tabla clave/valor)   ←─ ContenidoWeb (Model, auditable)

Página pública: PaginaInicioController index/servicios/productos/marcas
    └── consume ContenidoWebService (bool/text/marcasWeb)
```

---

## Componentes e Interfaces

### Migración

`database/migrations/2026_09_13_000003_create_contenido_web_table.php`

```php
Schema::create('contenido_web', function (Blueprint $table) {
    $table->id();
    $table->string('clave', 100)->unique();
    $table->text('valor')->nullable();
    $table->string('tipo', 10)->default('string'); // bool | string | json
    $table->timestamps();
});
```

### Modelo `ContenidoWeb`

```php
protected $fillable = ['clave', 'valor', 'tipo'];

protected function casts(): array
{
    return [
        'valor' => 'string',
    ];
}
```

Registrar `ContenidoWeb::class` en `AuditServiceProvider::$modelos` (AuditServiceProvider.php L40-57) para que el observer audite sus cambios.

### `ContenidoWebService`

Singleton (registrado en `AppServiceProvider::register()`). Responsabilidades:

1. **Defaults y claves** — una constante/diccionario público que describe cada clave (tipo, sección, label, default):

```php
public const DEFAULTS = [
    'inicio_mostrar_marcas'    => ['tipo' => 'bool',   'seccion' => 'inicio',     'default' => true],
    'inicio_mostrar_servicios' => ['tipo' => 'bool',   'seccion' => 'inicio',     'default' => true],
    'inicio_mostrar_productos' => ['tipo' => 'bool',   'seccion' => 'inicio',     'default' => true],
    'inicio_mostrar_proyecto'  => ['tipo' => 'bool',   'seccion' => 'inicio',     'default' => true],
    'productos_mostrar_mosaico'=> ['tipo' => 'bool',   'seccion' => 'productos',  'default' => true],
    'productos_titulo'         => ['tipo' => 'string', 'seccion' => 'productos',  'default' => '¿Qué producto buscas para tu auto?'],
    'productos_intro'          => ['tipo' => 'string', 'seccion' => 'productos',  'default' => 'Explora las colecciones y encuentra el producto ideal para proteger y lucir tu vehículo.'],
    'servicios_titulo'         => ['tipo' => 'string', 'seccion' => 'servicios',  'default' => '¿Qué necesita tu auto?'],
    'servicios_intro'          => ['tipo' => 'string', 'seccion' => 'servicios',  'default' => 'Cada servicio es realizado por especialistas y con productos de primeras marcas.'],
    'marcas_titulo'            => ['tipo' => 'string', 'seccion' => 'marcas',     'default' => 'Trabajamos con las mejores marcas'],
    'marcas_intro'             => ['tipo' => 'string', 'seccion' => 'marcas',     'default' => 'Productos originales y equipos profesionales de primeras marcas para el cuidado de tu vehículo.'],
    'marcas_web'               => ['tipo' => 'json',   'seccion' => 'marcas',     'default' => []], // [{marca_id, orden}]
];
```

2. **Lectura** — `all(): Collection` hace UNA consulta por request y cachea en memoria. Métodos:

```php
public function bool(string $clave): bool      // valor BD '1'/'0' o default
public function text(string $clave): ?string   // valor BD o default
public function json(string $clave): array     // decodifica JSON o default
public function marcasWeb(): \Illuminate\Database\Eloquent\Collection
// ordena por 'orden' en marcas_web; si vacía → Marca::orderBy('nombre')->get()
```

### `ContenidoWebController`

```php
public function edit(): View
{
    $contenido = app(ContenidoWebService::class);
    $marcas = Marca::orderBy('nombre')->get();
    return view('contenido-web.edit', compact('contenido', 'marcas'));
}

public function update(Request $request): RedirectResponse
{
    $contenido = app(ContenidoWebService::class);
    $data = $contenido->clavesFormulario(); // filtra solo claves esperadas del request

    // booleans → '1'/'0', strings → trim, marcas_web → JSON [{marca_id, orden}]
    foreach ($data as $clave => $valor) {
        ContenidoWeb::updateOrCreate(['clave' => $clave], ['valor' => $valor, 'tipo' => ...]);
    }

    app(AuditService::class)->anotarAccion('actualizar contenidos web');

    return redirect()->route('contenido-web.edit')
        ->with('success', 'Contenido de la web actualizado correctamente.');
}
```

La curaduría llega como `marcas_web[]` (checkboxes) + `marcas_orden[id]` (números); el controlador arma el JSON `[{marca_id, orden}]` filtrando solo las marcas marcadas.

### Rutas

Dentro de un grupo nuevo en `routes/web.php` (después del grupo de servicios, antes de ventas):

```php
Route::middleware(['auth', 'permission:acceso-contenido-web'])->group(function () {
    Route::get('/contenido-web', [ContenidoWebController::class, 'edit'])->name('contenido-web.edit');
    Route::put('/contenido-web', [ContenidoWebController::class, 'update'])->name('contenido-web.update');
});
```

### Permiso y menú lateral

- `PermissionSeeder`: añadir `'acceso-contenido-web'` al array.
- `layouts/app.blade.php`: nueva sección "Sitio web" con el enlace "Contenido de la web" → `route('contenido-web.edit')`, envuelto en `@can('acceso-contenido-web')`, tanto en el sidebar desktop (~L152-190, junto a Inventario) como en el bottom nav móvil (~L464-503). Icono de página/globo.

### Vista `contenido-web/edit.blade.php`

```
@extends('layouts.app')
Section 'content':
  flash success
  <h1>Contenido de la web</h1>
  <form action=contenido-web.update method=PUT>  (+@csrf/@method('PUT'))
    ┌─ Página de inicio ───────────────┐
    │ ✓ Mostrar marcas en el inicio     │  (inicio_mostrar_marcas)
    │ ✓ Mostrar servicios destacados    │  (inicio_mostrar_servicios)
    │ ✓ Mostrar productos destacados    │  (inicio_mostrar_productos)
    │ ✓ Mostrar último proyecto         │  (inicio_mostrar_proyecto)
    ├─ Página de productos ─────────────┤
    │ ✓ Mostrar mosaico de categorías   │  (productos_mostrar_mosaico)
    │ [productos_titulo] [productos_intro]
    ├─ Página de servicios ─────────────┤
    │ [servicios_titulo] [servicios_intro]
    ├─ Página de marcas ────────────────┤
    │ [marcas_titulo] [marcas_intro]
    │ Curaduría:
    │  ☑ Marca A [orden 1]
    │  ☐ Marca B [orden 2]      (check = mostrar, número = orden)
    └───────────────────────────────────┘
    [Guardar cambios] (bg-blue-600)
  </form>
  @vite(['resources/js/contenido-web/edit.js']) (si aplica)
```

Cada input usa `{{ $contenido->bool('clave') ? 'checked' : '' }}` y `{{ $contenido->text('clave') }}` para mostrar valor en BD o default. Los textareas/inputs longs respetan dark mode (`.input-main`, `.label-main`).

`marcas_web::pluck('marca_id')` marca los checks y el orden proviene del JSON.

### `resources/js/contenido-web/edit.js`

- Reordena la curaduría de marcas con subir/bajar y mueve los inputs `marcas_orden[]`.
- Duradero: sin JS el formulario sigue funcionando (envío estándar).

---

## Cambios en la página pública

### `PaginaInicioController::index()`

```php
public function index(): View
{
    $contenido = app(ContenidoWebService::class);

    $servicios = Servicio::web()->take(3)->get(); // servicios-web
    $marcas = $contenido->marcasWeb();             // curaduría del panel
    $productos = Producto::where('activo', true)->with('marca')
                   ->latest()->take(6)->get();

    $empresa = config('carwash');

    return view('publica.inicio', compact('contenido', 'servicios', 'marcas', 'productos', 'empresa'));
}
```

Se eliminan los arrays hardcodeados (PaginaInicioController.php L20-53).

### `publica/inicio.blade.php`

Cada sección se envuelve en `@if ($contenido->bool(...))`:

```blade
@if ($contenido->bool('inicio_mostrar_marcas'))
    ... @foreach ($marcas as $marca) ... {{ $marca->nombre }} ...
@endif

@if ($contenido->bool('inicio_mostrar_servicios'))   {{-- ya parte de servicios-web --}} @endif

@if ($contenido->bool('inicio_mostrar_productos'))
    ... tarjetas con foto/foto, marca, precios (reales, con hover) ...
@endif

@if ($contenido->bool('inicio_mostrar_proyecto'))
    ... sección último proyecto (estática, conservada) ...
@endif
```

La sección de marcas del inicio pasa de iterar strings (`{{ $marca }}`) a modelos reales (`{{ $marca->nombre }}`).

### `PaginaInicioController::productos()`

```php
public function productos(): View
{
    $contenido = app(ContenidoWebService::class);

    $categorias_productos = Categoria::whereHas('productos', fn ($q) => $q->where('activo', true))
        ->withCount(['productos as productos_count' => fn ($q) => $q->where('activo', true)])
        ->orderBy('nombre')
        ->get()
        ->map(fn (Categoria $c) => [
            'slug'        => $c->slug,
            'nombre'      => $c->nombre,
            'descripcion' => $c->descripcion,
            'icono'       => $this->iconoParaCategoria($c->slug), // map slug→lavado/ceramico/...
            'imagen'      => null,
        ])
        ->values();

    return view('publica.productos', compact('contenido', 'categorias_productos', 'empresa'));
}
```

`iconoParaCategoria()` asigna el ícono del partial `promo-categoria-producto` según palabras del slug (lavado→lavado, ceram→ceramico, limpi→limpiadores, carroceria/pintura→carroceria, default→default), para que el mosaico renderice con datos reales sin añadir columnas a `categorias`.

### `publica/productos.blade.php`

- Envolver la sección del mosaico con `@if ($contenido->bool('productos_mostrar_mosaico'))`.
- Título/intro desde `$contenido->text('productos_titulo'/'productos_intro')`.
- Layout dinámico 2-1-2 por grupos de 5:

```blade
@php
    $cols = ['izq' => [], 'centro' => [], 'der' => []];
    foreach ($categorias_productos as $i => $cat) {
        $pos = $i % 5;
        if ($pos === 0 || $pos === 1)      $cols['izq'][] = $cat;
        elseif ($pos === 2)                $cols['centro'][] = $cat;
        else                               $cols['der'][] = $cat;
    }
@endphp
```

- Los tiles (ya mapeados a arrays con `slug/nombre/descripcion/icono/imagen`) se renderizan en sus columnas alternando dimensiones `lg:flex-[1.15]` / `lg:flex-1`, conservando el CTA "VER MÁS" y enlazando a `route('publica.productos.categoria', ['categoria' => $cat['slug']])`.
- Vacío → `@if (! $categorias_productos->isNotEmpty())` → "Aún no hay categorías de productos disponibles."
- `promo-categoria-producto.blade.php`: `href="#"` se reemplaza por la ruta real y se conservan gradientes (FUTURO de imagen real).

### `PaginaInicioController::marcas()`

```php
$marcas = app(ContenidoWebService::class)->marcasWeb();
```

La vista `publica/marcas.blade.php` itera `{{ $marca->nombre }}` y usa `marcas_titulo`/`marcas_intro` en el encabezado (h2 + p de la sección `logo-list`).

### `PaginaInicioController::servicios()` — solo cabecera

Consume `servicios_titulo`/`servicios_intro` (ya especificado en `servicios-web`).

---

## Propiedades de Corrección

### Propiedad 1: Fallback de defaults sin BD

*Para cualquier* clave definida en `ContenidoWebService::DEFAULTS` y una tabla `contenido_web` vacía, `bool()`/`text()`/`json()` retornan exactamente el default definido, y `marcasWeb()` retorna todas las marcas ordenadas por nombre.

**Valida:** Requisitos 1.3, 1.4, 1.5

---

### Propiedad 2: Persistencia upsert sin duplicados

*Para cualquier* conjunto de claves enviadas en `PUT /contenido-web`, tras el guardado existe exactamente **una** fila por clave con el valor correcto (booleans como `'1'`/`'0'`, curaduría como JSON), y una segunda petición con los mismos valores no altera el resultado (idempotencia).

**Valida:** Requisitos 3.1, 3.2

---

### Propiedad 3: Toggle apaga la sección en la web

*Para cualquier* clave de sección (`inicio_mostrar_*`, `productos_mostrar_mosaico`) con valor `false` en BD, la página pública correspondiente NO renderiza esa sección; con `true` (o clave ausente) sí la renderiza.

**Valida:** Requisitos 5.1-5.4, 5.8, 6.1

---

### Propiedad 4: Curaduría de marcas respeta orden y filtro

*Para cualquier* JSON `marcas_web` válido (`[{marca_id, orden}]` con ids existentes), `marcasWeb()` devuelve exactamente esas marcas ordenadas por `orden`, nunca marcas no curadas; y con JSON vacío devuelve todas (fallback). La página `/nuestras-marcas` muestra al menos una marca siempre.

**Valida:** Requisitos 5.5, 7.1, 7.2, 7.3, 7.5

---

### Propiedad 5: Mosaico usa solo categorías con productos activos

*Para cualquier* conjunto de categorías y productos con estados `activo` arbitrarios, los tiles del mosaico corresponden exactamente a las categorías con ≥1 producto activo, con su contador de activos, y cada tile enlaza a `publica.productos.categoria` con su slug.

**Valida:** Requisitos 6.2, 6.3, 6.5

---

### Propiedad 6: Panel protegido por permiso

*Para cualquier* usuario autenticado sin `acceso-contenido-web`, GET y PUT `/contenido-web` retornan HTTP 403 y no modifican BD; sin autenticación redirigen a `/login`.

**Valida:** Requisitos 4.2, 4.3

---

## Manejo de Errores

- **Validación**: `productos_titulo`/`productos_intro`/`servicios_*`/`marcas_*` → `nullable|string|max:120`; `marcas_orden` → `nullable|integer|min:0`. Error → redirect back con `$errors`, patrón estándar de inputs.
- **Sin BD (fallback)**: nunca error; las páginas usan defaults.
- **Cloudinary**: no aplica en este módulo (curaduría/textos). Las imágenes de entidad pertenecen a sus CRUDs.
- **Acceso**: middleware `permission:*` → 403; `auth` → `/login`.

---

## Estrategia de Testing

### Tests de ejemplo

**`tests/Feature/ContenidoWebTest.php`**
- GET `/contenido-web` retorna 200 con la vista correcta (usuario con permiso).
- PUT persiste: `inicio_mostrar_marcas='0'` → página de inicio no renderiza la sección; `productos_titulo` aparece en `/nuestros-productos`.
- Upsert: segunda petición no duplica filas.
- Registra acción de auditoría (`registros_auditoria`).
- 403 sin permiso para GET y PUT (sin cambios en BD).
- La marca curada aparece en `/nuestras-marcas` en orden, y las no curadas no aparecen.
- Estado vacío del mosaico cuando no hay categorías con productos activos.

**`tests/Feature/PublicaMarcasTest.php` / `PublicaProductosTest.php`** (actualizar)
- `/nuestras-marcas` desde BD (curaduría y fallback).
- `/nuestros-productos` → tiles reales con slug enlazando a la colección.

### Property tests

**`tests/Feature/ContenidoWebPropertiesTest.php`** — 100+ iteraciones, tag `// Feature: contenido-web, Property {N}: <descripción>`:
- **P1**: claves ausentes → defaults exactos.
- **P2**: sets de claves aleatorios → 1 fila por clave, idempotencia.
- **P4**: curadurías aleatorias → orden y filtrado correctos, fallback vacío.
- **P5**: estados activo aleatorios en productos → mosaico exacto y enlaces correctos.

### JS property tests

`tests/js/contenido-web/curaduria.property.test.js` (fast-check) — la parti de reparto 2-1-2 pura:
- El reparto de `n` categorías genera columna izquierda/centro/derecha sin perder ni duplicar categorías (`abord` no vacío preserva el multiset).

**Cobertura mínima esperada**

| Área | Tipo | Archivo |
|---|---|---|
| Panel CRUD de contenidos | Feature | `ContenidoWebTest.php` |
| Toggles → páginas públicas | Feature | `ContenidoWebTest.php` + `Publica*Test.php` |
| Curaduría de marcas | Feature | `ContenidoWebTest.php` + `PublicaMarcasTest.php` |
| Mosaico real | Feature | `PublicaProductosTest.php` |
| Propiedades display/mosaico | Property | `ContenidoWebPropertiesTest.php` |
| Reparto de columnas | JS property | `tests/js/contenido-web/curaduria.property.test.js` |

---

## Comandos de verificación

- `vendor/bin/pint`
- `php artisan test`
- `npm run test`
- `npm run build`
- `php artisan migrate` (BD local de desarrollo)

**Después de la implementación**: `php artisan migrate:fresh --seed` queda a criterio del usuario para validar la web con datos demo.