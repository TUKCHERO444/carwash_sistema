<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\EgresoCaja;
use App\Models\Venta;
use App\Models\CambioAceite;
use App\Models\Ingreso;
use Illuminate\Support\Facades\DB;

class CajaService
{
    /**
     * Obtiene la caja actualmente abierta, o null si no existe ninguna.
     */
    public function getCajaActiva(): ?Caja
    {
        return Caja::abierta()->first();
    }

    /**
     * Abre una nueva caja con el monto inicial dado.
     * Usa una transacción con lockForUpdate para garantizar unicidad de caja abierta.
     *
     * @throws \RuntimeException si ya existe una caja abierta.
     */
    public function abrirCaja(float $montoInicial, int $userId): Caja
    {
        return DB::transaction(function () use ($montoInicial, $userId) {
            // Lock pesimista para evitar race conditions en aperturas simultáneas
            $cajaExistente = Caja::where('estado', 'abierta')->lockForUpdate()->first();

            if ($cajaExistente) {
                throw new \RuntimeException('Ya existe una caja abierta.');
            }

            return Caja::create([
                'user_id'        => $userId,
                'estado'         => 'abierta',
                'monto_inicial'  => $montoInicial,
                'fecha_apertura' => now(),
                'fecha_cierre'   => null,
            ]);
        });
    }

    /**
     * Cierra la caja dada, registrando la fecha y hora de cierre.
     *
     * @throws \RuntimeException si la caja ya está cerrada.
     */
    public function cerrarCaja(Caja $caja): Caja
    {
        if ($caja->estado === 'cerrada') {
            throw new \RuntimeException('La caja ya está cerrada.');
        }

        $caja->update([
            'estado'       => 'cerrada',
            'fecha_cierre' => now(),
        ]);

        return $caja->fresh();
    }

    /**
     * Registra un egreso manual en la caja activa.
     *
     * @param  Caja   $caja  La caja a la que se asocia el egreso.
     * @param  array  $data  Debe contener: monto, descripcion, tipo_pago.
     *                       Opcionalmente: user_id (si no se pasa, se usa el usuario autenticado).
     * @throws \RuntimeException si la caja está cerrada.
     */
    public function registrarEgreso(Caja $caja, array $data): EgresoCaja
    {
        if ($caja->estado === 'cerrada') {
            throw new \RuntimeException('No se pueden registrar egresos en una caja cerrada.');
        }

        return EgresoCaja::create([
            'caja_id'     => $caja->id,
            'monto'       => $data['monto'],
            'descripcion' => $data['descripcion'],
            'tipo_pago'   => $data['tipo_pago'],
            'user_id'     => $data['user_id'] ?? auth()->id(),
        ]);
    }

    /**
     * Asocia una transacción existente (venta, cambio_aceite o ingreso) a la caja dada.
     *
     * @param  string  $tipo           Tipo de transacción: 'venta', 'cambio_aceite' o 'ingreso'.
     * @param  int     $transaccionId  ID del registro a asociar.
     * @param  Caja    $caja           La caja a la que se asocia la transacción.
     * @throws \RuntimeException si la caja está cerrada o el tipo no es válido.
     */
    public function asociarTransaccion(string $tipo, int $transaccionId, Caja $caja): void
    {
        if ($caja->estado === 'cerrada') {
            throw new \RuntimeException('No se pueden asociar transacciones a una caja cerrada.');
        }

        $modelo = match ($tipo) {
            'venta'         => Venta::class,
            'cambio_aceite' => CambioAceite::class,
            'ingreso'       => Ingreso::class,
            default         => throw new \InvalidArgumentException("Tipo de transacción inválido: {$tipo}"),
        };

        $modelo::where('id', $transaccionId)->update(['caja_id' => $caja->id]);
    }

    /**
     * Calcula el resumen financiero de la caja.
     *
     * Fórmula:
     *   total_ingresos = SUM(ventas.total) + SUM(cambio_aceites.total) + SUM(ingresos.total WHERE estado='confirmado')
     *   total_egresos  = SUM(egresos_caja.monto)
     *   balance_final  = monto_inicial + total_ingresos - total_egresos
     *
     * También calcula la distribución por modo de pago para cada fuente de ingreso.
     *
     * @return array{
     *   total_ingresos: float,
     *   total_egresos: float,
     *   balance_final: float,
     *   monto_efectivo: float,
     *   monto_yape: float,
     *   monto_izipay: float,
     * }
     */
    public function calcularResumen(Caja $caja): array
    {
        $cajaId = $caja->id;

        // Totales de ingresos por fuente
        $totalVentas   = Venta::where('caja_id', $cajaId)->sum('total');
        $totalCambios  = CambioAceite::where('caja_id', $cajaId)->sum('total');
        $totalIngresos = Ingreso::where('caja_id', $cajaId)
                                ->where('estado', 'confirmado')
                                ->sum('total');

        $totalIngresosCaja = (float) $totalVentas
                           + (float) $totalCambios
                           + (float) $totalIngresos;

        // Total de egresos
        $totalEgresos = (float) EgresoCaja::where('caja_id', $cajaId)->sum('monto');

        // Balance final
        $balanceFinal = (float) $caja->monto_inicial + $totalIngresosCaja - $totalEgresos;

        // Distribución por modo de pago (ventas + cambios + ingresos confirmados)
        $montoEfectivo = 0.0;
        $montoYape     = 0.0;
        $montoIzipay   = 0.0;

        $fuentes = [
            Venta::where('caja_id', $cajaId)->get(),
            CambioAceite::where('caja_id', $cajaId)->get(),
            Ingreso::where('caja_id', $cajaId)->where('estado', 'confirmado')->get(),
        ];

        foreach ($fuentes as $registros) {
            foreach ($registros as $registro) {
                switch ($registro->metodo_pago) {
                    case 'efectivo':
                        $montoEfectivo += (float) $registro->total;
                        break;
                    case 'yape':
                        $montoYape += (float) $registro->total;
                        break;
                    case 'izipay':
                        $montoIzipay += (float) $registro->total;
                        break;
                    case 'mixto':
                        $montoEfectivo += (float) ($registro->monto_efectivo ?? 0);
                        $montoYape     += (float) ($registro->monto_yape ?? 0);
                        $montoIzipay   += (float) ($registro->monto_izipay ?? 0);
                        break;
                }
            }
        }

        return [
            'total_ingresos'             => $totalIngresosCaja,
            'total_egresos'              => $totalEgresos,
            'balance_final'              => $balanceFinal,
            'monto_efectivo'             => $montoEfectivo,
            'monto_yape'                 => $montoYape,
            'monto_izipay'               => $montoIzipay,
            'total_ventas'               => (float) $totalVentas,
            'total_cambios'              => (float) $totalCambios,
            'total_ingresos_vehiculares' => (float) $totalIngresos,
        ];
    }
}
