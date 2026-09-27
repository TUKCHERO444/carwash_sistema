<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Services\AuditService;
use App\Services\KardexService;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
    public function __construct(private KardexService $kardexService) {}

    /**
     * Regla de validación alfanumérica para la descripción.
     * Admite letras, números y espacios; rechaza símbolos.
     */
    private const DESCRIPCION_RULES = [
        'nullable',
        'string',
        'max:100',
        'regex:/^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ ]+$/u',
    ];

    /**
     * Reglas de precio de compra y venta.
     *
     * `precio_compra` es opcional: sin una compra registrada el costo real es
     * desconocido y se representa con 0, no con null, porque la columna es NOT NULL
     * y el reporte de inventario la usa en aritmética.
     *
     * La regla de margen solo aplica cuando hay un costo conocido: con
     * `precio_compra` en 0 basta con que el precio de venta sea positivo.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<int, string>>
     */
    private function reglasPrecio(array $data): array
    {
        $precioCompra = (float) ($data['precio_compra'] ?? 0);

        $precioVenta = ['required', 'numeric', 'gt:0'];

        if ($precioCompra > 0) {
            $precioVenta[] = 'gte:precio_compra';
        }

        return [
            'precio_compra' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => $precioVenta,
        ];
    }

    /**
     * Display a paginated listing of productos.
     */
    public function index(): View
    {
        $productos = Producto::with('categoria')->paginate(10);

        return view('productos.index', compact('productos'));
    }

    /**
     * Búsqueda dinámica de productos por nombre (AJAX).
     * Devuelve un JSON con los productos que coinciden con el término buscado.
     */
    public function buscar(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));

        $query = Producto::with('categoria');

        if ($q !== '') {
            $query->where('nombre', 'like', "%{$q}%");
        }

        $productos = $query->orderBy('nombre')->limit(20)->get();

        return response()->json([
            'success' => true,
            'total' => $productos->count(),
            'productos' => $productos->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'descripcion' => $p->descripcion,
                'categoria' => $p->categoria?->nombre ?? '—',
                'precio_compra' => number_format($p->precio_compra, 2),
                'precio_venta' => number_format($p->precio_venta, 2),
                'stock' => $p->stock,
                'inventario' => $p->inventario,
                'stock_bajo' => $p->esta_en_alerta,
                'activo' => (bool) $p->activo,
                'foto' => $p->foto_url,
                'edit_url' => route('productos.edit', $p),
                'toggle_url' => route('productos.toggleStatus', $p),
                'stock_url' => route('productos.updateStock', $p),
                'destroy_url' => route('productos.destroy', $p),
            ]),
        ]);
    }

    /**
     * Show the form for creating a new producto.
     */
    public function create(): View
    {
        $categorias = Categoria::orderBy('nombre')->get();
        $marcas = Marca::orderBy('nombre')->get();

        return view('productos.create', compact('categorias', 'marcas'));
    }

    /**
     * Store a newly created producto in the database.
     *
     * El producto entra al catálogo con cero existencias y sin movimiento de Kardex:
     * un producto que se acaba de definir no recibió mercadería. Reponer sus
     * existencias es una operación de inventario, registrada en el Kardex.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            array_merge([
                'nombre' => ['required', 'string', 'max:150'],
                'descripcion' => self::DESCRIPCION_RULES,
                'activo' => ['nullable', 'boolean'],
                'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
                'marca_id' => ['nullable', 'integer', 'exists:marcas,id'],
            ], $this->reglasPrecio($request->all())),
            [
                'precio_venta.gte' => 'El precio de venta no puede ser inferior al precio de compra.',
            ]
        );

        $fotoUrl = null;
        if ($request->hasFile('foto')) {
            $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
            $fotoUrl = $result['secure_url'];
        }

        DB::transaction(function () use ($validated, $fotoUrl, $request) {
            $producto = Producto::create([
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'precio_compra' => (float) ($validated['precio_compra'] ?? 0),
                'precio_venta' => $validated['precio_venta'],
                'stock' => 0,
                'inventario' => 0,
                'activo' => $request->boolean('activo', true),
                'foto' => $fotoUrl,
                'categoria_id' => $validated['categoria_id'] ?? null,
                'marca_id' => $validated['marca_id'] ?? null,
            ]);

            if (! empty($validated['categoria_id'])) {
                Categoria::find($validated['categoria_id'])->increment('contador_productos');
            }
        });

        return redirect()->route('productos.index')
            ->with('success', 'Producto creado correctamente.');
    }

    /**
     * Show the form for editing an existing producto.
     */
    public function edit(Producto $producto): View
    {
        $categorias = Categoria::orderBy('nombre')->get();
        $marcas = Marca::orderBy('nombre')->get();

        return view('productos.edit', compact('producto', 'categorias', 'marcas'));
    }

    /**
     * Update the specified producto in the database.
     *
     * `stock` e `inventario` quedan fuera de propósito a propósito: la existencia es el
     * resultado de las operaciones de inventario registradas por el sistema, no un dato
     * que se edite a mano. Cualquier valor recibido en esos campos se ignora.
     */
    public function update(Request $request, Producto $producto): RedirectResponse
    {
        $validated = $request->validate(
            array_merge([
                'nombre' => ['required', 'string', 'max:150'],
                'descripcion' => self::DESCRIPCION_RULES,
                'activo' => ['nullable', 'boolean'],
                'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
                'marca_id' => ['nullable', 'integer', 'exists:marcas,id'],
            ], $this->reglasPrecio($request->all())),
            [
                'precio_venta.gte' => 'El precio de venta no puede ser inferior al precio de compra.',
            ]
        );

        $data = [
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'precio_compra' => (float) ($validated['precio_compra'] ?? 0),
            'precio_venta' => $validated['precio_venta'],
            'categoria_id' => $validated['categoria_id'] ?? null,
            'marca_id' => $validated['marca_id'] ?? null,
        ];

        if ($request->hasFile('foto')) {
            // Eliminar imagen anterior
            if ($producto->foto) {
                if (str_starts_with($producto->foto, 'http')) {
                    // Extraer public_id de la URL de Cloudinary
                    $parts = explode('/', $producto->foto);
                    $filename = end($parts);
                    $publicId = pathinfo($filename, PATHINFO_FILENAME);
                    try {
                        Cloudinary::uploadApi()->destroy($publicId);
                    } catch (\Exception $e) {
                        // Opcional: loguear error si no se pudo borrar de Cloudinary
                    }
                } elseif (Storage::disk('public')->exists($producto->foto)) {
                    Storage::disk('public')->delete($producto->foto);
                }
            }

            $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
            $data['foto'] = $result['secure_url'];
        }
        // Si no hay imagen nueva, no se incluye 'foto' en $data → se conserva la ruta anterior

        DB::transaction(function () use ($producto, $data, $validated) {
            $categoria_id_anterior = $producto->categoria_id;
            $categoria_id_nueva = $validated['categoria_id'] ?? null;

            $producto->update($data);

            if ($categoria_id_anterior !== $categoria_id_nueva) {
                if ($categoria_id_anterior !== null) {
                    Categoria::find($categoria_id_anterior)->decrement('contador_productos');
                }
                if ($categoria_id_nueva !== null) {
                    Categoria::find($categoria_id_nueva)->increment('contador_productos');
                }
            }
        });

        return redirect()->route('productos.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    /**
     * Update only the stock (and inventario) of the specified producto.
     * Accepts a JSON request with `cantidad_adicional` and returns a JSON response.
     */
    public function updateStock(Request $request, Producto $producto): JsonResponse
    {
        $validated = $request->validate([
            'cantidad_adicional' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        try {
            $stockAntes = $producto->stock;
            $nuevoStock = $producto->stock + $validated['cantidad_adicional'];

            DB::transaction(function () use ($producto, $nuevoStock, $stockAntes, $validated) {
                $correlativo = $this->kardexService->siguienteCorrelativoInventario();

                app(AuditService::class)->anotarAccion('ajustar stock');

                $producto->stock = $nuevoStock;
                $producto->inventario = $nuevoStock;
                $producto->save();

                $this->kardexService->registrarEntrada(
                    $producto,
                    (int) $validated['cantidad_adicional'],
                    $stockAntes,
                    'inventario',
                    $correlativo
                );
            });

            return response()->json([
                'success' => true,
                'nuevo_stock' => $nuevoStock,
                'nuevo_inventario' => $producto->inventario,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el stock. Intente nuevamente.',
            ], 500);
        }
    }

    /**
     * Toggle the active status of the specified producto.
     */
    public function toggleStatus(Producto $producto): JsonResponse
    {
        try {
            app(AuditService::class)->anotarAccion('toggle estado');

            $producto->activo = ! $producto->activo;
            $producto->save();

            return response()->json([
                'success' => true,
                'activo' => (bool) $producto->activo,
                'message' => 'Estado actualizado correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado.',
            ], 500);
        }
    }

    /**
     * Remove the specified producto from the database.
     * Also deletes the associated image file from storage if it exists.
     * Image deletion happens OUTSIDE the transaction since filesystem ops can't be rolled back.
     */
    public function destroy(Producto $producto): RedirectResponse
    {
        // Delete image outside the transaction
        if ($producto->foto) {
            if (str_starts_with($producto->foto, 'http')) {
                $parts = explode('/', $producto->foto);
                $filename = end($parts);
                $publicId = pathinfo($filename, PATHINFO_FILENAME);
                try {
                    Cloudinary::uploadApi()->destroy($publicId);
                } catch (\Exception $e) {
                    // Silently fail if not found or error
                }
            } elseif (Storage::disk('public')->exists($producto->foto)) {
                Storage::disk('public')->delete($producto->foto);
            }
        }

        DB::transaction(function () use ($producto) {
            if ($producto->categoria_id !== null) {
                Categoria::find($producto->categoria_id)->decrement('contador_productos');
            }

            $producto->delete();
        });

        return redirect()->route('productos.index')
            ->with('success', 'Producto eliminado correctamente.');
    }
}
