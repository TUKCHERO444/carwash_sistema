<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    /**
     * RUC peruano: exactamente 11 dígitos numéricos, sin letras ni símbolos.
     */
    private const RUC_RULE = 'digits:11';

    /**
     * Razón social: letras, números, espacios y los signos habituales de una
     * denominação social (puntos, comas, paréntesis, ampersand, guiones, barras).
     */
    private const RAZON_SOCIAL_RULE = 'regex:/^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ .,&()\'"\-\/+]+$/u';

    /**
     * Dirección: letras, números, espacios y signos de urbanization.
     */
    private const DIRECCION_RULE = 'regex:/^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ .,\-#]+$/u';

    /**
     * Mensajes de validación en español.
     */
    private const MENSAJES = [
        'ruc.required' => 'El RUC es obligatorio.',
        'ruc.digits' => 'El RUC debe tener exactamente 11 dígitos numéricos.',
        'ruc.unique' => 'Ya existe un proveedor registrado con este RUC.',
        'razon_social.required' => 'La razón social es obligatoria.',
        'razon_social.regex' => 'La razón social solo admite letras, números y signos básicos.',
        'estado.required' => 'El estado es obligatorio.',
        'estado.in' => 'El estado debe ser 1 (activo) u 0 (inactivo).',
    ];

    /**
     * Reglas de validación compartidas entre store y update.
     *
     * @param  int|null  $ignoreId  Id a ignorar en la regla unique (update).
     */
    private function reglas(?int $ignoreId = null): array
    {
        $unicidad = $ignoreId
            ? 'unique:proveedores,ruc,'.$ignoreId
            : 'unique:proveedores,ruc';

        return [
            'ruc' => ['required', 'string', self::RUC_RULE, $unicidad],
            'razon_social' => ['required', 'string', 'max:150', self::RAZON_SOCIAL_RULE],
            'direccion' => ['nullable', 'string', 'max:200', self::DIRECCION_RULE],
            'estado_tributario' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+$/u'],
            'condicion' => ['nullable', 'string', 'max:100'],
            'estado' => ['required', 'size:1', 'in:0,1'],
        ];
    }

    /**
     * Display a listing of all proveedores ordered by razón social.
     */
    public function index(): View
    {
        $proveedores = Proveedor::orderBy('razon_social')->paginate(10);

        return view('proveedores.index', compact('proveedores'));
    }

    /**
     * Show the form for creating a new proveedor.
     */
    public function create(): View
    {
        return view('proveedores.create');
    }

    /**
     * Store a newly created proveedor in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->reglas(), self::MENSAJES);

        Proveedor::create([
            'ruc' => $validated['ruc'],
            'razon_social' => $validated['razon_social'],
            'direccion' => $validated['direccion'] ?? null,
            'estado_tributario' => $validated['estado_tributario'] ?? null,
            'condicion' => $validated['condicion'] ?? null,
            'estado' => $validated['estado'],
        ]);

        return redirect()->route('proveedores.index')
            ->with('success', 'Proveedor creado correctamente.');
    }

    /**
     * Show the form for editing an existing proveedor.
     */
    public function edit(Proveedor $proveedor): View
    {
        return view('proveedores.edit', compact('proveedor'));
    }

    /**
     * Update the specified proveedor in the database.
     */
    public function update(Request $request, Proveedor $proveedor): RedirectResponse
    {
        $validated = $request->validate($this->reglas($proveedor->id), self::MENSAJES);

        $proveedor->update([
            'ruc' => $validated['ruc'],
            'razon_social' => $validated['razon_social'],
            'direccion' => $validated['direccion'] ?? null,
            'estado_tributario' => $validated['estado_tributario'] ?? null,
            'condicion' => $validated['condicion'] ?? null,
            'estado' => $validated['estado'],
        ]);

        return redirect()->route('proveedores.index')
            ->with('success', 'Proveedor actualizado correctamente.');
    }

    /**
     * Remove the specified proveedor from the database.
     */
    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        $proveedor->delete();

        return redirect()->route('proveedores.index')
            ->with('success', 'Proveedor eliminado correctamente.');
    }
}
