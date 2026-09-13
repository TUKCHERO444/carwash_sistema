<?php

namespace App\Http\Controllers;

use App\Models\ContenidoWeb;
use App\Models\Marca;
use App\Services\AuditService;
use App\Services\ContenidoWebService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContenidoWebController extends Controller
{
    /**
     * Muestra la pantalla única de gestión de contenidos de la web.
     */
    public function edit(): View
    {
        $contenido = app(ContenidoWebService::class);
        $marcas = Marca::orderBy('nombre')->get();

        return view('contenido-web.edit', compact('contenido', 'marcas'));
    }

    /**
     * Persiste los contenidos del panel (toggles, textos y curaduría de marcas).
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'productos_titulo' => ['nullable', 'string', 'max:120'],
            'productos_intro' => ['nullable', 'string', 'max:120'],
            'servicios_titulo' => ['nullable', 'string', 'max:120'],
            'servicios_intro' => ['nullable', 'string', 'max:120'],
            'marcas_titulo' => ['nullable', 'string', 'max:120'],
            'marcas_intro' => ['nullable', 'string', 'max:120'],
            'marcas_web' => ['nullable', 'array'],
            'marcas_web.*' => ['integer', 'exists:marcas,id'],
            'marcas_orden' => ['nullable', 'array'],
            'marcas_orden.*' => ['integer', 'min:0'],
        ]);

        $data = [];

        foreach (ContenidoWebService::DEFAULTS as $clave => $meta) {
            if ($meta['tipo'] === 'bool') {
                $data[$clave] = $request->boolean($clave) ? '1' : '0';
            } elseif ($meta['tipo'] === 'string') {
                $data[$clave] = trim((string) $request->input($clave, ''));
            }
        }

        $marcasWeb = $request->input('marcas_web', []);
        $marcasOrden = $request->input('marcas_orden', []);
        $curaduria = [];

        foreach ($marcasWeb as $marcaId) {
            $curaduria[] = [
                'marca_id' => (int) $marcaId,
                'orden' => (int) ($marcasOrden[$marcaId] ?? 0),
            ];
        }

        $data['marcas_web'] = json_encode($curaduria);

        app(AuditService::class)->anotarAccion('actualizar contenidos web');

        foreach ($data as $clave => $valor) {
            ContenidoWeb::updateOrCreate(
                ['clave' => $clave],
                ['valor' => $valor, 'tipo' => ContenidoWebService::DEFAULTS[$clave]['tipo']]
            );
        }

        return redirect()->route('contenido-web.edit')
            ->with('success', 'Contenidos de la web actualizados correctamente.');
    }
}
