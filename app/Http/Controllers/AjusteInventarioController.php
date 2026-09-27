<?php

namespace App\Http\Controllers;

use App\Models\AjusteInventario;
use App\Models\Producto;
use App\Services\AjusteInventarioService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AjusteInventarioController extends Controller
{
    private const TIPOS = [
        'positivo' => 'Ajuste Positivo',
        'negativo' => 'Ajuste Negativo',
        'merma' => 'Merma',
        'daño' => 'Daño',
        'conteo_fisico' => 'Conteo Físico',
    ];

    private const MENSAJES = [
        'tipo.required' => 'Debe seleccionar un tipo de ajuste.',
        'tipo.in' => 'El tipo de ajuste no es válido.',
        'motivo.required' => 'El motivo es obligatorio para este tipo de ajuste.',
        'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
        'lineas.required' => 'Debe agregar al menos un producto.',
        'lineas.min' => 'Debe agregar al menos un producto.',
        'lineas.*.producto_id.required' => 'Cada línea debe tener un producto.',
        'lineas.*.producto_id.exists' => 'Uno de los productos seleccionados no existe.',
        'lineas.*.cantidad.required_without:conteo_fisico' => 'Cada línea debe tener una cantidad.',
        'lineas.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
        'lineas.*.cantidad.not_in' => 'La cantidad no puede ser cero.',
        'lineas.*.conteo_fisico.required_without:cantidad' => 'Ingrese la cantidad contada.',
        'lineas.*.conteo_fisico.integer' => 'La cantidad contada debe ser un número entero.',
        'lineas.*.conteo_fisico.min' => 'La cantidad contada no puede ser negativa.',
    ];

    /**
     * Listado de ajustes con filtros por tipo y fecha.
     */
    public function index(Request $request): View
    {
        $ajustes = AjusteInventario::query()
            ->with('user')
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->string('tipo')->toString()))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('ajustes.index', compact('ajustes'));
    }

    public function create(): View
    {
        $productos = Producto::where('activo', 1)->orderBy('nombre')->get(['id', 'nombre', 'stock']);

        return view('ajustes.create', compact('productos'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->reglas(), self::MENSAJES);

        try {
            $ajuste = app(AjusteInventarioService::class)->crear($validated, $request->user());

            return redirect()->route('ajustes.show', $ajuste)
                ->with('success', 'Ajuste registrado correctamente.');
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error al registrar el ajuste: '.$e->getMessage());
        }
    }

    public function show(AjusteInventario $ajuste): View
    {
        $ajuste->load(['user', 'detalles.producto', 'detalles.kardex']);

        return view('ajustes.show', compact('ajuste'));
    }

    /**
     * Reglas de validación compartidas.
     */
    private function reglas(): array
    {
        $tipos = implode(',', array_keys(self::TIPOS));

        return [
            'tipo' => ['required', 'in:'.$tipos],
            'motivo' => ['nullable', 'string', 'max:500'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'lineas.*.cantidad' => ['required_without:conteo_fisico', 'integer', 'not_in:0', 'min:-9999', 'max:9999'],
            'lineas.*.conteo_fisico' => ['required_without:cantidad', 'integer', 'min:0', 'max:99999'],
        ];
    }
}
