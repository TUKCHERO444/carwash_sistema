<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\CambioAceite;
use App\Models\EgresoCaja;
use App\Models\Lavado;
use App\Models\MetodoPago;
use App\Models\Venta;
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
                'user_id' => $userId,
                'estado' => 'abierta',
                'monto_inicial' => $montoInicial,
                'fecha_apertura' => now(),
                'fecha_cierre' => null,
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
            'estado' => 'cerrada',
            'fecha_cierre' => now(),
        ]);

        return $caja->fresh();
    }

    /**
     * Calcula el efectivo físico esperado en la caja.
     *
     * Solo representa dinero en efectivo: monto inicial + ingresos en
     * efectivo − egresos en efectivo. Yape/Izipay/transferencia/tarjeta no
     * son dinero físico y no se incluyen. Reutiliza `calcularResumen` para
     * no duplicar la consolidación de ingresos por método de pago.
     */
    public function calcularEfectivoEsperado(Caja $caja): float
    {
        $resumen = $this->calcularResumen($caja);

        $egresosEfectivo = (float) EgresoCaja::where('caja_id', $caja->id)
            ->where('tipo_pago', 'efectivo')
            ->sum('monto');

        return round((float) $caja->monto_inicial + $resumen['monto_efectivo'] - $egresosEfectivo, 2);
    }

    /**
     * Cierra la caja registrando el arqueo en la misma transacción.
     *
     * El esperado se calcula dentro de la transacción (nunca se confía en
     * valores del cliente) y la diferencia es `contado − esperado`. Una
     * diferencia no bloquea el cierre, pero queda registrada. Una caja
     * cerrada no admite modificar su arqueo (no existen rutas de edición y
     * este método rechaza cajas cerradas).
     *
     * @throws \RuntimeException si la caja ya está cerrada.
     */
    public function cerrarConArqueo(Caja $caja, float $efectivoContado, ?string $observacion, int $userId): Caja
    {
        return DB::transaction(function () use ($caja, $efectivoContado, $observacion, $userId) {
            $caja = Caja::where('id', $caja->id)->lockForUpdate()->firstOrFail();

            if ($caja->estado === 'cerrada') {
                throw new \RuntimeException('La caja ya está cerrada.');
            }

            $esperado = $this->calcularEfectivoEsperado($caja);
            $contado = round($efectivoContado, 2);

            $caja->update([
                'estado' => 'cerrada',
                'fecha_cierre' => now(),
                'efectivo_esperado' => $esperado,
                'efectivo_contado' => $contado,
                'diferencia' => round($contado - $esperado, 2),
                'arqueo_user_id' => $userId,
                'fecha_arqueo' => now(),
                'observacion_arqueo' => $observacion,
            ]);

            return $caja->fresh();
        });
    }

    /**
     * Registra un egreso manual en la caja activa.
     *
     * @param  Caja  $caja  La caja a la que se asocia el egreso.
     * @param  array  $data  Debe contener: monto, descripcion, tipo_pago.
     *                       Opcionalmente: categoria, trabajador_id, user_id
     *                       (si no se pasa, se usa el usuario autenticado).
     *                       La clasificación no altera el impacto financiero.
     *
     * @throws \RuntimeException si la caja está cerrada.
     */
    public function registrarEgreso(Caja $caja, array $data): EgresoCaja
    {
        if ($caja->estado === 'cerrada') {
            throw new \RuntimeException('No se pueden registrar egresos en una caja cerrada.');
        }

        return EgresoCaja::create([
            'caja_id' => $caja->id,
            'monto' => $data['monto'],
            'descripcion' => $data['descripcion'],
            'tipo_pago' => $data['tipo_pago'],
            'categoria' => $data['categoria'] ?? null,
            'trabajador_id' => $data['trabajador_id'] ?? null,
            'user_id' => $data['user_id'] ?? auth()->id(),
        ]);
    }

    /**
     * Asocia una transacción existente (venta, cambio_aceite o ingreso) a la caja dada.
     *
     * @param  string  $tipo  Tipo de transacción: 'venta', 'cambio_aceite' o 'ingreso'.
     * @param  int  $transaccionId  ID del registro a asociar.
     * @param  Caja  $caja  La caja a la que se asocia la transacción.
     *
     * @throws \RuntimeException si la caja está cerrada o el tipo no es válido.
     */
    public function asociarTransaccion(string $tipo, int $transaccionId, Caja $caja): void
    {
        if ($caja->estado === 'cerrada') {
            throw new \RuntimeException('No se pueden asociar transacciones a una caja cerrada.');
        }

        $modelo = match ($tipo) {
            'venta' => Venta::class,
            'cambio_aceite' => CambioAceite::class,
            'ingreso' => Lavado::class,
            default => throw new \InvalidArgumentException("Tipo de transacción inválido: {$tipo}"),
        };

        $modelo::where('id', $transaccionId)->update(['caja_id' => $caja->id]);
    }

    /**
     * Calcula los totales mínimos de una caja.
     *
     * Es la variante segura para el panel operativo: no carga relaciones ni
     * distribuye ingresos por método de pago.
     *
     * @return array{
     *   total_ingresos: float,
     *   total_egresos: float,
     *   total_ventas: float,
     *   total_cambios: float,
     *   total_ingresos_vehiculares: float,
     * }
     */
    public function calcularTotales(Caja $caja): array
    {
        $cajaId = $caja->id;

        $totalVentas = (float) Venta::where('caja_id', $cajaId)->sum('total');
        $totalCambios = (float) CambioAceite::where('caja_id', $cajaId)->sum('total');
        $totalIngresosVehiculares = (float) Lavado::where('caja_id', $cajaId)
            ->where('estado', 'confirmado')
            ->sum('total');
        $totalEgresos = (float) EgresoCaja::where('caja_id', $cajaId)->sum('monto');

        return [
            'total_ingresos' => $totalVentas + $totalCambios + $totalIngresosVehiculares,
            'total_egresos' => $totalEgresos,
            'total_ventas' => $totalVentas,
            'total_cambios' => $totalCambios,
            'total_ingresos_vehiculares' => $totalIngresosVehiculares,
        ];
    }

    /**
     * Calcula el resumen financiero de la caja.
     *
     * Fórmula:
     *   total_ingresos = SUM(ventas.total) + SUM(cambio_aceites.total) + SUM(ingresos.total WHERE estado='confirmado')
     *   total_egresos  = SUM(egresos_caja.monto)
     *   balance_final  = monto_inicial + total_ingresos - total_egresos
     *
     * También calcula la distribución por método de pago para cada fuente de ingreso.
     *
     * @return array{
     *   total_ingresos: float,
     *   total_egresos: float,
     *   balance_final: float,
     *   monto_efectivo: float,
     *   monto_yape: float,
     *   monto_izipay: float,
     *   por_metodo: array<string, float>,
     * }
     */
    public function calcularResumen(Caja $caja): array
    {
        $totales = $this->calcularTotales($caja);
        $cajaId = $caja->id;

        $totalVentas = $totales['total_ventas'];
        $totalCambios = $totales['total_cambios'];
        $totalIngresos = $totales['total_ingresos_vehiculares'];
        $totalIngresosCaja = $totales['total_ingresos'];
        $totalEgresos = $totales['total_egresos'];

        // Balance final
        $balanceFinal = (float) $caja->monto_inicial + $totalIngresosCaja - $totalEgresos;

        // Distribución por método de pago desde las líneas (ventas + cambios + lavados confirmados).
        $porMetodo = [];
        $codigosEfectivo = MetodoPago::where('es_efectivo', true)->pluck('codigo')->all();

        $fuentes = [
            Venta::where('caja_id', $cajaId)->with('pagos.metodoPago')->get(),
            CambioAceite::where('caja_id', $cajaId)->with('pagos.metodoPago')->get(),
            Lavado::where('caja_id', $cajaId)->where('estado', 'confirmado')->with('pagos.metodoPago')->get(),
        ];

        foreach ($fuentes as $registros) {
            foreach ($registros as $registro) {
                foreach ($registro->pagos as $pago) {
                    $codigo = $pago->metodoPago?->codigo ?? '—';
                    $porMetodo[$codigo] = round(($porMetodo[$codigo] ?? 0) + (float) $pago->monto, 2);
                }
            }
        }

        $montoEfectivo = 0.0;
        foreach ($porMetodo as $codigo => $monto) {
            if (in_array($codigo, $codigosEfectivo, true)) {
                $montoEfectivo += $monto;
            }
        }

        return [
            'total_ingresos' => $totalIngresosCaja,
            'total_egresos' => $totalEgresos,
            'balance_final' => $balanceFinal,
            'monto_efectivo' => round($montoEfectivo, 2),
            'monto_yape' => round($porMetodo['yape'] ?? 0, 2),
            'monto_izipay' => round($porMetodo['izipay'] ?? 0, 2),
            'por_metodo' => $porMetodo,
            'total_ventas' => (float) $totalVentas,
            'total_cambios' => (float) $totalCambios,
            'total_ingresos_vehiculares' => (float) $totalIngresos,
        ];
    }

    /**
     * Registra un ingreso compensatorio (nota de crédito) en la caja activa.
     *
     * Se usa al anular una compra recibida: genera un ingreso que compensa
     * el egreso original de la compra.
     *
     * @param  Caja  $caja  La caja a la que se asocia el ingreso.
     * @param  array  $data  Debe contener: monto, descripcion, tipo_pago.
     *                       Opcionalmente: user_id (si no se pasa, se usa el usuario autenticado).
     *
     * @throws \RuntimeException si la caja está cerrada.
     */
    public function registrarIngresoCompensatorio(Caja $caja, array $data): EgresoCaja
    {
        if ($caja->estado === 'cerrada') {
            throw new \RuntimeException('No se pueden registrar ingresos compensatorios en una caja cerrada.');
        }

        // Usamos EgresoCaja con monto negativo para representar un ingreso (nota de crédito)
        // El campo tipo_pago se usa para clasificar: 'transferencia' por defecto para notas de crédito
        return EgresoCaja::create([
            'caja_id' => $caja->id,
            'monto' => -abs($data['monto']), // Monto negativo = ingreso
            'descripcion' => $data['descripcion'],
            'tipo_pago' => $data['tipo_pago'] ?? 'transferencia',
            'user_id' => $data['user_id'] ?? auth()->id(),
        ]);
    }
}
