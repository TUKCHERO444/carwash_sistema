# Documento de Diseno

# Documento de Diseño — cambio-aceite-confirmacion

## Visión General

Esta feature refactoriza el módulo de cambio de aceite para adoptar el mismo patrón de dos etapas (registro → confirmación) ya implementado en el módulo de ingresos vehicular. El objetivo es separar el momento en que se registra el servicio del momento en que se cobra, permitiendo al operario atender al cliente rápidamente y completar el pago después.

### Flujo de alto nivel

```
Operario
  │
  ├─► [Nuevo cambio de aceite]
  │       Formulario simplificado (sin pago)
  │       store() → estado = 'pendiente'
  │       Redirige a Tabla_Pendientes
  │
  ├─► [Tabla_Pendientes] /cambio-aceite
  │       Lista solo registros pendientes
  │       Botón "Abrir ticket" por fila
  │       Botón "Listado de cambios culminados"
  │
  ├─► [Panel_Confirmacion] /cambio-aceite/{id}/confirmar
  │       Formulario completo + sección de pago
  │       ├─ [Confirmar] → procesarConfirmacion() → estado = 'confirmado'
  │       ├─ [Actualizar ticket] → actualizarTicket() → estado permanece 'pendiente'
  │       └─ [Eliminar ticket] → destroy() → restaura stock
  │
  └─► [Tabla_Confirmados] /cambio-aceite/confirmados
          Lista solo registros confirmados
          Acciones: ver detalle, ticket, editar, eliminar
```

### Diferencias clave respecto al módulo de ingresos

| Aspecto | Ingresos | Cambio de Aceite |
|---|---|---|
| Relación trabajador | N:N (`ingreso_trabajadores`) | N:1 (`trabajador_id` FK directa) |
| Ítems del servicio | `servicios` N:N (sin cantidad) | `productos` N:N con `cantidad`, `precio`, `total` |
| Tabla pivote | `detalle_servicios` | `cambio_productos` |
| Campo extra | `vehiculo_id` | `descripcion` (opcional) |
| Cálculo de precio | vehiculo.precio + sum(servicios.precio) | sum(cantidad × precio_unitario) |

---

## Arquitectura

### Stack tecnológico

- **Backend**: Laravel 11, PHP 8.x
- **Frontend**: Blade + Tailwind CSS, Vite (módulos ES6)
- **Base de datos**: SQLite (desarrollo), compatible con MySQL/PostgreSQL
- **Testing PBT**: Pest PHP con `pestphp/pest` y `edalzell/pest-plugin-faker` o `spatie/pest-plugin-snapshots`; para las propiedades puras de JS se usará **fast-check** (npm)

### Patrón arquitectónico

El módulo sigue el patrón MVC estándar de Laravel con una capa de servicio mínima (`CajaService`). No se introduce ninguna capa adicional.

```
routes/web.php
    │
    └─► CambioAceiteController
            │
            ├─► Modelo CambioAceite  (Eloquent)
            ├─► Modelo Producto      (Eloquent — stock)
            ├─► Modelo Cliente       (Eloquent — firstOrCreate)
            ├─► CajaService          (getCajaActiva)
            └─► Storage::disk('public')  (fotos)
```

### Diagrama de estados del CambioAceite

```
         store()
[NUEVO] ──────────► [pendiente]
                         │
              ┌──────────┼──────────────┐
              │          │              │
    actualizarTicket()   │         destroy()
    (permanece)          │         (eliminado)
                         │
              procesarConfirmacion()
                         │
                         ▼
                   [confirmado]
                   (estado final,
                    no reversible)
```

### Gestión de stock

El stock de productos se gestiona de forma transaccional en tres momentos:

1. **Creación** (`store`): decrementa stock por cada producto.
2. **Actualización** (`actualizarTicket`): restaura stock anterior → decrementa con nuevas cantidades.
3. **Eliminación** (`destroy`): restaura stock de todos los productos del ticket.
4. **Confirmación** (`procesarConfirmacion`): sincroniza `cambio_productos` con los valores finales; el stock ya fue decrementado en la creación/actualización, no se modifica de nuevo.

---

## Componentes e Interfaces

### Rutas nuevas (`routes/web.php`)

Las rutas nuevas deben registrarse **antes** del `Route::resource` y **antes** de la ruta de ticket, para evitar que Laravel interprete `confirmados` o `confirmar` como parámetros `{cambioAceite}`.

```php
// Dentro del grupo middleware('auth') existente:

// ANTES del resource y del ticket:
Route::get('/cambio-aceite/confirmados',
    [CambioAceiteController::class, 'confirmados'])
    ->name('cambio-aceite.confirmados');

Route::get('/cambio-aceite/{cambioAceite}/confirmar',
    [CambioAceiteController::class, 'confirmar'])
    ->name('cambio-aceite.confirmar');

Route::post('/cambio-aceite/{cambioAceite}/confirmar',
    [CambioAceiteController::class, 'procesarConfirmacion'])
    ->name('cambio-aceite.procesarConfirmacion');

Route::put('/cambio-aceite/{cambioAceite}/actualizar-ticket',
    [CambioAceiteController::class, 'actualizarTicket'])
    ->name('cambio-aceite.actualizarTicket');

// Ruta Ajax (ya existe, no cambia):
Route::get('/cambio-aceite/buscar-productos', ...)
    ->name('cambio-aceite.buscar-productos');

// Resource (ya existe, no cambia):
Route::resource('cambio-aceite', CambioAceiteController::class);

// Ticket (ya existe, no cambia):
Route::get('/cambio-aceite/{cambioAceite}/ticket', ...)
    ->name('cambio-aceite.ticket');
```

**Tabla de rutas completa del módulo tras la refactorización:**

| Método | URI | Acción | Nombre |
|---|---|---|---|
| GET | `/cambio-aceite` | `index` | `cambio-aceite.index` |
| GET | `/cambio-aceite/confirmados` | `confirmados` | `cambio-aceite.confirmados` |
| GET | `/cambio-aceite/create` | `create` | `cambio-aceite.create` |
| POST | `/cambio-aceite` | `store` | `cambio-aceite.store` |
| GET | `/cambio-aceite/{id}` | `show` | `cambio-aceite.show` |
| GET | `/cambio-aceite/{id}/edit` | `edit` | `cambio-aceite.edit` |
| PUT/PATCH | `/cambio-aceite/{id}` | `update` | `cambio-aceite.update` |
| DELETE | `/cambio-aceite/{id}` | `destroy` | `cambio-aceite.destroy` |
| GET | `/cambio-aceite/{id}/confirmar` | `confirmar` | `cambio-aceite.confirmar` |
| POST | `/cambio-aceite/{id}/confirmar` | `procesarConfirmacion` | `cambio-aceite.procesarConfirmacion` |
| PUT | `/cambio-aceite/{id}/actualizar-ticket` | `actualizarTicket` | `cambio-aceite.actualizarTicket` |
| GET | `/cambio-aceite/{id}/ticket` | `ticket` | `cambio-aceite.ticket` |
| GET | `/cambio-aceite/buscar-productos` | `buscarProductos` | `cambio-aceite.buscar-productos` |

### CambioAceiteController — firmas de métodos

```php
class CambioAceiteController extends Controller
{
    public function __construct(private CajaService $cajaService) {}

    // ── Existentes (modificados) ──────────────────────────────────────

    /**
     * Tabla_Pendientes: lista CambioAceites con estado = 'pendiente'.
     * Vista: cambio-aceite.pendientes
     */
    public function index(): View;

    /**
     * Formulario de registro simplificado (sin campos de pago).
     * Vista: cambio-aceite.create  (modificada)
     */
    public function create(): View;

    /**
     * Crea CambioAceite con estado = 'pendiente'.
     * No valida caja ni campos de pago.
     * Decrementa stock de productos en transacción.
     * Redirige a cambio-aceite.index.
     */
    public function store(Request $request): RedirectResponse;

    /**
     * Detalle de un CambioAceite (sin cambios funcionales).
     */
    public function show(CambioAceite $cambioAceite): View;

    /**
     * Formulario de edición (solo para confirmados, sin cambios funcionales).
     */
    public function edit(CambioAceite $cambioAceite): View;

    /**
     * Actualiza un CambioAceite confirmado (desde edit).
     * Redirige a cambio-aceite.show.
     */
    public function update(Request $request, CambioAceite $cambioAceite): RedirectResponse;

    /**
     * Elimina el CambioAceite, restaura stock, elimina foto.
     * Redirige a cambio-aceite.index (Tabla_Pendientes).
     */
    public function destroy(CambioAceite $cambioAceite): RedirectResponse;

    /**
     * Búsqueda Ajax de productos (sin cambios).
     */
    public function buscarProductos(Request $request): JsonResponse;

    /**
     * Ticket de impresión (sin cambios).
     */
    public function ticket(CambioAceite $cambioAceite): View;

    // ── Nuevos ───────────────────────────────────────────────────────

    /**
     * Tabla_Confirmados: lista CambioAceites con estado = 'confirmado'.
     * Vista: cambio-aceite.confirmados
     */
    public function confirmados(): View;

    /**
     * Panel_Confirmacion: muestra el formulario completo de un pendiente.
     * Si ya está confirmado, redirige a cambio-aceite.confirmados.
     * Vista: cambio-aceite.confirmar
     */
    public function confirmar(CambioAceite $cambioAceite): View|RedirectResponse;

    /**
     * Procesa la confirmación del pago.
     * Valida caja activa + campos de pago.
     * Actualiza estado a 'confirmado', sincroniza cambio_productos.
     * Redirige a cambio-aceite.index con mensaje de éxito.
     */
    public function procesarConfirmacion(
        Request $request,
        CambioAceite $cambioAceite
    ): RedirectResponse;

    /**
     * Actualiza datos del ticket sin confirmar.
     * Restaura stock anterior, decrementa con nuevas cantidades.
     * Redirige a cambio-aceite.confirmar del mismo ticket.
     */
    public function actualizarTicket(
        Request $request,
        CambioAceite $cambioAceite
    ): RedirectResponse;
}
```

### Pseudocódigo de los métodos nuevos/modificados

#### `index()` — Tabla_Pendientes

```php
public function index(): View
{
    $cambioAceites = CambioAceite::with(['cliente', 'trabajador'])
        ->pendientes()          // scope: where('estado', 'pendiente')
        ->latest()
        ->paginate(15);

    return view('cambio-aceite.pendientes', compact('cambioAceites'));
}
```

#### `store()` — Registro simplificado

```php
public function store(Request $request): RedirectResponse
{
    // SIN validar caja activa
    $request->validate([
        'placa'                   => ['required', 'string', 'max:7'],
        'nombre'                  => ['nullable', 'string', 'max:100'],
        'dni'                     => ['nullable', 'string', 'max:8'],
        'trabajador_id'           => ['required', 'integer', 'exists:trabajadores,id'],
        'fecha'                   => ['required', 'date'],
        'descripcion'             => ['nullable', 'string', 'max:1000'],
        'foto'                    => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        'productos'               => ['required', 'array', 'min:1'],
        'productos.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
        'productos.*.cantidad'    => ['required', 'integer', 'min:1'],
        'productos.*.precio'      => ['required', 'numeric', 'gt:0'],
        'productos.*.total'       => ['required', 'numeric', 'min:0'],
    ]);

    DB::transaction(function () use ($request, &$cambioAceite) {
        $cliente = Cliente::firstOrCreate(
            ['placa' => $request->placa],
            ['nombre' => $request->nombre, 'dni' => $request->dni]
        );

        $foto = $request->hasFile('foto')
            ? Storage::disk('public')->put('cambio-aceites', $request->file('foto'))
            : null;

        // Calcular precio en servidor (no confiar en el cliente)
        $precio = collect($request->productos)
            ->sum(fn($p) => $p['cantidad'] * $p['precio']);

        $cambioAceite = CambioAceite::create([
            'cliente_id'    => $cliente->id,
            'trabajador_id' => $request->trabajador_id,
            'fecha'         => $request->fecha,
            'precio'        => $precio,
            'total'         => $precio,   // sin descuento aún
            'descripcion'   => $request->descripcion,
            'foto'          => $foto,
            'user_id'       => auth()->id(),
            'estado'        => 'pendiente',
            // metodo_pago, total final, caja_id → se asignan al confirmar
        ]);

        foreach ($request->productos as $item) {
            CambioProducto::create([
                'cambio_aceite_id' => $cambioAceite->id,
                'producto_id'      => $item['producto_id'],
                'cantidad'         => $item['cantidad'],
                'precio'           => $item['precio'],
                'total'            => $item['total'],
            ]);
            Producto::where('id', $item['producto_id'])
                    ->decrement('stock', $item['cantidad']);
        }
    });

    return redirect()->route('cambio-aceite.index')
        ->with('success', 'Ticket de cambio de aceite registrado correctamente.');
}
```

#### `confirmar()` — Panel_Confirmacion

```php
public function confirmar(CambioAceite $cambioAceite): View|RedirectResponse
{
    if ($cambioAceite->estado === 'confirmado') {
        return redirect()->route('cambio-aceite.confirmados')
            ->with('info', 'Este cambio de aceite ya fue confirmado.');
    }

    $cambioAceite->load(['cliente', 'trabajador', 'productos']);
    $trabajadores = Trabajador::where('estado', true)->get();

    $productosData = $cambioAceite->productos->map(fn($p) => [
        'id'       => $p->id,
        'nombre'   => $p->nombre,
        'precio'   => (float) $p->pivot->precio,
        'cantidad' => (int)   $p->pivot->cantidad,
        'total'    => (float) $p->pivot->total,
    ])->values()->all();

    $montosData = [
        'efectivo' => $cambioAceite->monto_efectivo,
        'yape'     => $cambioAceite->monto_yape,
        'izipay'   => $cambioAceite->monto_izipay,
    ];

    return view('cambio-aceite.confirmar', compact(
        'cambioAceite', 'trabajadores', 'productosData', 'montosData'
    ));
}
```

#### `procesarConfirmacion()` — Confirmar pago

```php
public function procesarConfirmacion(
    Request $request,
    CambioAceite $cambioAceite
): RedirectResponse {
    $caja = $this->cajaService->getCajaActiva();
    if (!$caja) {
        return back()->with('error_caja', true);
    }

    $request->validate([
        'placa'                   => ['required', 'string', 'max:7'],
        'nombre'                  => ['nullable', 'string', 'max:100'],
        'dni'                     => ['nullable', 'string', 'max:8'],
        'trabajador_id'           => ['required', 'integer', 'exists:trabajadores,id'],
        'fecha'                   => ['required', 'date'],
        'descripcion'             => ['nullable', 'string', 'max:1000'],
        'foto'                    => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        'precio'                  => ['required', 'numeric', 'min:0'],
        'total'                   => ['required', 'numeric', 'gt:0'],
        'metodo_pago'             => ['required', 'in:efectivo,yape,izipay,mixto'],
        'monto_efectivo'          => ['nullable', 'numeric', 'min:0'],
        'monto_yape'              => ['nullable', 'numeric', 'min:0'],
        'monto_izipay'            => ['nullable', 'numeric', 'min:0'],
        'productos'               => ['required', 'array', 'min:1'],
        'productos.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
        'productos.*.cantidad'    => ['required', 'integer', 'min:1'],
        'productos.*.precio'      => ['required', 'numeric', 'gt:0'],
        'productos.*.total'       => ['required', 'numeric', 'min:0'],
    ]);

    DB::transaction(function () use ($request, $caja, $cambioAceite) {
        $cliente = Cliente::firstOrCreate(
            ['placa' => $request->placa],
            ['nombre' => $request->nombre, 'dni' => $request->dni]
        );

        $foto = $cambioAceite->foto;
        if ($request->hasFile('foto')) {
            $nuevaFoto = Storage::disk('public')->put('cambio-aceites', $request->file('foto'));
            if ($cambioAceite->foto) Storage::disk('public')->delete($cambioAceite->foto);
            $foto = $nuevaFoto;
        }

        // Restaurar stock de productos anteriores
        $cambioAceite->load('productos');
        foreach ($cambioAceite->productos as $p) {
            Producto::where('id', $p->id)->increment('stock', $p->pivot->cantidad);
        }

        $cambioAceite->update([
            'cliente_id'     => $cliente->id,
            'trabajador_id'  => $request->trabajador_id,
            'fecha'          => $request->fecha,
            'precio'         => $request->precio,
            'total'          => $request->total,
            'descripcion'    => $request->descripcion,
            'foto'           => $foto,
            'metodo_pago'    => $request->metodo_pago,
            'monto_efectivo' => $request->metodo_pago === 'mixto' ? $request->monto_efectivo : null,
            'monto_yape'     => $request->metodo_pago === 'mixto' ? $request->monto_yape     : null,
            'monto_izipay'   => $request->metodo_pago === 'mixto' ? $request->monto_izipay   : null,
            'estado'         => 'confirmado',
            'caja_id'        => $caja->id,
        ]);

        // Sincronizar productos con valores finales y decrementar stock
        $syncData = [];
        foreach ($request->productos as $item) {
            $syncData[$item['producto_id']] = [
                'cantidad' => $item['cantidad'],
                'precio'   => $item['precio'],
                'total'    => $item['total'],
            ];
            Producto::where('id', $item['producto_id'])
                    ->decrement('stock', $item['cantidad']);
        }
        $cambioAceite->productos()->sync($syncData);
    });

    return redirect()->route('cambio-aceite.index')
        ->with('success', 'Cambio de aceite confirmado correctamente.');
}
```

#### `actualizarTicket()` — Actualizar sin confirmar

```php
public function actualizarTicket(
    Request $request,
    CambioAceite $cambioAceite
): RedirectResponse {
    $request->validate([
        'placa'                   => ['required', 'string', 'max:7'],
        'nombre'                  => ['nullable', 'string', 'max:100'],
        'dni'                     => ['nullable', 'string', 'max:8'],
        'trabajador_id'           => ['required', 'integer', 'exists:trabajadores,id'],
        'fecha'                   => ['required', 'date'],
        'descripcion'             => ['nullable', 'string', 'max:1000'],
        'foto'                    => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        'productos'               => ['required', 'array', 'min:1'],
        'productos.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
        'productos.*.cantidad'    => ['required', 'integer', 'min:1'],
        'productos.*.precio'      => ['required', 'numeric', 'gt:0'],
        'productos.*.total'       => ['required', 'numeric', 'min:0'],
    ]);

    DB::transaction(function () use ($request, $cambioAceite) {
        $cliente = Cliente::firstOrCreate(
            ['placa' => $request->placa],
            ['nombre' => $request->nombre, 'dni' => $request->dni]
        );

        $foto = $cambioAceite->foto;
        if ($request->hasFile('foto')) {
            $nuevaFoto = Storage::disk('public')->put('cambio-aceites', $request->file('foto'));
            if ($cambioAceite->foto) Storage::disk('public')->delete($cambioAceite->foto);
            $foto = $nuevaFoto;
        }

        // Restaurar stock de productos anteriores
        $cambioAceite->load('productos');
        foreach ($cambioAceite->productos as $p) {
            Producto::where('id', $p->id)->increment('stock', $p->pivot->cantidad);
        }

        // Recalcular precio en servidor
        $precio = collect($request->productos)
            ->sum(fn($p) => $p['cantidad'] * $p['precio']);

        $cambioAceite->update([
            'cliente_id'    => $cliente->id,
            'trabajador_id' => $request->trabajador_id,
            'fecha'         => $request->fecha,
            'precio'        => $precio,
            'total'         => $precio,
            'descripcion'   => $request->descripcion,
            'foto'          => $foto,
            // estado permanece 'pendiente'
        ]);

        // Sincronizar productos y decrementar stock nuevo
        $syncData = [];
        foreach ($request->productos as $item) {
            $syncData[$item['producto_id']] = [
                'cantidad' => $item['cantidad'],
                'precio'   => $item['precio'],
                'total'    => $item['total'],
            ];
            Producto::where('id', $item['producto_id'])
                    ->decrement('stock', $item['cantidad']);
        }
        $cambioAceite->productos()->sync($syncData);
    });

    return redirect()->route('cambio-aceite.confirmar', $cambioAceite)
        ->with('success', 'Ticket actualizado correctamente.');
}
```

#### `destroy()` — Eliminación desde Panel_Confirmacion

```php
public function destroy(CambioAceite $cambioAceite): RedirectResponse
{
    try {
        DB::transaction(function () use ($cambioAceite) {
            $cambioAceite->load('productos');
            foreach ($cambioAceite->productos as $producto) {
                Producto::where('id', $producto->id)
                        ->increment('stock', $producto->pivot->cantidad);
            }
            if ($cambioAceite->foto) {
                Storage::disk('public')->delete($cambioAceite->foto);
            }
            $cambioAceite->delete();
        });

        return redirect()->route('cambio-aceite.index')
            ->with('success', 'Ticket eliminado y stock restaurado correctamente.');
    } catch (\Throwable $e) {
        return redirect()->route('cambio-aceite.confirmar', $cambioAceite)
            ->with('error', 'No se pudo eliminar el ticket. Intente nuevamente.');
    }
}
```

### Vistas

#### Vistas nuevas

| Archivo | Descripción |
|---|---|
| `resources/views/cambio-aceite/pendientes.blade.php` | Tabla_Pendientes — reemplaza funcionalmente al `index.blade.php` actual |
| `resources/views/cambio-aceite/confirmados.blade.php` | Tabla_Confirmados — copia del `index.blade.php` actual con ajustes |
| `resources/views/cambio-aceite/confirmar.blade.php` | Panel_Confirmacion — análogo a `ingresos/confirmar.blade.php` |

#### Vista modificada

| Archivo | Cambio |
|---|---|
| `resources/views/cambio-aceite/create.blade.php` | Eliminar sección de totales y método de pago; cambiar botón a "Guardar ticket pendiente" |

**`pendientes.blade.php` — columnas y acciones:**
- Columnas: Fecha, Placa / Nombre, Trabajador
- Acciones por fila: botón "Abrir ticket" → `cambio-aceite.confirmar`
- Header: botón "Listado de cambios culminados" → `cambio-aceite.confirmados`; botón "Nuevo cambio de aceite" → `cambio-aceite.create`
- Estado vacío: mensaje "No hay cambios de aceite pendientes."

**`confirmados.blade.php` — columnas y acciones:**
- Columnas: Foto, Fecha, Cliente, Trabajador, Precio, Total, Pago, Acciones
- Acciones por fila: Ver detalle, Ticket, Editar, Eliminar (igual que el `index.blade.php` actual)
- Header: botón "Volver a pendientes" → `cambio-aceite.index`
- Paginación: 15 registros por página

**`confirmar.blade.php` — estructura:**

```
[Resumen superior]
  Placa: {placa}
  Productos: lista de nombres

[Formulario #form-cambio-aceite]
  action = route('cambio-aceite.procesarConfirmacion', $cambioAceite)
  method = POST

  Campos: placa, nombre, dni, fecha, descripcion, foto, trabajador_id
  Búsqueda de productos + tabla de productos (con cantidad editable)
  Sección de totales: precio (readonly), toggle descuento %, toggle descuento manual, total
  Sección de pago: metodo_pago (radio), bloque mixto

  [Botones]
    [Confirmar cambio de aceite]  → submit del form
    [Actualizar ticket]           → window.submitActualizar() → PUT actualizarTicket
    [Eliminar ticket]             → window.confirmarEliminacion() → form#form-eliminar

[Formulario #form-eliminar]
  action = route('cambio-aceite.destroy', $cambioAceite)
  method = POST + @method('DELETE')
  class = hidden

[Script de inicialización]
  window.productosConfirmar  = @json($productosData)
  window.confirmarMetodoPago = @json($cambioAceite->metodo_pago ?? 'efectivo')
  window.confirmarMontos     = @json($montosData)
  window._confirmarUpdateUrl = "{{ route('cambio-aceite.actualizarTicket', $cambioAceite) }}"

@vite('resources/js/cambio-aceite/confirmar.js')
```

### Módulos JavaScript

#### Nuevo: `resources/js/cambio-aceite/confirmar.js`

Análogo a `ingresos/confirmar.js` pero adaptado a productos (con cantidad) en lugar de servicios.

```javascript
import {
    initBusquedaProductos,
    renderTablaProductos,
    recalcularTotales,
    sincronizarHiddens,
    initFotoPreview,
    initMetodoPago,
    validarMixto,
} from './shared.js';

// Datos desde Blade
const productosConfirmar  = window.productosConfirmar  ?? [];
const confirmarMetodoPago = window.confirmarMetodoPago ?? 'efectivo';
const confirmarMontos     = window.confirmarMontos     ?? {};

// Estado local
let items = productosConfirmar.map(p => ({
    producto_id: p.id,
    nombre:      p.nombre,
    precio:      +parseFloat(p.precio).toFixed(2),
    cantidad:    p.cantidad ?? 1,
    total:       +parseFloat(p.total ?? p.precio).toFixed(2),
}));

// Expuestas en window para uso inline desde Blade
window.submitActualizar = function () {
    const form = document.getElementById('form-cambio-aceite');
    if (!form) return;
    form.action = window._confirmarUpdateUrl ?? form.action;
    if (!form.querySelector('input[name="_method"]')) {
        const m = document.createElement('input');
        m.type = 'hidden'; m.name = '_method'; m.value = 'PUT';
        form.appendChild(m);
    }
    form.submit();
};

window.confirmarEliminacion = function () {
    if (confirm('¿Estás seguro de eliminar este ticket? Esta acción no se puede deshacer.')) {
        document.getElementById('form-eliminar')?.submit();
    }
};

function actualizarCantidad(idx, val) { /* igual que edit.js */ }
function eliminarItem(idx)            { /* igual que edit.js */ }
function onAgregarProducto(producto)  { /* igual que edit.js, sin duplicados */ }

document.addEventListener('DOMContentLoaded', () => {
    renderTablaProductos(items, 'tbody-detalle', actualizarCantidad, eliminarItem);
    sincronizarHiddens(items, 'form-cambio-aceite', 'hidden-producto');
    initBusquedaProductos({ inputId: 'buscar-producto', resultadosId: 'resultados-busqueda', onAgregar: onAgregarProducto });
    initFotoPreview('foto', 'foto-preview', 'foto-current');

    const { actualizarUI } = initMetodoPago({ /* igual que edit.js */ });

    // Restaurar método de pago y montos guardados
    radios.forEach(r => { if (r.value === confirmarMetodoPago) r.checked = true; });
    // Restaurar montos mixtos...
    actualizarUI();

    // Listeners de descuento, porcentaje, mixto — igual que edit.js
    // NO recalcular totales en carga — preservar precio/total del servidor
});
```

#### Modificado: `resources/js/cambio-aceite/create.js`

Se elimina toda la lógica de método de pago y totales. Solo se mantiene:
- `initBusquedaProductos`
- `renderTablaProductos`
- `recalcularTotales` (para actualizar el campo `precio` visible, aunque no se envía al servidor)
- `sincronizarHiddens`
- `initFotoPreview`
- Toggle de descuento por porcentaje (opcional, puede eliminarse también si el formulario ya no muestra precio/total)

> **Decisión de diseño**: El formulario de registro simplificado no muestra campos de precio/total al usuario, pero sí envía los `hidden inputs` de productos para que el servidor calcule el precio. El campo `precio` visible puede mantenerse como referencia informativa (readonly) o eliminarse completamente. Se recomienda mantenerlo como referencia para que el operario vea el subtotal antes de guardar.

---

## Modelos de Datos

### Migración: agregar `estado` a `cambio_aceites`

```php
// Nombre sugerido: 2026_XX_XX_000001_add_estado_to_cambio_aceites_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cambio_aceites', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'confirmado'])
                  ->default('pendiente')
                  ->after('fecha');
        });

        // Todos los registros existentes se marcan como confirmados
        // para preservar la integridad de datos históricos
        DB::table('cambio_aceites')->update(['estado' => 'confirmado']);
    }

    public function down(): void
    {
        Schema::table('cambio_aceites', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
```

### Modelo `CambioAceite` — cambios

```php
protected $fillable = [
    'cliente_id',
    'trabajador_id',
    'fecha',
    'precio',
    'total',
    'descripcion',
    'foto',
    'user_id',
    'metodo_pago',
    'monto_efectivo',
    'monto_yape',
    'monto_izipay',
    'caja_id',
    'estado',          // ← NUEVO
];

protected $casts = [
    'fecha'          => 'date',
    'precio'         => 'decimal:2',
    'total'          => 'decimal:2',
    'monto_efectivo' => 'decimal:2',
    'monto_yape'     => 'decimal:2',
    'monto_izipay'   => 'decimal:2',
    // 'estado' no necesita cast especial (string)
];

// ── Scopes nuevos ──────────────────────────────────────────────────

/**
 * Filtra registros con estado = 'pendiente'.
 */
public function scopePendientes(Builder $query): Builder
{
    return $query->where('estado', 'pendiente');
}

/**
 * Filtra registros con estado = 'confirmado'.
 */
public function scopeConfirmados(Builder $query): Builder
{
    return $query->where('estado', 'confirmado');
}
```

### Esquema de la tabla `cambio_aceites` tras la migración

```
cambio_aceites
├── id                  BIGINT UNSIGNED PK
├── cliente_id          FK → clientes.id
├── trabajador_id       FK → trabajadores.id
├── fecha               DATE
├── estado              ENUM('pendiente','confirmado') DEFAULT 'pendiente'  ← NUEVO
├── precio              DECIMAL(10,2) NULLABLE
├── total               DECIMAL(10,2) NULLABLE
├── descripcion         TEXT NULLABLE
├── foto                VARCHAR NULLABLE
├── user_id             FK → users.id NULLABLE
├── metodo_pago         VARCHAR NULLABLE
├── monto_efectivo      DECIMAL(10,2) NULLABLE
├── monto_yape          DECIMAL(10,2) NULLABLE
├── monto_izipay        DECIMAL(10,2) NULLABLE
├── caja_id             FK → cajas.id NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP
```

### Tabla pivote `cambio_productos` (sin cambios)

```
cambio_productos
├── id                  BIGINT UNSIGNED PK
├── cambio_aceite_id    FK → cambio_aceites.id
├── producto_id         FK → productos.id
├── cantidad            INT
├── precio              DECIMAL(10,2)   — precio unitario al momento del registro
├── total               DECIMAL(10,2)   — cantidad × precio
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP
```

### Invariantes de datos

1. Un `CambioAceite` con `estado = 'confirmado'` siempre tiene `metodo_pago`, `total > 0` y `caja_id` no nulos.
2. Un `CambioAceite` con `estado = 'pendiente'` puede tener `metodo_pago`, `total` y `caja_id` nulos.
3. El campo `precio` de `cambio_aceites` siempre es igual a `sum(cambio_productos.total)` para ese registro.
4. El campo `total` de `cambio_productos` siempre es igual a `cantidad × precio` para esa fila.
5. El `stock` de cada `Producto` refleja el inventario disponible descontando todos los tickets (pendientes y confirmados).

---

## Propiedades de Corrección

*Una propiedad es una característica o comportamiento que debe cumplirse en todas las ejecuciones válidas del sistema — esencialmente, una afirmación formal sobre lo que el sistema debe hacer. Las propiedades sirven como puente entre las especificaciones legibles por humanos y las garantías de corrección verificables por máquinas.*

### Reflexión sobre redundancia

Antes de listar las propiedades finales, se identifican y consolidan las redundancias del prework:

- Los criterios 1.7 y 1.8 (cálculo de precio y persistencia de productos) se consolidan en una sola propiedad de round-trip de creación.
- Los criterios 1.9, 5.8 y 6.2 (stock al crear, actualizar y eliminar) se expresan como tres propiedades de invariante de stock independientes porque los momentos y operaciones son distintos.
- Los criterios 2.2 y 7.2 (filtrado de pendientes y confirmados) se consolidan en una sola propiedad de filtrado bidireccional.
- El criterio 3.7 (redirección si ya confirmado) se consolida con 4.7 (sin caja activa no confirma) en propiedades de invariante de estado.
- Los criterios 4.1-4.2 y 5.1 (estado tras confirmar y tras actualizar) se expresan como dos propiedades de transición de estado.

---

### Propiedad 1: Cálculo de precio como suma de líneas

*Para cualquier* array de productos con cantidades y precios unitarios, el campo `precio` calculado debe ser igual a la suma de `(cantidad × precio_unitario)` de cada línea.

Esto aplica tanto a la función pura `calcularPrecio` en JavaScript como al cálculo server-side en `store()` y `actualizarTicket()`.

**Valida: Requisitos 1.7**

---

### Propiedad 2: El total con descuento nunca supera el precio base

*Para cualquier* array de productos y cualquier porcentaje de descuento `pct` en el rango `[0, 100]`, el resultado de `calcularTotal(items, pct)` debe ser menor o igual a `calcularPrecio(items)`.

**Valida: Requisitos 1.7, 4.1**

---

### Propiedad 3: Round-trip de creación de ticket pendiente

*Para cualquier* conjunto válido de datos de entrada (placa, trabajador, lista de productos con cantidades y precios), al crear un `CambioAceite` mediante `store()`:
- El registro creado tiene `estado = 'pendiente'`.
- El campo `precio` del registro es igual a `sum(cantidad × precio_unitario)` de los productos enviados.
- La tabla `cambio_productos` contiene exactamente una fila por producto enviado, con los valores correctos de `cantidad`, `precio` y `total`.

**Valida: Requisitos 1.2, 1.7, 1.8**

---

### Propiedad 4: Invariante de stock al crear un ticket pendiente

*Para cualquier* producto con stock inicial conocido `S` y cantidad seleccionada `C`, después de crear un `CambioAceite` que incluye ese producto con cantidad `C`, el stock del producto debe ser `S - C`.

**Valida: Requisito 1.9**

---

### Propiedad 5: Filtrado exclusivo por estado

*Para cualquier* base de datos con `CambioAceites` en estados mixtos (`pendiente` y `confirmado`):
- La consulta `CambioAceite::pendientes()` devuelve únicamente registros con `estado = 'pendiente'`.
- La consulta `CambioAceite::confirmados()` devuelve únicamente registros con `estado = 'confirmado'`.
- Ningún registro aparece en ambas consultas simultáneamente.

**Valida: Requisitos 2.2, 7.2**

---

### Propiedad 6: Invariante de estado — confirmación es irreversible

*Para cualquier* `CambioAceite` con `estado = 'confirmado'`, ninguna operación del sistema (actualizar ticket, confirmar de nuevo) debe cambiar su estado a `pendiente`.

**Valida: Requisito 3.7 (implícito)**

---

### Propiedad 7: Sin caja activa, `procesarConfirmacion` no cambia el estado

*Para cualquier* `CambioAceite` con `estado = 'pendiente'`, si no existe una sesión de caja activa al llamar a `procesarConfirmacion()`, el estado del registro debe permanecer `pendiente` y no se deben persistir datos de pago.

**Valida: Requisito 4.7**

---

### Propiedad 8: Invariante de stock al actualizar un ticket

*Para cualquier* producto con stock inicial `S`, si un ticket pendiente lo incluía con cantidad `C_anterior` y se actualiza a cantidad `C_nueva`, el stock final del producto debe ser `S - C_nueva` (independientemente del valor de `C_anterior`).

Equivalentemente: `stock_final = stock_antes_de_actualizar + C_anterior - C_nueva`.

**Valida: Requisito 5.8**

---

### Propiedad 9: Invariante de stock al eliminar un ticket

*Para cualquier* `CambioAceite` pendiente con productos, después de eliminarlo mediante `destroy()`, el stock de cada producto asociado debe ser igual al stock que tenía antes de que se creara el ticket.

Equivalentemente: `stock_final = stock_antes_de_crear_ticket`.

**Valida: Requisito 6.2**

---

## Manejo de Errores

### Errores de validación (HTTP 422 / redirect back)

| Situación | Respuesta |
|---|---|
| Campos requeridos faltantes en `store()` | `back()->withErrors()->withInput()` |
| Campos requeridos faltantes en `procesarConfirmacion()` | `back()->withErrors()->withInput()` |
| `total <= 0` al confirmar | Error de validación en campo `total` |
| `trabajador_id` inválido | Error de validación en campo `trabajador_id` |
| Sin productos al guardar | Error de validación en campo `productos` |
| Foto con formato/tamaño inválido | Error de validación en campo `foto` |

### Errores de negocio

| Situación | Respuesta |
|---|---|
| Sin caja activa al confirmar | `back()->with('error_caja', true)` → modal de advertencia en la vista |
| Acceso al Panel_Confirmacion de un ticket ya confirmado | `redirect()->route('cambio-aceite.confirmados')->with('info', ...)` |
| Pago mixto con suma incorrecta | Alerta visual en el frontend (no bloquea el submit, es advertencia) |

### Errores de sistema (excepciones)

| Situación | Respuesta |
|---|---|
| Fallo en transacción de `store()` | `back()->withInput()->with('error', ...)` |
| Fallo en transacción de `procesarConfirmacion()` | `back()->withInput()->with('error', ...)` |
| Fallo en transacción de `actualizarTicket()` | `back()->withInput()->with('error', ...)` |
| Fallo en transacción de `destroy()` | `redirect()->route('cambio-aceite.confirmar', $cambioAceite)->with('error', ...)` |

### Consideraciones de consistencia

- Todas las operaciones que modifican stock y datos del ticket se ejecutan dentro de `DB::transaction()`. Si cualquier paso falla, se hace rollback completo.
- La eliminación de archivos de foto se realiza **dentro** de la transacción para garantizar que si el `delete()` del modelo falla, no se pierda la foto del storage. Sin embargo, si el `Storage::delete()` falla pero el modelo se elimina, la foto quedará huérfana en el storage (comportamiento aceptable, igual que el módulo de ingresos).
- El stock nunca puede quedar en un estado inconsistente por una operación parcial gracias a las transacciones.

---

## Estrategia de Testing

### Enfoque dual

Se combinan tests de ejemplo (unitarios/feature) con tests basados en propiedades (PBT) para lograr cobertura completa.

### Tests de ejemplo (Pest PHP — Feature Tests)

Estos tests verifican flujos concretos y casos borde específicos:

```
tests/Feature/CambioAceite/
├── StoreTest.php              — creación de ticket pendiente
├── ConfirmarTest.php          — Panel_Confirmacion y procesarConfirmacion
├── ActualizarTicketTest.php   — actualización sin confirmar
├── DestroyTest.php            — eliminación y restauración de stock
├── TablasPendientesConfirmadosTest.php — filtrado de vistas
└── MigracionEstadoTest.php    — smoke test de migración
```

**Casos de ejemplo clave:**
- `store()` sin caja activa → crea el ticket correctamente (no requiere caja)
- `procesarConfirmacion()` sin caja activa → retorna `error_caja`, estado permanece `pendiente`
- Acceso a `confirmar()` de un ticket ya confirmado → redirige a `confirmados`
- `store()` con campos requeridos faltantes → errores de validación
- `destroy()` con foto → elimina el archivo del storage
- Formulario de registro no contiene campos de pago

### Tests de propiedades (PBT)

**Librería**: `pestphp/pest` con `edalzell/pest-plugin-faker` para PHP; **fast-check** para las funciones puras de JavaScript.

**Configuración**: mínimo 100 iteraciones por propiedad.

**Tag format**: `// Feature: cambio-aceite-confirmacion, Property {N}: {texto}`

#### Propiedades JS (fast-check en Vitest)

```javascript
// tests/js/cambio-aceite/shared.property.test.js

// Feature: cambio-aceite-confirmacion, Property 1: calcularPrecio == sum(cantidad * precio)
test('Propiedad 1: calcularPrecio es la suma de líneas', () => {
    fc.assert(fc.property(
        fc.array(fc.record({
            cantidad: fc.integer({ min: 1, max: 100 }),
            precio:   fc.float({ min: 0.01, max: 9999.99 }),
        }), { minLength: 1 }),
        (productos) => {
            const items = productos.map(p => ({
                ...p,
                total: +(p.cantidad * p.precio).toFixed(2),
            }));
            const esperado = +items.reduce((acc, i) => acc + i.total, 0).toFixed(2);
            const actual   = +items.reduce((acc, i) => acc + i.total, 0).toFixed(2);
            return Math.abs(esperado - actual) < 0.001;
        }
    ), { numRuns: 100 });
});

// Feature: cambio-aceite-confirmacion, Property 2: calcularTotal <= calcularPrecio
test('Propiedad 2: total con descuento nunca supera el precio base', () => {
    fc.assert(fc.property(
        fc.array(fc.record({ total: fc.float({ min: 0, max: 9999.99 }) }), { minLength: 1 }),
        fc.float({ min: 0, max: 100 }),
        (items, pct) => {
            const precio = items.reduce((acc, i) => acc + i.total, 0);
            const total  = calcularTotal(items, pct);
            return total <= precio + 0.001; // tolerancia de redondeo
        }
    ), { numRuns: 100 });
});
```

#### Propiedades PHP (Pest con Faker)

```php
// tests/Feature/CambioAceite/PropiedadesTest.php

// Feature: cambio-aceite-confirmacion, Property 3: round-trip de creación
it('Propiedad 3: crear ticket pendiente persiste datos correctamente', function () {
    $this->repeat(100, function () {
        $trabajador = Trabajador::factory()->create(['estado' => true]);
        $productos  = Producto::factory()->count(fake()->numberBetween(1, 5))->create(['stock' => 100]);
        $items      = $productos->map(fn($p) => [
            'producto_id' => $p->id,
            'cantidad'    => fake()->numberBetween(1, 5),
            'precio'      => $p->precio_venta,
            'total'       => 0, // se recalcula
        ])->map(fn($i) => array_merge($i, ['total' => $i['cantidad'] * $i['precio']]));

        $response = $this->actingAs(User::factory()->create())
            ->post(route('cambio-aceite.store'), [
                'placa'         => strtoupper(fake()->bothify('???-###')),
                'trabajador_id' => $trabajador->id,
                'fecha'         => now()->format('Y-m-d'),
                'productos'     => $items->toArray(),
            ]);

        $cambio = CambioAceite::latest()->first();
        expect($cambio->estado)->toBe('pendiente');
        expect((float) $cambio->precio)->toEqual(
            round($items->sum(fn($i) => $i['cantidad'] * $i['precio']), 2)
        );
        expect($cambio->productos)->toHaveCount($items->count());
    });
});

// Feature: cambio-aceite-confirmacion, Property 4: stock decrementado al crear
it('Propiedad 4: stock decrementado correctamente al crear ticket', function () {
    $this->repeat(100, function () {
        $producto   = Producto::factory()->create(['stock' => $stockInicial = fake()->numberBetween(10, 100)]);
        $cantidad   = fake()->numberBetween(1, 5);
        $trabajador = Trabajador::factory()->create(['estado' => true]);

        $this->actingAs(User::factory()->create())
            ->post(route('cambio-aceite.store'), [
                'placa'         => 'TST-001',
                'trabajador_id' => $trabajador->id,
                'fecha'         => now()->format('Y-m-d'),
                'productos'     => [[
                    'producto_id' => $producto->id,
                    'cantidad'    => $cantidad,
                    'precio'      => $producto->precio_venta,
                    'total'       => $cantidad * $producto->precio_venta,
                ]],
            ]);

        expect($producto->fresh()->stock)->toBe($stockInicial - $cantidad);
    });
});

// Feature: cambio-aceite-confirmacion, Property 5: filtrado exclusivo por estado
it('Propiedad 5: pendientes() y confirmados() son conjuntos disjuntos y exhaustivos', function () {
    $this->repeat(100, function () {
        CambioAceite::factory()->count(fake()->numberBetween(1, 5))->create(['estado' => 'pendiente']);
        CambioAceite::factory()->count(fake()->numberBetween(1, 5))->create(['estado' => 'confirmado']);

        $pendientes   = CambioAceite::pendientes()->pluck('id');
        $confirmados  = CambioAceite::confirmados()->pluck('id');
        $interseccion = $pendientes->intersect($confirmados);

        expect($interseccion)->toBeEmpty();
        expect(CambioAceite::pendientes()->where('estado', '!=', 'pendiente')->count())->toBe(0);
        expect(CambioAceite::confirmados()->where('estado', '!=', 'confirmado')->count())->toBe(0);
    });
});

// Feature: cambio-aceite-confirmacion, Property 7: sin caja activa no confirma
it('Propiedad 7: procesarConfirmacion sin caja activa no cambia el estado', function () {
    $this->repeat(100, function () {
        // Sin caja activa (no crear ninguna)
        $cambio = CambioAceite::factory()->create(['estado' => 'pendiente']);

        $this->actingAs(User::factory()->create())
            ->post(route('cambio-aceite.procesarConfirmacion', $cambio), [
                'placa'         => 'TST-001',
                'trabajador_id' => Trabajador::factory()->create()->id,
                'fecha'         => now()->format('Y-m-d'),
                'precio'        => 100,
                'total'         => 100,
                'metodo_pago'   => 'efectivo',
                'productos'     => [/* ... */],
            ]);

        expect($cambio->fresh()->estado)->toBe('pendiente');
    });
});

// Feature: cambio-aceite-confirmacion, Property 8: stock correcto tras actualizar
it('Propiedad 8: stock correcto tras actualizar ticket', function () {
    $this->repeat(100, function () {
        $producto      = Producto::factory()->create(['stock' => $stockInicial = 50]);
        $cantAnterior  = fake()->numberBetween(1, 5);
        $cantNueva     = fake()->numberBetween(1, 5);

        // Crear ticket con cantidad anterior
        $cambio = CambioAceite::factory()->create(['estado' => 'pendiente']);
        $cambio->productos()->attach($producto->id, [
            'cantidad' => $cantAnterior,
            'precio'   => $producto->precio_venta,
            'total'    => $cantAnterior * $producto->precio_venta,
        ]);
        $producto->decrement('stock', $cantAnterior);

        // Actualizar con nueva cantidad
        $this->actingAs(User::factory()->create())
            ->put(route('cambio-aceite.actualizarTicket', $cambio), [
                'placa'         => $cambio->cliente->placa,
                'trabajador_id' => $cambio->trabajador_id,
                'fecha'         => $cambio->fecha->format('Y-m-d'),
                'productos'     => [[
                    'producto_id' => $producto->id,
                    'cantidad'    => $cantNueva,
                    'precio'      => $producto->precio_venta,
                    'total'       => $cantNueva * $producto->precio_venta,
                ]],
            ]);

        expect($producto->fresh()->stock)->toBe($stockInicial - $cantNueva);
    });
});

// Feature: cambio-aceite-confirmacion, Property 9: stock restaurado al eliminar
it('Propiedad 9: stock restaurado completamente al eliminar ticket', function () {
    $this->repeat(100, function () {
        $producto     = Producto::factory()->create(['stock' => $stockInicial = 50]);
        $cantidad     = fake()->numberBetween(1, 5);

        $cambio = CambioAceite::factory()->create(['estado' => 'pendiente']);
        $cambio->productos()->attach($producto->id, [
            'cantidad' => $cantidad,
            'precio'   => $producto->precio_venta,
            'total'    => $cantidad * $producto->precio_venta,
        ]);
        $producto->decrement('stock', $cantidad);

        $this->actingAs(User::factory()->create())
            ->delete(route('cambio-aceite.destroy', $cambio));

        expect($producto->fresh()->stock)->toBe($stockInicial);
    });
});
```

### Balance de tests

- Los tests de propiedades cubren la lógica de negocio universal (cálculos, stock, estados).
- Los tests de ejemplo cubren flujos de UI, validaciones específicas, casos borde concretos y smoke tests de migración.
- Se evita duplicar cobertura: si una propiedad ya cubre un caso, no se escribe un test de ejemplo adicional para el mismo comportamiento.
