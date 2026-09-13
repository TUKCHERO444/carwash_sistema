<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use App\Services\AuditService;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ServicioController extends Controller
{
    /**
     * Regla de validación alfanumérica para el nombre (letras, números
     * y espacios; rechaza símbolos). Máximo 30 caracteres.
     */
    private const NOMBRE_RULES = [
        'required',
        'string',
        'max:30',
        'regex:/^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ ]+$/u',
    ];

    /**
     * Regla de validación alfanumérica para la descripción.
     * Admite letras, números y espacios; rechaza símbolos.
     */
    private const DESCRIPCION_RULES = [
        'nullable',
        'string',
        'max:100',
        'regex:/^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ ]+$/u',
    ];

    /**
     * Íconos disponibles para la tarjeta pública del servicio.
     */
    private const ICONOS = ['sparkles', 'shield', 'oil', 'layers', 'paint', 'wrench', 'droplets', 'car'];

    /**
     * Reglas de validación compartidas por store() y update().
     */
    private function reglas(): array
    {
        return [
            'nombre' => self::NOMBRE_RULES,
            'descripcion' => self::DESCRIPCION_RULES,
            'precio' => ['required', 'numeric', 'gt:0'],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'icono' => ['nullable', 'string', Rule::in(self::ICONOS)],
            'imagen' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

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
        return view('servicios.create', ['iconos' => self::ICONOS]);
    }

    /**
     * Valida y persiste un nuevo servicio.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->reglas());

        $data = $request->only('nombre', 'descripcion', 'precio');
        $data['activo'] = $request->boolean('activo', true);
        $data['orden'] = (int) ($validated['orden'] ?? 0);
        $data['icono'] = $validated['icono'] ?? 'sparkles';

        if ($request->hasFile('imagen')) {
            $result = Cloudinary::uploadApi()->upload($request->file('imagen')->getRealPath());
            $data['imagen'] = $result['secure_url'];
        }

        Servicio::create($data);

        return redirect()->route('servicios.index')
            ->with('success', 'Servicio creado correctamente.');
    }

    /**
     * Muestra el formulario de edición con datos precargados.
     * Usa Route Model Binding: Servicio $servicio
     */
    public function edit(Servicio $servicio): View
    {
        return view('servicios.edit', ['servicio' => $servicio, 'iconos' => self::ICONOS]);
    }

    /**
     * Valida y actualiza un servicio existente.
     */
    public function update(Request $request, Servicio $servicio): RedirectResponse
    {
        $validated = $request->validate($this->reglas());

        $data = $request->only('nombre', 'descripcion', 'precio');
        $data['activo'] = $request->boolean('activo', (bool) $servicio->activo);
        $data['orden'] = (int) ($validated['orden'] ?? 0);
        $data['icono'] = $validated['icono'] ?? $servicio->icono;

        if ($request->hasFile('imagen')) {
            if ($servicio->imagen) {
                $this->destruirImagen($servicio->imagen);
            }

            $result = Cloudinary::uploadApi()->upload($request->file('imagen')->getRealPath());
            $data['imagen'] = $result['secure_url'];
        }

        $servicio->update($data);

        return redirect()->route('servicios.index')
            ->with('success', 'Servicio actualizado correctamente.');
    }

    /**
     * Alterna el estado activo de un servicio vía AJAX.
     */
    public function toggleStatus(Servicio $servicio): JsonResponse
    {
        try {
            app(AuditService::class)->anotarAccion('toggle estado');

            $servicio->activo = ! $servicio->activo;
            $servicio->save();

            return response()->json([
                'success' => true,
                'activo' => (bool) $servicio->activo,
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
     * Elimina un servicio si no tiene lavados asociados.
     * La imagen se destruye en Cloudinary fuera de la transacción.
     */
    public function destroy(Servicio $servicio): RedirectResponse
    {
        if ($servicio->imagen) {
            $this->destruirImagen($servicio->imagen);
        }

        if ($servicio->lavados()->exists()) {
            return redirect()->route('servicios.index')
                ->with('error', 'No se puede eliminar el servicio porque tiene lavados asociados.');
        }

        $servicio->delete();

        return redirect()->route('servicios.index')
            ->with('success', 'Servicio eliminado correctamente.');
    }

    /**
     * Elimina la imagen del servicio, ya sea en Cloudinary o en disco público.
     */
    private function destruirImagen(string $imagen): void
    {
        if (str_starts_with($imagen, 'http')) {
            $parts = explode('/', $imagen);
            $filename = end($parts);
            $publicId = pathinfo($filename, PATHINFO_FILENAME);
            try {
                Cloudinary::uploadApi()->destroy($publicId);
            } catch (\Exception $e) {
                // Silencioso: no bloquea el CRUD si el archivo no existe.
            }
        } elseif (Storage::disk('public')->exists($imagen)) {
            Storage::disk('public')->delete($imagen);
        }
    }
}
