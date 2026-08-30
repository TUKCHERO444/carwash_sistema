<?php

namespace App\Http\Controllers;

use App\Models\Trabajador;
use App\Services\AuditService;
use App\Services\DniApiService;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TrabajadorController extends Controller
{
    /**
     * Regla de validación "solo letras" (admite acentos, ü y ñ;
     * espacios simples entre palabras). Rechaza números y símbolos.
     */
    private const SOLO_LETRAS_RULE = 'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u';

    public function __construct(
        private DniApiService $dniApiService,
    ) {}

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
        $validated = $this->validateTrabajador($request);

        $fotoUrl = null;
        if ($request->hasFile('foto')) {
            $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
            $fotoUrl = $result['secure_url'];
        }

        Trabajador::create([
            'dni' => $validated['dni'],
            'nombre' => $validated['nombre'],
            'apellido_paterno' => $validated['apellido_paterno'],
            'apellido_materno' => $validated['apellido_materno'],
            'foto' => $fotoUrl,
            'estado' => $request->boolean('estado'),
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
        $validated = $this->validateTrabajador($request, $trabajador);

        $data = [
            'dni' => $validated['dni'],
            'nombre' => $validated['nombre'],
            'apellido_paterno' => $validated['apellido_paterno'],
            'apellido_materno' => $validated['apellido_materno'],
        ];

        if ($request->hasFile('foto')) {
            // Eliminar imagen anterior
            if ($trabajador->foto) {
                if (str_starts_with($trabajador->foto, 'http')) {
                    // Extraer public_id de la URL de Cloudinary
                    $parts = explode('/', $trabajador->foto);
                    $filename = end($parts);
                    $publicId = pathinfo($filename, PATHINFO_FILENAME);
                    try {
                        Cloudinary::uploadApi()->destroy($publicId);
                    } catch (\Exception $e) {
                        // Silencioso: no bloquea el flujo si falla el borrado remoto
                    }
                } elseif (Storage::disk('public')->exists($trabajador->foto)) {
                    Storage::disk('public')->delete($trabajador->foto);
                }
            }

            $result = Cloudinary::uploadApi()->upload($request->file('foto')->getRealPath());
            $data['foto'] = $result['secure_url'];
        }
        // Si no hay imagen nueva, no se incluye 'foto' en $data → se conserva la URL anterior

        $trabajador->update($data);

        return redirect()->route('trabajadores.index')
            ->with('success', 'Trabajador actualizado correctamente.');
    }

    /**
     * Remove the specified trabajador from the database.
     * Deletion is blocked if the trabajador has related cambioAceites or lavados.
     * Deletes the Cloudinary/local image outside the relation checks flow.
     */
    public function destroy(Trabajador $trabajador): RedirectResponse
    {
        if ($trabajador->cambioAceites()->exists()) {
            return redirect()->route('trabajadores.index')
                ->with('error', 'No se puede eliminar el trabajador porque tiene cambios de aceite asociados.');
        }

        if ($trabajador->lavados()->exists()) {
            return redirect()->route('trabajadores.index')
                ->with('error', 'No se puede eliminar el trabajador porque tiene lavados asociados.');
        }

        if ($trabajador->foto) {
            if (str_starts_with($trabajador->foto, 'http')) {
                $parts = explode('/', $trabajador->foto);
                $filename = end($parts);
                $publicId = pathinfo($filename, PATHINFO_FILENAME);
                try {
                    Cloudinary::uploadApi()->destroy($publicId);
                } catch (\Exception $e) {
                    // Silencioso si no se encuentra o falla
                }
            } elseif (Storage::disk('public')->exists($trabajador->foto)) {
                Storage::disk('public')->delete($trabajador->foto);
            }
        }

        $trabajador->delete();

        return redirect()->route('trabajadores.index')
            ->with('success', 'Trabajador eliminado correctamente.');
    }

    /**
     * Toggle the active status of the specified trabajador.
     */
    public function toggleStatus(Trabajador $trabajador): JsonResponse
    {
        try {
            app(AuditService::class)->anotarAccion('toggle estado');

            $trabajador->estado = ! $trabajador->estado;
            $trabajador->save();

            return response()->json([
                'success' => true,
                'estado' => (bool) $trabajador->estado,
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
     * Reglas de validación compartidas por store() y update().
     * En update(), el DNI se excluye a sí mismo de la comprobación unique.
     */
    private function validateTrabajador(Request $request, ?Trabajador $trabajador = null): array
    {
        $uniqueDni = $trabajador
            ? 'unique:trabajadores,dni,'.$trabajador->id
            : 'unique:trabajadores,dni';

        return $request->validate([
            'dni' => ['required', 'string', 'digits:8', 'regex:/^[0-9]{8}$/', $uniqueDni],
            'nombre' => ['required', 'string', 'max:50', self::SOLO_LETRAS_RULE],
            'apellido_paterno' => ['required', 'string', 'max:50', self::SOLO_LETRAS_RULE],
            'apellido_materno' => ['required', 'string', 'max:50', self::SOLO_LETRAS_RULE],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'estado' => ['required', 'boolean'],
        ]);
    }

    /**
     * Consulta los datos de la persona por DNI para autocompletar el formulario.
     *
     * Prioriza los datos locales si el DNI ya está registrado; de lo contrario,
     * consulta la API de DNI. Si la API no responde, devuelve success true sin
     * datos (nada que autocompletar), sin bloquear el flujo.
     */
    public function consultarDni(Request $request): JsonResponse
    {
        $request->validate([
            'dni' => ['required', 'string', 'digits:8', 'regex:/^[0-9]{8}$/'],
        ]);

        $trabajador = Trabajador::where('dni', $request->dni)->first();

        if ($trabajador) {
            return response()->json([
                'success' => true,
                'data' => [
                    'numero' => $trabajador->dni,
                    'nombres' => $trabajador->nombre,
                    'apellido_paterno' => $trabajador->apellido_paterno,
                    'apellido_materno' => $trabajador->apellido_materno,
                    'nombre_completo' => $trabajador->nombre_completo,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->dniApiService->buscarPorDni($request->dni),
        ]);
    }
}
