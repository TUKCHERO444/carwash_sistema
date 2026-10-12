<?php

namespace App\Http\Controllers\Cuenta;

use App\Http\Controllers\Controller;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Vehículos del cliente (solo lectura, scopeados por vínculo ACTIVO).
 */
class VehiculoController extends Controller
{
    /**
     * Lista los vehículos vinculados a la cuenta. Estática antes que show.
     */
    public function index(Request $request): View
    {
        $clienteIds = $request->user()->clientes()
            ->wherePivot('estado', 'ACTIVO')
            ->pluck('clientes.id');

        $vehiculos = Vehiculo::whereIn('cliente_id', $clienteIds)
            ->orderBy('placa')
            ->paginate(10);

        return view('cuenta.vehiculos.index', compact('vehiculos'));
    }

    /**
     * Detalle de un vehículo propio (ownership por VehiculoPolicy).
     */
    public function show(Request $request, Vehiculo $vehiculo): View
    {
        Gate::authorize('view', $vehiculo);

        $vehiculo->load('cliente');

        return view('cuenta.vehiculos.show', compact('vehiculo'));
    }
}
