<?php

namespace App\Http\Controllers\Cuenta;

use App\Http\Controllers\Controller;
use App\Models\CarritoItem;
use App\Services\AuditService;
use App\Services\CarritoService;
use App\Services\Reportes\ReportePdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Carrito de cotización del cliente (solo lectura/escritura propia).
 * No toca stock: solo valida topes vigentes.
 */
class CarritoController extends Controller
{
    public function __construct(
        private CarritoService $carritoService,
        private AuditService $auditService,
    ) {}

    /**
     * Lista el carrito propio con subtotales y total.
     */
    public function index(Request $request): View
    {
        $resumen = $this->carritoService->resumen($request->user());

        return view('cuenta.carrito.index', [
            'items' => $resumen['items'],
            'total' => $resumen['total'],
        ]);
    }

    /**
     * Agrega cantidad al carrito (suma si el ítem existe).
     */
    public function agregar(Request $request): RedirectResponse|JsonResponse
    {
        $validados = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        try {
            $item = $this->carritoService->agregar($request->user(), $validados['producto_id'], $validados['cantidad']);
        } catch (ValidationException $e) {
            return $this->errorStock($request, $e);
        }

        $resumen = $this->carritoService->resumen($request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'conteo' => $resumen['conteo'],
                'total' => $resumen['total'],
                'mensaje' => "Agregado: {$item->producto->nombre}.",
            ]);
        }

        return back()->with('success', "Agregado al carrito: {$item->producto->nombre}.");
    }

    /**
     * Fija la cantidad de un ítem propio.
     */
    public function actualizar(Request $request, CarritoItem $item): RedirectResponse|JsonResponse
    {
        $validados = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        try {
            $this->carritoService->actualizarCantidad($request->user(), $item, $validados['cantidad']);
        } catch (ValidationException $e) {
            return $this->errorStock($request, $e);
        }

        $resumen = $this->carritoService->resumen($request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'conteo' => $resumen['conteo'],
                'total' => $resumen['total'],
                'mensaje' => 'Cantidad actualizada.',
            ]);
        }

        return back()->with('success', 'Cantidad actualizada.');
    }

    /**
     * Quita un ítem propio.
     */
    public function quitar(Request $request, CarritoItem $item): RedirectResponse|JsonResponse
    {
        $this->carritoService->quitar($request->user(), $item);

        if ($request->expectsJson()) {
            $resumen = $this->carritoService->resumen($request->user());

            return response()->json([
                'ok' => true,
                'conteo' => $resumen['conteo'],
                'total' => $resumen['total'],
                'mensaje' => 'Producto quitado del carrito.',
            ]);
        }

        return back()->with('success', 'Producto quitado del carrito.');
    }

    /**
     * Vacía el carrito propio.
     */
    public function vaciar(Request $request): RedirectResponse
    {
        $this->carritoService->vaciar($request->user());

        return back()->with('success', 'Carrito vaciado.');
    }

    /**
     * Descarga el PDF proforma del carrito.
     */
    public function pdf(Request $request, ReportePdfService $pdfService)
    {
        $user = $request->user();
        $resumen = $this->carritoService->resumen($user);

        if ($resumen['items']->isEmpty()) {
            return redirect()->route('cuenta.carrito.index')
                ->with('info', 'Tu carrito está vacío.');
        }

        $this->auditService->registrar($user->carrito, 'carrito_pdf', null, null, 'cuenta');

        return $pdfService->descargar(
            'cuenta.carrito.pdf',
            'carrito-'.now()->format('Ymd-Hi').'.pdf',
            [
                'user' => $user,
                'items' => $resumen['items'],
                'total' => $resumen['total'],
                'fecha' => now(),
            ]
        );
    }

    /**
     * Redirige a wa.me con el resumen + enlace al PDF del carrito.
     * wa.me solo admite texto, no adjuntos: el PDF viaja como enlace.
     */
    public function whatsapp(Request $request): RedirectResponse
    {
        try {
            $url = $this->carritoService->enlaceWhatsApp($request->user());
        } catch (ValidationException $e) {
            return redirect()->route('cuenta.carrito.index')
                ->withErrors($e->errors());
        }

        return redirect()->away($url);
    }

    /**
     * Error de tope de stock: JSON si fetch, redirect con errores si form.
     */
    private function errorStock(Request $request, ValidationException $e): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        throw $e;
    }
}
