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
        ], [
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
            'fecha.date_format' => 'El formato de la fecha debe ser YYYY-MM-DD.',
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
     * Solo se permite interactuar (modificar) con la fecha de hoy:
     * las fechas pasadas son de solo consulta y las futuras no existen aún.
     */
    public function marcar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d', 'date_equals:today'],
            'marcas' => ['nullable', 'array'],
            'marcas.*' => ['required', 'date_format:H:i'],
        ], [
            'fecha.date_equals' => 'Solo se puede registrar o modificar la asistencia del día actual.',
            'fecha.date_format' => 'El formato de la fecha debe ser YYYY-MM-DD.',
            'marcas.*.date_format' => 'Cada hora de entrada debe tener formato HH:MM.',
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
