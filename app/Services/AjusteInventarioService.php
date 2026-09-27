<?php

namespace App\Services;

use App\Models\AjusteInventario;
use App\Models\DetalleAjusteInventario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AjusteInventarioService
{
    /**
     * Crea un ajuste de inventario completo con sus líneas y movimientos Kardex.
     *
     * @param  array  $data  Datos validados del formulario
     * @param  User  $user  Usuario que realiza el ajuste
     */
    public function crear(array $data, User $user): AjusteInventario
    {
        return DB::transaction(function () use ($data, $user) {
            // 1. Preparar líneas (incluye lógica de conteo_fisico)
            $lineas = $this->prepararLineas($data['lineas']);

            if (empty($lineas)) {
                throw new \RuntimeException('No hay líneas válidas para procesar.');
            }

            // 2. Crear cabecera
            $correlativo = $this->siguienteCorrelativo();
            $ajuste = AjusteInventario::create([
                'correlativo' => $correlativo,
                'tipo' => $data['tipo'],
                'motivo' => $data['motivo'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
                'user_id' => $user->id,
            ]);

            // 3. Procesar cada línea con lock
            foreach ($lineas as $linea) {
                $this->procesarLinea($ajuste, $linea);
            }

            // Auditoría
            app(AuditService::class)->anotarAccion('crear ajuste');

            return $ajuste->refresh();
        });
    }

    /**
     * Prepara las líneas normalizando conteo_fisico a cantidad con signo.
     *
     * @return array<int, array{producto_id: int, cantidad: int}>
     */
    private function prepararLineas(array $lineas): array
    {
        return collect($lineas)
            ->map(function ($linea) {
                if (isset($linea['conteo_fisico']) && $linea['conteo_fisico'] !== '') {
                    $producto = Producto::findOrFail($linea['producto_id']);
                    $delta = (int) $linea['conteo_fisico'] - $producto->stock;
                    if ($delta === 0) {
                        return null; // No hay cambio, se filtrará después
                    }

                    return [
                        'producto_id' => (int) $linea['producto_id'],
                        'cantidad' => $delta,
                    ];
                }

                $cantidad = (int) ($linea['cantidad'] ?? 0);
                if ($cantidad === 0) {
                    return null;
                }

                return [
                    'producto_id' => (int) $linea['producto_id'],
                    'cantidad' => $cantidad,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Procesa una línea de ajuste dentro de la transacción.
     */
    private function procesarLinea(AjusteInventario $ajuste, array $linea): void
    {
        $producto = Producto::lockForUpdate()->findOrFail($linea['producto_id']);

        if (! $producto->activo) {
            throw new \RuntimeException("El producto {$producto->nombre} está inactivo.");
        }

        $stockAntes = $producto->stock;
        $cantidad = (int) $linea['cantidad'];
        $stockDespues = $stockAntes + $cantidad;

        if ($stockDespues < 0) {
            throw new \RuntimeException(
                "Stock insuficiente para {$producto->nombre}. ".
                "Disponible: {$stockAntes}, requerido: ".abs($cantidad)
            );
        }

        // Determinar tipo de movimiento y costo
        [$tipoMov, $fuente, $costoUnitario] = $this->resolveMovimiento($ajuste->tipo, $cantidad);

        // Actualizar stock e inventario
        $producto->update([
            'stock' => $stockDespues,
            'inventario' => $stockDespues,
        ]);

        // Registrar Kardex
        $cpp = app(CostoPromedioService::class)->obtenerCpp($producto);
        $costoUnitario = $this->obtenerCostoParaMovimiento($ajuste->tipo, $cantidad, $cpp);

        app(KardexService::class)->registrar(
            $producto,
            $cantidad >= 0 ? 'entrada' : 'salida',
            abs($cantidad),
            $stockAntes,
            $stockAntes + $cantidad,
            $this->fuenteParaTipo($ajuste->tipo, $cantidad),
            $ajuste->correlativo,
            $costoUnitario,
        );

        // Detalle
        DetalleAjusteInventario::create([
            'ajuste_id' => $ajuste->id,
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'stock_antes' => $stockAntes,
            'stock_despues' => $stockDespues,
        ]);
    }

    /**
     * Determina el tipo de movimiento Kardex y la fuente según tipo de ajuste y signo de cantidad.
     *
     * @return array{tipo: string, fuente: string, costo: float}
     */
    private function resolveMovimiento(string $tipoAjuste, int $cantidad): array
    {
        $esEntrada = $cantidad > 0;

        return match ($tipoAjuste) {
            'positivo' => ['entrada', 'ajuste_positivo'],
            'negativo' => ['salida', 'ajuste_negativo'],
            'merma' => ['salida', 'merma'],
            'daño' => ['salida', 'daño'],
            'conteo_fisico' => [
                $esEntrada ? 'entrada' : 'salida',
                'conteo_fisico',
            ],
            default => throw new \InvalidArgumentException("Tipo de ajuste inválido: {$tipoAjuste}"),
        };
    }

    /**
     * Obtiene la fuente Kardex según tipo y signo de cantidad.
     */
    private function fuenteParaTipo(string $tipoAjuste, int $cantidad): string
    {
        $esEntrada = $cantidad > 0;

        return match ($tipoAjuste) {
            'positivo' => 'ajuste_positivo',
            'negativo' => 'ajuste_negativo',
            'merma' => 'merma',
            'daño' => 'daño',
            'conteo_fisico' => 'conteo_fisico',
            default => throw new \InvalidArgumentException('Tipo inválido'),
        };
    }

    /**
     * Obtiene el costo unitario para el movimiento Kardex.
     */
    private function obtenerCostoParaMovimiento(string $tipoAjuste, int $cantidad, float $cpp): float
    {
        $esEntrada = $cantidad > 0;

        // Solo las entradas con costo conocido actualizan CPP y llevan costo
        if ($tipoAjuste === 'positivo' && $esEntrada) {
            // Para ajustes positivos, se usa CPP actual como referencia de costo
            return $cpp;
        }

        // Salidas: usan CPP actual
        return $cpp;
    }

    /**
     * Genera el siguiente correlativo AJU-XXXX.
     */
    private function siguienteCorrelativo(): string
    {
        $max = AjusteInventario::where('correlativo', 'like', 'AJU-%')
            ->max('correlativo');
        $next = $max ? ((int) substr($max, 4)) + 1 : 1;

        return 'AJU-'.str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
