<?php

namespace App\Http\Controllers;

use App\Models\MovimientoKardex;
use App\Models\Producto;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class KardexController extends Controller
{
    /**
     * Listado paginado de movimientos de Kardex con filtros.
     */
    public function index(Request $request): View
    {
        $filtros = [
            'producto_id' => $request->get('producto_id'),
            'fuente' => $request->get('fuente'),
            'origen_id' => trim((string) $request->get('origen_id')),
            'desde' => $request->get('desde'),
            'hasta' => $request->get('hasta'),
        ];

        $query = MovimientoKardex::with(['producto', 'usuario'])->latest('fecha_movimiento');

        if (! empty($filtros['producto_id'])) {
            $query->where('producto_id', $filtros['producto_id']);
        }

        if (! empty($filtros['fuente'])) {
            $query->where('fuente', $filtros['fuente']);
        }

        if (! empty($filtros['origen_id'])) {
            $query->where('origen_id', 'like', '%'.$filtros['origen_id'].'%');
        }

        if (! empty($filtros['desde'])) {
            $query->whereDate('fecha_movimiento', '>=', $filtros['desde']);
        }

        if (! empty($filtros['hasta'])) {
            $query->whereDate('fecha_movimiento', '<=', $filtros['hasta']);
        }

        $movimientos = $query->paginate(15)->withQueryString();
        $productos = Producto::orderBy('nombre')->get();

        return view('kardex.index', compact('movimientos', 'productos', 'filtros'));
    }

    /**
     * Histórico de movimientos filtrado por un producto con su saldo actual.
     */
    public function porProducto(Producto $producto): View
    {
        $movimientos = MovimientoKardex::with('usuario')
            ->where('producto_id', $producto->id)
            ->latest('fecha_movimiento')
            ->paginate(15);

        return view('kardex.por-producto', compact('producto', 'movimientos'));
    }
}
