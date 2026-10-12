<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MetodoPagoController extends Controller
{
    /**
     * Display a paginated listing de métodos de pago.
     */
    public function index(): View
    {
        $metodosPago = MetodoPago::orderBy('orden')->orderBy('id')->paginate(10);

        return view('metodos-pago.index', compact('metodosPago'));
    }

    /**
     * Show the form for creating a new método de pago.
     */
    public function create(): View
    {
        return view('metodos-pago.create');
    }

    /**
     * Store a newly created método de pago in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'codigo' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/', 'unique:metodos_pago,codigo'],
            'nombre' => ['required', 'string', 'max:50'],
            'activo' => ['nullable', 'boolean'],
            'es_efectivo' => ['nullable', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($request) {
            $metodo = MetodoPago::create([
                'codigo' => MetodoPago::normalizarCodigo($request->codigo),
                'nombre' => $request->nombre,
                'activo' => $request->boolean('activo'),
                'es_efectivo' => $request->boolean('es_efectivo'),
                'orden' => $request->input('orden', 0),
            ]);

            if ($metodo->es_efectivo) {
                MetodoPago::where('id', '!=', $metodo->id)->update(['es_efectivo' => false]);
            }
        });

        return redirect()->route('metodos-pago.index')
            ->with('success', 'Método de pago creado correctamente.');
    }

    /**
     * Show the form for editing an existing método de pago.
     */
    public function edit(MetodoPago $metodoPago): View
    {
        return view('metodos-pago.edit', compact('metodoPago'));
    }

    /**
     * Update the specified método de pago in the database.
     */
    public function update(Request $request, MetodoPago $metodoPago): RedirectResponse
    {
        $request->validate([
            'codigo' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/', Rule::unique('metodos_pago', 'codigo')->ignore($metodoPago->id)],
            'nombre' => ['required', 'string', 'max:50'],
            'activo' => ['nullable', 'boolean'],
            'es_efectivo' => ['nullable', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($request, $metodoPago) {
            $metodoPago->update([
                'codigo' => MetodoPago::normalizarCodigo($request->codigo),
                'nombre' => $request->nombre,
                'activo' => $request->boolean('activo'),
                'es_efectivo' => $request->boolean('es_efectivo'),
                'orden' => $request->input('orden', 0),
            ]);

            if ($metodoPago->es_efectivo) {
                MetodoPago::where('id', '!=', $metodoPago->id)->update(['es_efectivo' => false]);
            }
        });

        return redirect()->route('metodos-pago.index')
            ->with('success', 'Método de pago actualizado correctamente.');
    }

    /**
     * Remove the specified método de pago from the database.
     * Blocked if the método has associated pagos.
     */
    public function destroy(MetodoPago $metodoPago): RedirectResponse
    {
        if ($metodoPago->pagos()->exists()) {
            return redirect()->route('metodos-pago.index')
                ->with('error', 'No se puede eliminar el método de pago porque tiene pagos asociados. Desactívelo en su lugar.');
        }

        $metodoPago->delete();

        return redirect()->route('metodos-pago.index')
            ->with('success', 'Método de pago eliminado correctamente.');
    }
}
