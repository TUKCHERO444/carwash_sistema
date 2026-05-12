<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    /**
     * Display a listing of all categorias ordered by name.
     */
    public function index(): View
    {
        $categorias = Categoria::orderBy('nombre')->get();

        return view('categorias.index', compact('categorias'));
    }

    /**
     * Show the form for creating a new categoria.
     */
    public function create(): View
    {
        return view('categorias.create');
    }

    /**
     * Store a newly created categoria in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nombre'      => ['required', 'string', 'max:150', 'unique:categorias,nombre'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ]);

        Categoria::create([
            'nombre'             => $request->nombre,
            'descripcion'        => $request->descripcion,
            'contador_productos' => 0,
        ]);

        return redirect()->route('categorias.index')
            ->with('success', 'Categoría creada correctamente.');
    }

    /**
     * Show the form for editing an existing categoria.
     */
    public function edit(Categoria $categoria): View
    {
        return view('categorias.edit', compact('categoria'));
    }

    /**
     * Update the specified categoria in the database.
     */
    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        $request->validate([
            'nombre'      => ['required', 'string', 'max:150', 'unique:categorias,nombre,' . $categoria->id],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ]);

        $categoria->update([
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
        ]);

        return redirect()->route('categorias.index')
            ->with('success', 'Categoría actualizada correctamente.');
    }

    /**
     * Remove the specified categoria from the database.
     * Rejects deletion if the categoria has products assigned.
     */
    public function destroy(Categoria $categoria): RedirectResponse
    {
        if ($categoria->contador_productos > 0) {
            return redirect()->back()
                ->with('error', 'No se puede eliminar la categoría porque tiene productos asignados.');
        }

        $categoria->delete();

        return redirect()->route('categorias.index')
            ->with('success', 'Categoría eliminada correctamente.');
    }
}
