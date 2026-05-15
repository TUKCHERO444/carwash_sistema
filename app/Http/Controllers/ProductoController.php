<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
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
    public function buscar(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim($request->input('q', ''));

        $query = Producto::with('categoria');

        if ($q !== '') {
            $query->where('nombre', 'like', "%{$q}%");
        }

        $productos = $query->orderBy('nombre')->limit(20)->get();

        return response()->json([
            'success'   => true,
            'total'     => $productos->count(),
            'productos' => $productos->map(fn ($p) => [
                'id'            => $p->id,
                'nombre'        => $p->nombre,
                'categoria'     => $p->categoria?->nombre ?? '—',
                'precio_compra' => number_format($p->precio_compra, 2),
                'precio_venta'  => number_format($p->precio_venta, 2),
                'stock'         => $p->stock,
                'activo'        => (bool) $p->activo,
                'foto'          => $p->foto ? asset('storage/' . $p->foto) : null,
                'edit_url'      => route('productos.edit', $p),
                'toggle_url'    => route('productos.toggleStatus', $p),
                'stock_url'     => route('productos.updateStock', $p),
                'destroy_url'   => route('productos.destroy', $p),
            ]),
        ]);
    }

    /**
     * Show the form for creating a new producto.
     */
    public function create(): View
    {
        $categorias = \App\Models\Categoria::orderBy('nombre')->get();
        return view('productos.create', compact('categorias'));
    }

    /**
     * Store a newly created producto in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre'        => ['required', 'string', 'max:150'],
            'precio_compra' => ['required', 'numeric', 'gt:0'],
            'precio_venta'  => ['required', 'numeric', 'gt:0'],
            'stock'         => ['required', 'integer', 'min:0'],
            'inventario'    => ['required', 'integer', 'min:0'],
            'activo'        => ['nullable', 'boolean'],
            'foto'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'categoria_id'  => ['nullable', 'integer', 'exists:categorias,id'],
        ]);

        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('images/productos', 'public');
        }

        DB::transaction(function () use ($validated, $fotoPath, $request) {
            Producto::create([
                'nombre'        => $validated['nombre'],
                'precio_compra' => $validated['precio_compra'],
                'precio_venta'  => $validated['precio_venta'],
                'stock'         => $validated['stock'],
                'inventario'    => $validated['inventario'],
                'activo'        => $request->boolean('activo', true),
                'foto'          => $fotoPath,
                'categoria_id'  => $validated['categoria_id'] ?? null,
            ]);

            if (!empty($validated['categoria_id'])) {
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
        $categorias = \App\Models\Categoria::orderBy('nombre')->get();
        return view('productos.edit', compact('producto', 'categorias'));
    }

    /**
     * Update the specified producto in the database.
     */
    public function update(Request $request, Producto $producto): RedirectResponse
    {
        $validated = $request->validate([
            'nombre'        => ['required', 'string', 'max:150'],
            'precio_compra' => ['required', 'numeric', 'gt:0'],
            'precio_venta'  => ['required', 'numeric', 'gt:0'],
            'stock'         => ['required', 'integer', 'min:0'],
            'inventario'    => ['required', 'integer', 'min:0'],
            'activo'        => ['nullable', 'boolean'],
            'foto'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'categoria_id'  => ['nullable', 'integer', 'exists:categorias,id'],
        ]);

        $data = [
            'nombre'        => $validated['nombre'],
            'precio_compra' => $validated['precio_compra'],
            'precio_venta'  => $validated['precio_venta'],
            'stock'         => $validated['stock'],
            'inventario'    => $validated['inventario'],
            'categoria_id'  => $validated['categoria_id'] ?? null,
        ];

        if ($request->hasFile('foto')) {
            // Eliminar imagen anterior si existe
            if ($producto->foto && Storage::disk('public')->exists($producto->foto)) {
                Storage::disk('public')->delete($producto->foto);
            }
            $data['foto'] = $request->file('foto')->store('images/productos', 'public');
        }
        // Si no hay imagen nueva, no se incluye 'foto' en $data → se conserva la ruta anterior

        DB::transaction(function () use ($producto, $data, $validated) {
            $categoria_id_anterior = $producto->categoria_id;
            $categoria_id_nueva    = $validated['categoria_id'] ?? null;

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
    public function updateStock(Request $request, Producto $producto): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'cantidad_adicional' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        try {
            $nuevoStock = $producto->stock + $validated['cantidad_adicional'];

            DB::transaction(function () use ($producto, $nuevoStock) {
                $producto->stock = $nuevoStock;
                $producto->inventario = $nuevoStock;
                $producto->save();
            });

            return response()->json(['success' => true, 'nuevo_stock' => $nuevoStock]);
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
    public function toggleStatus(Producto $producto): \Illuminate\Http\JsonResponse
    {
        try {
            $producto->activo = !$producto->activo;
            $producto->save();

            return response()->json([
                'success' => true,
                'activo'  => (bool) $producto->activo,
                'message' => 'Estado actualizado correctamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado.'
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
        // Delete image outside the transaction — filesystem ops cannot be rolled back
        if ($producto->foto && Storage::disk('public')->exists($producto->foto)) {
            Storage::disk('public')->delete($producto->foto);
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
