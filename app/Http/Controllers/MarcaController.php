<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MarcaController extends Controller
{
    /**
     * Regla de validación para el archivo de foto (patrón Cloudinary).
     */
    private const FOTO_RULES = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];

    /**
     * Solo letras (con acentos españoles y ñ) y espacios internos simples.
     * Rechaza números, símbolos y espacios dobles.
     */
    private const SOLO_LETRAS_RULE = 'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u';

    /**
     * Letras, números y espacios internos simples. Rechaza símbolos.
     */
    private const ALFANUMERICO_RULE = 'regex:/^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ]+)*$/u';

    /**
     * Display a listing of all marcas ordered by name.
     */
    public function index(): View
    {
        $marcas = Marca::withCount('productos')->orderBy('nombre')->paginate(10);

        return view('marcas.index', compact('marcas'));
    }

    /**
     * Show the form for creating a new marca.
     */
    public function create(): View
    {
        return view('marcas.create');
    }

    /**
     * Store a newly created marca in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:150', 'unique:marcas,nombre', self::SOLO_LETRAS_RULE],
            'descripcion' => ['nullable', 'string', 'max:100', self::ALFANUMERICO_RULE],
            'foto' => self::FOTO_RULES,
        ]);

        $fotoUrl = null;
        if ($request->hasFile('foto')) {
            $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
            $fotoUrl = $result['secure_url'];
        }

        Marca::create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'foto' => $fotoUrl,
        ]);

        return redirect()->route('marcas.index')
            ->with('success', 'Marca creada correctamente.');
    }

    /**
     * Show the form for editing an existing marca.
     */
    public function edit(Marca $marca): View
    {
        return view('marcas.edit', compact('marca'));
    }

    /**
     * Update the specified marca in the database.
     */
    public function update(Request $request, Marca $marca): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:150', 'unique:marcas,nombre,'.$marca->id, self::SOLO_LETRAS_RULE],
            'descripcion' => ['nullable', 'string', 'max:100', self::ALFANUMERICO_RULE],
            'foto' => self::FOTO_RULES,
        ]);

        $data = [
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
        ];

        if ($request->hasFile('foto')) {
            // Eliminar imagen anterior
            $this->eliminarFoto($marca);

            $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
            $data['foto'] = $result['secure_url'];
        }
        // Si no hay imagen nueva, no se incluye 'foto' en $data → se conserva la anterior

        $marca->update($data);

        return redirect()->route('marcas.index')
            ->with('success', 'Marca actualizada correctamente.');
    }

    /**
     * Remove the specified marca from the database.
     * Rejects deletion if the marca has products assigned.
     */
    public function destroy(Marca $marca): RedirectResponse
    {
        if ($marca->productos()->exists()) {
            return redirect()->route('marcas.index')
                ->with('error', 'No se puede eliminar la marca porque tiene productos asignados.');
        }

        // Eliminar imagen fuera del borrado: las operaciones de archivo no se pueden revertir.
        $this->eliminarFoto($marca);

        $marca->delete();

        return redirect()->route('marcas.index')
            ->with('success', 'Marca eliminada correctamente.');
    }

    /**
     * Elimina la foto de la marca de Cloudinary (o del disco público si es
     * una ruta local). Hace lo mismo que el patrón aplicado en ProductoController.
     */
    private function eliminarFoto(Marca $marca): void
    {
        if (! $marca->foto) {
            return;
        }

        if (str_starts_with($marca->foto, 'http')) {
            // Extraer public_id de la URL de Cloudinary
            $parts = explode('/', $marca->foto);
            $filename = end($parts);
            $publicId = pathinfo($filename, PATHINFO_FILENAME);
            try {
                Cloudinary::uploadApi()->destroy($publicId);
            } catch (\Exception $e) {
                // Opcional: loguear error si no se pudo borrar de Cloudinary
            }
        } elseif (Storage::disk('public')->exists($marca->foto)) {
            Storage::disk('public')->delete($marca->foto);
        }
    }
}
