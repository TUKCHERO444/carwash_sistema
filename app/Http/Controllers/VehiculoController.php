<?php

namespace App\Http\Controllers;

use App\Models\Vehiculo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VehiculoController extends Controller
{
    /**
     * Display a paginated listing of vehículos.
     */
    public function index(): View
    {
        $vehiculos = Vehiculo::paginate(10);

        return view('vehiculos.index', compact('vehiculos'));
    }

    /**
     * Show the form for creating a new vehículo.
     */
    public function create(): View
    {
        return view('vehiculos.create');
    }

    /**
     * Store a newly created vehículo in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nombre'      => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'precio'      => ['required', 'numeric', 'gt:0'],
        ]);

        Vehiculo::create($request->only('nombre', 'descripcion', 'precio'));

        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo creado correctamente.');
    }

    /**
     * Show the form for editing an existing vehículo.
     */
    public function edit(Vehiculo $vehiculo): View
    {
        return view('vehiculos.edit', compact('vehiculo'));
    }

    /**
     * Update the specified vehículo in the database.
     */
    public function update(Request $request, Vehiculo $vehiculo): RedirectResponse
    {
        $request->validate([
            'nombre'      => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'precio'      => ['required', 'numeric', 'gt:0'],
        ]);

        $vehiculo->update($request->only('nombre', 'descripcion', 'precio'));

        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo actualizado correctamente.');
    }

    /**
     * Remove the specified vehículo from the database.
     * Blocked if the vehículo has associated ingresos.
     */
    public function destroy(Vehiculo $vehiculo): RedirectResponse
    {
        if ($vehiculo->ingresos()->exists()) {
            return redirect()->route('vehiculos.index')
                ->with('error', 'No se puede eliminar el vehículo porque tiene ingresos asociados.');
        }

        $vehiculo->delete();

        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo eliminado correctamente.');
    }
}
