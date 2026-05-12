<?php

namespace App\Http\Controllers;

use App\Models\Trabajador;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrabajadorController extends Controller
{
    /**
     * Display a paginated listing of trabajadores.
     */
    public function index(): View
    {
        $trabajadores = Trabajador::paginate(10);

        return view('trabajadores.index', compact('trabajadores'));
    }

    /**
     * Show the form for creating a new trabajador.
     */
    public function create(): View
    {
        return view('trabajadores.create');
    }

    /**
     * Store a newly created trabajador in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:100', 'unique:trabajadores'],
            'estado' => ['required', 'boolean'],
        ]);

        Trabajador::create([
            'nombre' => $request->nombre,
            'estado' => $request->estado,
        ]);

        return redirect()->route('trabajadores.index')
            ->with('success', 'Trabajador creado correctamente.');
    }

    /**
     * Show the form for editing an existing trabajador.
     */
    public function edit(Trabajador $trabajador): View
    {
        return view('trabajadores.edit', compact('trabajador'));
    }

    /**
     * Update the specified trabajador in the database.
     */
    public function update(Request $request, Trabajador $trabajador): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:100', 'unique:trabajadores,nombre,' . $trabajador->id],
            'estado' => ['required', 'boolean'],
        ]);

        $trabajador->update([
            'nombre' => $request->nombre,
            'estado' => $request->estado,
        ]);

        return redirect()->route('trabajadores.index')
            ->with('success', 'Trabajador actualizado correctamente.');
    }

    /**
     * Remove the specified trabajador from the database.
     * Deletion is blocked if the trabajador has related cambioAceites or ingresos.
     */
    public function destroy(Trabajador $trabajador): RedirectResponse
    {
        if ($trabajador->cambioAceites()->exists()) {
            return redirect()->route('trabajadores.index')
                ->with('error', 'No se puede eliminar el trabajador porque tiene cambios de aceite asociados.');
        }

        if ($trabajador->ingresos()->exists()) {
            return redirect()->route('trabajadores.index')
                ->with('error', 'No se puede eliminar el trabajador porque tiene ingresos asociados.');
        }

        $trabajador->delete();

        return redirect()->route('trabajadores.index')
            ->with('success', 'Trabajador eliminado correctamente.');
    }

    /**
     * Toggle the active status of the specified trabajador.
     */
    public function toggleStatus(Trabajador $trabajador): \Illuminate\Http\JsonResponse
    {
        try {
            $trabajador->estado = !$trabajador->estado;
            $trabajador->save();

            return response()->json([
                'success' => true,
                'estado'  => (bool) $trabajador->estado,
                'message' => 'Estado actualizado correctamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado.'
            ], 500);
        }
    }
}
