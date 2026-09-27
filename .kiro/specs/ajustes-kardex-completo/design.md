# .kiro/specs/ajustes-kardex-completo/design.md

# Diseño Técnico — Ajustes de Inventario + Kardex Completo

## 1. Arquitectura General

```
┌─────────────────────────────────────────────────────────────┐
│                      AJUSTE INVENTARIO                      │
│  Cabecera: AJU-0001, tipo, motivo, observaciones, user_id   │
└──────────────────────────┬──────────────────────────────────┘
                           │
         ┌─────────────────┼─────────────────┐
         ▼                 ▼                 ▼
    ┌─────────┐      ┌─────────┐      ┌─────────┐
    │ Línea 1 │      │ Línea 2 │      │ Línea N │
    │ prod_id │      │ prod_id │      │ prod_id │
    │ cantidad│      │ cantidad│      │ cantidad│
    │ stock_a │      │ stock_a │      │ stock_a │
    │ stock_d │      │ stock_d │      │ stock_d │
    └────┬────┘      └────┬────┘      └────┬────┘
         │                │                │
         ▼                ▼                ▼
    ┌─────────────────────────────────────────┐
    │       TRANSACCIÓN ÚNICA (DB)            │
    │  1. CREATE ajuste_inventario            │
    │  2. CREATE detalle_ajuste (bulk)        │
    │  3. FOR cada línea:                     │
    │       lockForUpdate(producto)           │
    │       UPDATE stock/inventario           │
    │       INSERT movimiento_kardex          │
    └─────────────────────────────────────────┘
```

---

## 2. Modelo de Datos

### 2.1 Migraciones

#### `create_ajustes_inventario_table.php`
```php
Schema::create('ajustes_inventario', function (Blueprint $table) {
    $table->id();
    $table->string('correlativo', 20)->unique(); // AJU-0001
    $table->enum('tipo', [
        'positivo', 'negativo', 'merma', 'daño', 'conteo_fisico'
    ]);
    $table->text('motivo')->nullable(); // obligatorio para merma/daño/conteo
    $table->text('observaciones')->nullable();
    $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
    $table->timestamps();

    $table->index('tipo');
    $table->index('created_at');
});

Schema::create('detalle_ajustes_inventario', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ajuste_id')
        ->constrained('ajustes_inventario')->onDelete('cascade');
    $table->foreignId('producto_id')
        ->constrained('productos')->onDelete('restrict');
    $table->integer('cantidad'); // firmado: + entrada, - salida
    $table->integer('stock_antes');
    $table->integer('stock_despues');
    $table->timestamps();

    $table->index('producto_id');
});
```

#### `add_costo_to_movimientos_kardex_table.php`
```php
Schema::table('movimientos_kardex', function (Blueprint $table) {
    $table->decimal('costo_unitario', 10, 2)->nullable()->after('cantidad');
    $table->decimal('costo_total', 12, 2)->nullable()->after('costo_unitario');
});
```

### 2.2 Modelos

#### `App\Models\AjusteInventario`
```php
class AjusteInventario extends Model
{
    protected $fillable = [
        'correlativo', 'tipo', 'motivo', 'observaciones', 'user_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const TIPOS = [
        'positivo' => 'Ajuste Positivo',
        'negativo' => 'Ajuste Negativo',
        'merma' => 'Merma',
        'daño' => 'Daño',
        'conteo_fisico' => 'Conteo Físico',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function detalles(): HasMany { return $this->hasMany(DetalleAjusteInventario::class); }
    public function productos(): BelongsToMany { 
        return $this->belongsToMany(Producto::class, 'detalle_ajustes_inventario')
            ->withPivot('cantidad', 'stock_antes', 'stock_despues')
            ->withTimestamps();
    }

    public function etiquetaTipo(): string {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function esEntrada(): bool {
        return in_array($this->tipo, ['positivo']);
    }

    public function esSalida(): bool {
        return in_array($this->tipo, ['negativo', 'merma', 'daño']);
    }

    public function esConteo(): bool {
        return $this->tipo === 'conteo_fisico';
    }
}
```

#### `App\Models\DetalleAjusteInventario`
```php
class DetalleAjusteInventario extends Model
{
    protected $table = 'detalle_ajustes_inventario';
    protected $fillable = [
        'ajuste_id', 'producto_id', 'cantidad', 'stock_antes', 'stock_despues'
    ];
    protected $casts = [
        'cantidad' => 'integer',
        'stock_antes' => 'integer',
        'stock_despues' => 'integer',
    ];

    public function ajuste(): BelongsTo { return $this->belongsTo(AjusteInventario::class); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
}
```

#### Actualizar `App\Models\MovimientoKardex`
```php
// Agregar a $fillable
'costo_unitario', 'costo_total'

protected $casts = [
    'costo_unitario' => 'decimal:2',
    'costo_total' => 'decimal:2',
    // ... existentes
];
```

---

## 3. Servicios

### 3.1 `App\Services\AjusteInventarioService`
```php
class AjusteInventarioService
{
    public function crear(array $data, User $user): AjusteInventario
    {
        return DB::transaction(function () use ($data, $user) {
            // 1. Validar y preparar líneas
            $lineas = $this->prepararLineas($data['lineas']);
            
            // 2. Crear cabecera
            $correlativo = $this->siguienteCorrelativo();
            $ajuste = AjusteInventario::create([
                'correlativo' => $correlativo,
                'tipo' => $data['tipo'],
                'motivo' => $data['motivo'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
                'user_id' => $user->id,
            ]);

            // 3. Procesar cada línea con lock
            foreach ($lineas as $linea) {
                $this->procesarLinea($ajuste, $linea);
            }

            // Auditoría
            app(AuditService::class)->anotarAccion('crear ajuste');

            return $ajuste->refresh();
        });
    }

    private function procesarLinea(AjusteInventario $ajuste, array $linea): void
    {
        $producto = Producto::lockForUpdate()->findOrFail($linea['producto_id']);

        if (! $producto->activo) {
            throw new \RuntimeException("El producto {$producto->nombre} está inactivo.");
        }

        $stockAntes = $producto->stock;
        $cantidad = (int) $linea['cantidad'];
        $stockDespues = $stockAntes + $cantidad;

        if ($stockDespues < 0) {
            throw new \RuntimeException("Stock insuficiente para {$producto->nombre}.");
        }

        // Determinar tipo de movimiento y costo
        [$tipoMov, $fuente, $costoUnitario] = $this->resolveMovimiento($ajuste->tipo, $cantidad);

        // Actualizar stock e inventario
        $producto->update([
            'stock' => $stockDespues,
            'inventario' => $stockDespues,
        ]);

        // Registrar Kardex
        app(KardexService::class)->registrar(
            $producto,
            $cantidad >= 0 ? 'entrada' : 'salida',
            $cantidad >= 0 ? $cantidad : -$cantidad,
            $stockAntes,
            $stockDespues,
            $fuente,
            $ajuste->correlativo,
            $costoUnitario,
        );

        // Detalle
        DetalleAjusteInventario::create([
            'ajuste_id' => $ajuste->id,
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'stock_antes' => $stockAntes,
            'stock_despues' => $stockDespues,
        ]);
    }

    private function resolveMovimiento(string $tipoAjuste, int $cantidad): array
    {
        $cpp = app(CostoPromedioService::class)->obtenerCpp($producto);
        
        return match ($tipoAjuste) {
            'positivo' => ['entrada', 'ajuste_positivo', $cpp],
            'negativo' => ['salida', 'ajuste_negativo', $cpp],
            'merma' => ['salida', 'merma', $cpp],
            'daño' => ['salida', 'daño', $cpp],
            'conteo_fisico' => [
                $cantidad >= 0 ? 'entrada' : 'salida',
                'conteo_fisico',
                $cpp
            ],
            default => throw new \InvalidArgumentException("Tipo de ajuste inválido: {$tipoAjuste}"),
        };
    }

    private function siguienteCorrelativo(): string
    {
        $max = AjusteInventario::where('correlativo', 'like', 'AJU-%')
            ->max('correlativo');
        $next = $max ? ((int) substr($max, 4)) + 1 : 1;
        return 'AJU-'.str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    private function prepararLineas(array $lineas): array
    {
        return collect($lineas)->map(function ($linea) {
            if (! empty($linea['conteo_fisico'])) {
                // Conteo físico: calcular delta
                $producto = Producto::findOrFail($linea['producto_id']);
                $delta = (int) $linea['conteo_fisico'] - $producto->stock;
                return [
                    'producto_id' => $linea['producto_id'],
                    'cantidad' => $delta,
                ];
            }
            return [
                'producto_id' => $linea['producto_id'],
                'cantidad' => (int) $linea['cantidad'],
            ];
        })->filter(fn ($l) => $l['cantidad'] !== 0)->values()->all();
    }
}
```

### 3.2 `App\Services\KardexService` — Métodos Actualizados
```php
public function registrar(
    Producto $producto,
    string $tipo, // 'entrada' | 'salida'
    int $cantidad, // siempre positivo
    int $stockAntes,
    int $stockDespues,
    string $fuente,
    string $origenId,
    float $costoUnitario = 0
): void
{
    DB::table('movimientos_kardex')->insert([
        'producto_id' => $producto->id,
        'tipo' => $tipo,
        'fuente' => $fuente,
        'origen_id' => $origenId,
        'cantidad' => $cantidad,
        'stock_antes' => $stockAntes,
        'stock_despues' => $stockDespues,
        'costo_unitario' => round($costoUnitario, 2),
        'costo_total' => round($cantidad * $costoUnitario, 2),
        'usuario_id' => auth()->id(),
        'fecha_movimiento' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

// Métodos existentes actualizados para aceptar costo
public function registrarEntrada(..., float $costoUnitario = 0): void { ... }
public function registrarSalida(..., float $costoUnitario = 0): void { ... }
public function registrarEntradaCompra(..., float $costoUnitario): void { ... }
```

### 3.3 `App\Services\CostoPromedioService`
```php
class CostoPromedioService
{
    public function obtenerCpp(Producto $producto): float
    {
        $cpp = (float) $producto->costo_promedio_ponderado;
        return $cpp > 0 ? $cpp : (float) $producto->precio_compra;
    }

    public function actualizarPorEntrada(Producto $producto, int $cantidad, float $costoUnitario): void
    {
        $stockActual = $producto->stock; // ya incluye la nueva cantidad
        $cppActual = (float) $producto->costo_promedio_ponderado;
        
        if ($cppActual <= 0) {
            $nuevoCpp = $costoUnitario;
        } else {
            $nuevoCpp = (($stockActual - $cantidad) * $cppActual + $cantidad * $costoUnitario) / $stockActual;
        }

        $producto->update(['costo_promedio_ponderado' => round($nuevoCpp, 4)]);
    }
}
```

---

## 4. Controladores

### 4.1 `App\Http\Controllers\AjusteInventarioController`
```php
class AjusteInventarioController extends Controller
{
    private const TIPOS = ['positivo', 'negativo', 'merma', 'daño', 'conteo_fisico'];

    private const MENSAJES = [
        'tipo.required' => 'Debe seleccionar un tipo de ajuste.',
        'tipo.in' => 'El tipo de ajuste no es válido.',
        'motivo.required' => 'El motivo es obligatorio para este tipo de ajuste.',
        'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
        'lineas.required' => 'Debe agregar al menos un producto.',
        'lineas.min' => 'Debe agregar al menos un producto.',
        'lineas.*.producto_id.required' => 'Cada línea debe tener un producto.',
        'lineas.*.producto_id.exists' => 'Uno de los productos no existe.',
        'lineas.*.cantidad.required' => 'Cada línea debe tener cantidad.',
        'lineas.*.conteo_fisico.required_without:cantidad' => 'Ingrese la cantidad contada.',
    ];

    public function index(Request $request): View
    {
        $ajustes = AjusteInventario::with('user', 'detalles.producto')
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->latest()->paginate(10)->withQueryString();

        return view('ajustes.index', compact('ajustes'));
    }

    public function create(): View
    {
        $productos = Producto::where('activo', 1)->orderBy('nombre')->get(['id', 'nombre', 'stock']);
        return view('ajustes.create', compact('productos'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->reglas(), self::MENSAJES);

        try {
            $ajuste = app(AjusteInventarioService::class)->crear($validated, $request->user());

            return redirect()->route('ajustes.show', $ajuste)
                ->with('success', 'Ajuste registrado correctamente.');
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(AjusteInventario $ajuste): View
    {
        $ajuste->load(['user', 'detalles.producto', 'detalles.kardex']);
        return view('ajustes.show', compact('ajuste'));
    }

    private function reglas(): array
    {
        $motivoObligatorio = in_array(request('tipo'), ['merma', 'daño', 'conteo_fisico']);

        return [
            'tipo' => ['required', 'in:' . implode(',', self::TIPOS)],
            'motivo' => $motivoObligatorio ? ['required', 'string', 'min:10'] : ['nullable', 'string', 'max:500'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'lineas.*.cantidad' => ['required_without:conteo_fisico', 'integer', 'min:-9999', 'max:9999', 'not_in:0'],
            'lineas.*.conteo_fisico' => ['required_without:cantidad', 'integer', 'min:0', 'max:99999'],
        ];
    }
}
```

---

## 5. Vistas

### 5.1 `resources/views/ajustes/create.blade.php`
- Select tipo con descripciones por tipo
- Tabla líneas dinámicas (JS igual a compras):
  - Select producto (solo activos, muestra stock actual)
  - Input cantidad (o campo `conteo_fisico` si tipo=conteo_fisico)
  - Badge tipo línea (entrada/salida)
  - Botón eliminar
- Totales calculados en JS (solo informativo)
- Botón "Agregar línea"
- `@vite('resources/js/ajustes/create.js')`

### 5.2 `resources/views/ajustes/show.blade.php`
- Cabecera: correlativo, tipo (badge), motivo, usuario, fecha
- Tabla líneas: producto, cantidad, stock_antes, stock_despues
- Panel "Movimientos Kardex Generados": tabla con fuente, tipo, cantidad, stock_antes/despues, costo_unitario, costo_total

### 5.3 `resources/js/ajustes/create.js`
- Lógica idéntica a `compras/create.js` adaptada:
  - `calcularTotal()` no aplica (no hay total monetario)
  - Validación: al menos 1 línea, cantidad ≠ 0
  - Campo dinámico: si tipo=conteo_fisico → muestra "Cantidad física" en lugar de "Cantidad"

---

## 6. Tests

### 6.1 `tests/Feature/AjusteInventarioTest.php`
```php
// Property 1: Ajuste positivo → stock + Kardex ENTRADA
// Property 2: Conteo físico delta -5 → stock -5, Kardex SALIDA conteo_fisico
// Property 3: Merma con stock insuficiente → error + rollback
// Property 4: Transacción atómica (fallo en línea 3 → 0 Kardex, 0 stock changes)
// Property 5: Costo ENTRADA = CPP; SALIDA = CPP
// Property 6: Producto inactivo → rechazo
// Property 7: Conteo físico delta 0 → rechazo
// Property 8: Conteo físico delta +3 → ENTRADA, delta -3 → SALIDA
```

### 6.2 `tests/js/ajustes/create.property.test.js` (fast-check)
- Property: total líneas ≥ 1
- Property: cantidad ≠ 0
- Property: conteo_fisico → delta calculado correctamente

---

## 7. Integración con Existente

| Componente | Cambio |
|------------|--------|
| `PermissionSeeder` | Agregar `acceso-ajustes` |
| `routes/web.php` | `Route::resource('ajustes', AjusteInventarioController::class)` bajo `permission:acceso-ajustes` |
| `AuditServiceProvider` | Agregar `AjusteInventario::class` a `$modelos` |
| `AuditService::EXCLUIDOS` | Agregar `DetalleAjusteInventario::class` |
| `AccionesAuditoriaController` | Agregar `crear ajuste` a acciones |
| `layouts/app.blade.php` | Enlace "Ajustes" en Gestión Administrativa |
| `vite.config.js` | Agregar `resources/js/ajustes/create.js` |
| `DatabaseSeeder` | Registrar `AjusteInventarioSeeder` (opcional, demo) |

---

## 8. Migraciones Orden

1. `create_ajustes_inventario_table.php`
2. `create_detalle_ajustes_inventario_table.php`
3. `add_costo_to_movimientos_kardex_table.php`