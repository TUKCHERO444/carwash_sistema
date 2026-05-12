<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServicioController extends Controller
{
    /**
     * Muestra la lista paginada de servicios.
     */
    public function index(): View
    {
        $servicios = Servicio::paginate(10);

        return view('servicios.index', compact('servicios'));
    }

    /**
     * Muestra el formulario de creación.
     */
    public function create(): View
    {
        return view('servicios.create');
    }

    /**
     * Valida y persiste un nuevo servicio.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'precio' => ['required', 'numeric', 'gt:0'],
        ]);

        Servicio::create($request->only('nombre', 'precio'));

        return redirect()->route('servicios.index')
            ->with('success', 'Servicio creado correctamente.');
    }

    /**
     * Muestra el formulario de edición con datos precargados.
     * Usa Route Model Binding: Servicio $servicio
     */
    public function edit(Servicio $servicio): View
    {
        return view('servicios.edit', compact('servicio'));
    }

    /**
     * Valida y actualiza un servicio existente.
     */
    public function update(Request $request, Servicio $servicio): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'precio' => ['required', 'numeric', 'gt:0'],
        ]);

        $servicio->update($request->only('nombre', 'precio'));

        return redirect()->route('servicios.index')
            ->with('success', 'Servicio actualizado correctamente.');
    }

    /**
     * Elimina un servicio si no tiene ingresos asociados.
     *
     * Si $servicio->ingresos()->exists() → redirect + flash 'error'
     * Si no tiene ingresos               → delete() + redirect + flash 'success'
     */
    public function destroy(Servicio $servicio): RedirectResponse
    {
        if ($servicio->ingresos()->exists()) {
            return redirect()->route('servicios.index')
                ->with('error', 'No se puede eliminar el servicio porque tiene ingresos asociados.');
        }

        $servicio->delete();

        return redirect()->route('servicios.index')
            ->with('success', 'Servicio eliminado correctamente.');
    }
}
