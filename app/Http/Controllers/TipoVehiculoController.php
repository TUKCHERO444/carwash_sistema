<?php

namespace App\Http\Controllers;

use App\Models\TipoVehiculo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TipoVehiculoController extends Controller
{
    /**
     * Regla de validación alfanumérica (letras, números y espacios;
     * rechaza símbolos).
     */
    private const ALFANUMERICO_RULE = 'regex:/^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ ]+$/u';

    /**
     * Display a paginated listing de tipos de vehículo.
     */
    public function index(): View
    {
        $tiposVehiculo = TipoVehiculo::paginate(10);

        return view('tipos-vehiculo.index', compact('tiposVehiculo'));
    }

    /**
     * Show the form for creating a new tipo de vehículo.
     */
    public function create(): View
    {
        return view('tipos-vehiculo.create');
    }

    /**
     * Store a newly created tipo de vehículo in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:30', self::ALFANUMERICO_RULE],
            'descripcion' => ['nullable', 'string', 'max:100', self::ALFANUMERICO_RULE],
            'precio' => ['required', 'numeric', 'gt:0'],
        ]);

        TipoVehiculo::create($request->only('nombre', 'descripcion', 'precio'));

        return redirect()->route('tipos-vehiculo.index')
            ->with('success', 'Tipo de vehículo creado correctamente.');
    }

    /**
     * Show the form for editing an existing tipo de vehículo.
     */
    public function edit(TipoVehiculo $tipoVehiculo): View
    {
        return view('tipos-vehiculo.edit', compact('tipoVehiculo'));
    }

    /**
     * Update the specified tipo de vehículo in the database.
     */
    public function update(Request $request, TipoVehiculo $tipoVehiculo): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:30', self::ALFANUMERICO_RULE],
            'descripcion' => ['nullable', 'string', 'max:100', self::ALFANUMERICO_RULE],
            'precio' => ['required', 'numeric', 'gt:0'],
        ]);

        $tipoVehiculo->update($request->only('nombre', 'descripcion', 'precio'));

        return redirect()->route('tipos-vehiculo.index')
            ->with('success', 'Tipo de vehículo actualizado correctamente.');
    }

    /**
     * Remove the specified tipo de vehículo from the database.
     * Blocked if the tipo tiene lavados asociados.
     */
    public function destroy(TipoVehiculo $tipoVehiculo): RedirectResponse
    {
        if ($tipoVehiculo->lavados()->exists()) {
            return redirect()->route('tipos-vehiculo.index')
                ->with('error', 'No se puede eliminar el tipo de vehículo porque tiene lavados asociados.');
        }

        $tipoVehiculo->delete();

        return redirect()->route('tipos-vehiculo.index')
            ->with('success', 'Tipo de vehículo eliminado correctamente.');
    }
}
