<?php

namespace App\Services;

use App\Models\Carrito;
use App\Models\CarritoItem;
use App\Models\Producto;
use App\Models\User;
use App\Services\Reportes\ReportePdfService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Único punto de escritura del carrito de cotización (análogo a
 * CajaService para caja). Valida contra stock vigente sin reservar
 * ni descontar: el descuento sigue solo en VentaController::store.
 */
class CarritoService
{
    public function __construct(
        private AuditService $auditService,
        private ReportePdfService $pdfService,
    ) {}

    /**
     * Agrega cantidad a un ítem (suma si existe; snapshot solo al crear).
     *
     * @throws ValidationException
     */
    public function agregar(User $user, int $productoId, int $cantidad): CarritoItem
    {
        return DB::transaction(function () use ($user, $productoId, $cantidad) {
            $producto = Producto::where('id', $productoId)->lockForUpdate()->first();

            $this->validarProducto($producto, $cantidad);

            $carrito = Carrito::firstOrCreate(['user_id' => $user->id]);

            $item = $carrito->items()->where('producto_id', $producto->id)->lockForUpdate()->first();

            $nueva = ($item?->cantidad ?? 0) + $cantidad;

            if ($nueva > (int) $producto->stock) {
                throw ValidationException::withMessages([
                    'cantidad' => "Solo quedan {$producto->stock} unidades de {$producto->nombre}.",
                ]);
            }

            if ($item === null) {
                $item = $carrito->items()->create([
                    'producto_id' => $producto->id,
                    'cantidad' => $nueva,
                    'precio_unitario' => $producto->precio_venta,
                ]);
            } else {
                $item->update(['cantidad' => $nueva]);
            }

            $this->auditService->registrar($item, 'carrito_agregar', null, null, 'cuenta');

            return $item->refresh();
        });
    }

    /**
     * Fija la cantidad de un ítem propio.
     *
     * @throws ValidationException
     */
    public function actualizarCantidad(User $user, CarritoItem $item, int $cantidad): CarritoItem
    {
        return DB::transaction(function () use ($user, $item, $cantidad) {
            $this->propio($user, $item);

            $producto = Producto::where('id', $item->producto_id)->lockForUpdate()->first();

            $this->validarProducto($producto, $cantidad);

            $item->update(['cantidad' => $cantidad]);

            $this->auditService->registrar($item, 'carrito_actualizar', null, null, 'cuenta');

            return $item->refresh();
        });
    }

    /**
     * Quita un ítem propio.
     */
    public function quitar(User $user, CarritoItem $item): void
    {
        $this->propio($user, $item);

        $item->delete();

        $this->auditService->registrar($item, 'carrito_quitar', null, null, 'cuenta');
    }

    /**
     * Vacía el carrito propio.
     */
    public function vaciar(User $user): void
    {
        $carrito = $user->carrito;

        if ($carrito === null) {
            return;
        }

        $carrito->items()->delete();

        $this->auditService->registrar($carrito, 'carrito_vaciar', null, null, 'cuenta');
    }

    /**
     * Resumen para vista, header y PDF (ítems con producto).
     *
     * @return array{items: Collection<int, CarritoItem>, total: float, conteo: int}
     */
    public function resumen(User $user): array
    {
        $carrito = $user->carrito()->with('items.producto')->first();

        $items = $carrito?->items ?? collect();

        return [
            'items' => $items,
            'total' => round($items->sum(fn (CarritoItem $item) => $item->cantidad * (float) $item->precio_unitario), 2),
            'conteo' => (int) $items->sum('cantidad'),
        ];
    }

    /**
     * Número del local en formato wa.me (51 + 9 dígitos PE).
     */
    public static function numeroWhatsApp(): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) config('services.whatsapp.pedidos'));

        if (strlen($digitos) === 9 && str_starts_with($digitos, '9')) {
            return '51'.$digitos;
        }

        if (strlen($digitos) === 11 && str_starts_with($digitos, '51')) {
            return $digitos;
        }

        return null;
    }

    /**
     * Genera el PDF del carrito en disco público (un archivo por
     * usuario, nombre aleatorio) y devuelve el enlace wa.me con el
     * resumen + URL del PDF. wa.me solo admite texto, no adjuntos.
     *
     * @throws ValidationException
     */
    public function enlaceWhatsApp(User $user): string
    {
        $numero = self::numeroWhatsApp();

        if ($numero === null) {
            throw ValidationException::withMessages([
                'whatsapp' => 'El envío por WhatsApp no está configurado.',
            ]);
        }

        $resumen = $this->resumen($user);

        if ($resumen['items']->isEmpty()) {
            throw ValidationException::withMessages([
                'whatsapp' => 'Tu carrito está vacío.',
            ]);
        }

        $archivo = "carrito-pdfs/carrito-{$user->id}-".Str::random(8).'.pdf';

        Storage::disk('public')->makeDirectory('carrito-pdfs');

        // Un solo archivo vigente por usuario.
        foreach (Storage::disk('public')->files('carrito-pdfs') as $previo) {
            if (str_starts_with(basename($previo), "carrito-{$user->id}-")) {
                Storage::disk('public')->delete($previo);
            }
        }

        $this->pdfService->guardar('cuenta.carrito.pdf', Storage::disk('public')->path($archivo), [
            'user' => $user,
            'items' => $resumen['items'],
            'total' => $resumen['total'],
            'fecha' => now(),
        ]);

        $lineas = ["Hola Carwash El Chinito, soy {$user->name} ({$user->email}). Mi pedido:"];

        foreach ($resumen['items']->take(30) as $item) {
            $lineas[] = "• {$item->cantidad}× {$item->producto->nombre} — S/ ".number_format((float) $item->subtotal, 2, '.', '');
        }

        if ($resumen['items']->count() > 30) {
            $lineas[] = '…y '.($resumen['items']->count() - 30).' líneas más (ver PDF).';
        }

        $lineas[] = 'Total: S/ '.number_format((float) $resumen['total'], 2, '.', '');
        $lineas[] = 'Detalle PDF: '.url(Storage::url($archivo));

        $this->auditService->registrar($user->carrito, 'carrito_whatsapp', null, null, 'cuenta');

        return 'https://wa.me/'.$numero.'?text='.rawurlencode(implode("\n", $lineas));
    }

    public function conteoParaHeader(?User $user): int
    {
        if ($user === null || ! $user->hasRole('Cliente')) {
            return 0;
        }

        return (int) ($user->carrito?->items()->sum('cantidad') ?? 0);
    }

    /**
     * @throws ValidationException
     */
    private function validarProducto(?Producto $producto, int $cantidad): void
    {
        if ($producto === null || ! $producto->activo) {
            throw ValidationException::withMessages([
                'producto_id' => 'El producto no está disponible.',
            ]);
        }

        if ($cantidad < 1) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad mínima es 1.',
            ]);
        }

        if ($cantidad > (int) $producto->stock) {
            throw ValidationException::withMessages([
                'cantidad' => "Solo quedan {$producto->stock} unidades de {$producto->nombre}.",
            ]);
        }
    }

    /**
     * El ítem debe pertenecer al carrito del usuario (404 si es ajeno).
     */
    private function propio(User $user, CarritoItem $item): void
    {
        if ($user->carrito === null || $item->carrito_id !== $user->carrito->id) {
            abort(404);
        }
    }
}
