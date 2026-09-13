<?php

namespace App\Http\Controllers;

use App\Services\AsistenciaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsistenciaController extends Controller
{
    public function __construct(
        private AsistenciaService $asistenciaService,
    ) {}

    /**
     * Devuelve la vista del panel de asistencia (calendario del mes actual).
     */
    public function index(): View
    {
        $totalActivos = $this->asistenciaService->trabajadoresActivos()->count();

        return view('asistencia.index', compact('totalActivos'));
    }

    /**
     * Resumen de asistencia de una fecha concreta (AJAX).
     */
    public function porFecha(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        return response()->json($this->asistenciaService->resumenDeFecha($validated['fecha']));
    }

    /**
     * Dias del mes con marcas de asistencia (AJAX). Alimenta los indicadores del calendario.
     */
    public function porMes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mes' => ['required', 'date_format:Y-m'],
        ]);

        return response()->json($this->asistenciaService->estadoPorMes($validated['mes']));
    }

    /**
     * Sincroniza las marcas de asistencia de una fecha (AJAX, full-sync).
     */
    public function marcar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'marcas' => ['nullable', 'array'],
            'marcas.*' => ['required', 'date_format:H:i'],
        ]);

        $resumen = $this->asistenciaService->sincronizarMarca(
            $validated['fecha'],
            $validated['marcas'] ?? []
        );

        return response()->json([
            'success' => true,
            'message' => 'Asistencia registrada correctamente.',
            ...$resumen,
        ]);
    }
}
