<?php

namespace App\Services;

use App\Models\CambioAceite;
use App\Models\Lavado;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\Reportes\ReporteOperativoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de agregación de estadísticas e ingresos para el dashboard.
 *
 * Consolida las tres fuentes de ingreso del negocio (ventas, lavados y
 * cambios de aceite) usando el mismo criterio de "ingreso consolidado"
 * que {@see CajaService::calcularResumen()} pero por rango de fechas.
 */
class DashboardService
{
    /**
     * Normaliza la fecha efectiva de cada fuente:
     *  - lavados usa la columna `fecha` (date)
     *  - ventas y cambio_aceites usan `created_at` (timestamp)
     */
    private const FECHA_VENTAS = 'DATE(created_at)';

    private const FECHA_LAVADOS = 'DATE(fecha)';

    private const FECHA_CAMBIOS = 'DATE(created_at)';

    /**
     * Resumen general del mes en curso y del día de hoy.
     *
     * @return array{
     *   ingresos_hoy: float,
     *   ingresos_mes: float,
     *   operaciones_mes: int,
     *   ticket_promedio_mes: float,
     *   ventas_mes: float,
     *   lavados_mes: float,
     *   cambios_mes: float,
     * }
     */
    public function resumenGeneral(): array
    {
        $hoy = CarbonImmutable::today();
        $inicioMes = CarbonImmutable::today()->startOfMonth();
        $finMes = CarbonImmutable::today()->endOfMonth();

        $mes = $this->ingresosEnRango($inicioMes, $finMes);
        $hoyVentas = (float) Venta::whereBetween('created_at', [$hoy->startOfDay(), $hoy->endOfDay()])->sum('total');
        $hoyLavados = (float) Lavado::where('estado', 'confirmado')
            ->whereDate('fecha', $hoy->toDateString())->sum('total');
        $hoyCambios = (float) CambioAceite::where('estado', 'confirmado')
            ->whereBetween('created_at', [$hoy->startOfDay(), $hoy->endOfDay()])->sum('total');

        $ingresosHoy = $hoyVentas + $hoyLavados + $hoyCambios;

        return [
            'ingresos_hoy' => $ingresosHoy,
            'ingresos_mes' => $mes['total'],
            'operaciones_mes' => $mes['operaciones'],
            'ticket_promedio_mes' => $mes['operaciones'] > 0
                ? round($mes['total'] / $mes['operaciones'], 2)
                : 0.0,
            'ventas_mes' => $mes['ventas'],
            'lavados_mes' => $mes['lavados'],
            'cambios_mes' => $mes['cambios'],
        ];
    }

    /**
     * Serie diaria de ingresos por fuente para los últimos $dias días.
     *
     * @return array{
     *   dias: string[],
     *   ventas: float[],
     *   lavados: float[],
     *   cambios: float[],
     *   totals: float[],
     * }
     */
    public function ingresosPorDia(int $dias = 30): array
    {
        $dias = min(max($dias, 1), 90);
        $desde = CarbonImmutable::today()->subDays($dias - 1)->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        // Inicializar la serie completa de días evitando huecos.
        $mapa = [];
        $etiquetas = [];
        for ($i = 0; $i < $dias; $i++) {
            $fecha = CarbonImmutable::today()->subDays($dias - 1 - $i)->toDateString();
            $etiquetas[] = $fecha;
            $mapa[$fecha] = ['ventas' => 0.0, 'lavados' => 0.0, 'cambios' => 0.0];
        }

        foreach (Venta::selectRaw(self::FECHA_VENTAS.' as fecha, SUM(total) as total')
            ->whereBetween('created_at', [$desde, $hasta])
            ->groupByRaw(self::FECHA_VENTAS)->get() as $fila) {
            $mapa[$this->fechaTexto($fila->fecha)]['ventas'] = (float) $fila->total;
        }

        foreach (Lavado::selectRaw(self::FECHA_LAVADOS.' as fecha, SUM(total) as total')
            ->where('estado', 'confirmado')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->groupByRaw(self::FECHA_LAVADOS)->get() as $fila) {
            $mapa[$this->fechaTexto($fila->fecha)]['lavados'] = (float) $fila->total;
        }

        foreach (CambioAceite::selectRaw(self::FECHA_CAMBIOS.' as fecha, SUM(total) as total')
            ->where('estado', 'confirmado')
            ->whereBetween('created_at', [$desde, $hasta])
            ->groupByRaw(self::FECHA_CAMBIOS)->get() as $fila) {
            $mapa[$this->fechaTexto($fila->fecha)]['cambios'] = (float) $fila->total;
        }

        $ventas = [];
        $lavados = [];
        $cambios = [];
        $totals = [];
        foreach ($etiquetas as $fecha) {
            $ventas[] = $mapa[$fecha]['ventas'];
            $lavados[] = $mapa[$fecha]['lavados'];
            $cambios[] = $mapa[$fecha]['cambios'];
            $totals[] = $mapa[$fecha]['ventas'] + $mapa[$fecha]['lavados'] + $mapa[$fecha]['cambios'];
        }

        return [
            'dias' => $etiquetas,
            'ventas' => $ventas,
            'lavados' => $lavados,
            'cambios' => $cambios,
            'totals' => $totals,
        ];
    }

    /**
     * Suma consolidada por método de pago en el rango dado.
     *
     * @return array<string, float> codigo => total (incluye ceros base).
     */
    public function metodoPago(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $resultado = ['efectivo' => 0.0, 'yape' => 0.0, 'izipay' => 0.0];

        $ventas = Venta::whereBetween('created_at', [$desde, $hasta])->with('pagos.metodoPago')->get();
        $cambios = CambioAceite::where('estado', 'confirmado')
            ->whereBetween('created_at', [$desde, $hasta])->with('pagos.metodoPago')->get();
        $lavados = Lavado::where('estado', 'confirmado')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])->with('pagos.metodoPago')->get();

        foreach ([$ventas, $cambios, $lavados] as $registros) {
            foreach ($registros as $registro) {
                $this->acumularMetodoPago($resultado, $registro);
            }
        }

        foreach ($resultado as $codigo => $monto) {
            $resultado[$codigo] = round($monto, 2);
        }

        return $resultado;
    }

    /**
     * Top N productos más vendidos por cantidad combinando
     * detalle_ventas y cambio_productos.
     *
     * @return Collection<int, array{ nombre: string, cantidad: int }>
     */
    public function topProductos(int $limit = 5): Collection
    {
        $porProducto = [];

        foreach (DB::table('detalle_ventas')
            ->join('productos', 'productos.id', '=', 'detalle_ventas.producto_id')
            ->selectRaw('productos.id, productos.nombre, SUM(detalle_ventas.cantidad) as cantidad')
            ->groupBy('productos.id', 'productos.nombre')
            ->get() as $fila) {
            $porProducto[$fila->id] = ['nombre' => $fila->nombre, 'cantidad' => (int) $fila->cantidad];
        }

        foreach (DB::table('cambio_productos')
            ->join('productos', 'productos.id', '=', 'cambio_productos.producto_id')
            ->selectRaw('productos.id, productos.nombre, SUM(cambio_productos.cantidad) as cantidad')
            ->groupBy('productos.id', 'productos.nombre')
            ->get() as $fila) {
            if (! isset($porProducto[$fila->id])) {
                $porProducto[$fila->id] = ['nombre' => $fila->nombre, 'cantidad' => 0];
            }
            $porProducto[$fila->id]['cantidad'] += (int) $fila->cantidad;
        }

        usort($porProducto, fn ($a, $b) => $b['cantidad'] <=> $a['cantidad']);

        return collect(array_slice($porProducto, 0, $limit))->values();
    }

    /**
     * Productos activos con stock por debajo o igual al umbral.
     *
     * @return Collection<int, Producto>
     */
    public function productosStockBajo(int $umbral = 5): Collection
    {
        return Producto::with('categoria:id,nombre')
            ->where('activo', true)
            ->where('stock', '<=', $umbral)
            ->orderBy('stock')
            ->limit(5)
            ->get();
    }

    /**
     * Resumen operativo del rango seleccionado (supervisión rápida).
     *
     * Reutiliza {@see ReporteOperativoService} para no duplicar las reglas
     * de universo (solo confirmados) ni las fórmulas de tiempos/vehículos.
     *
     * @return array{
     *   atenciones: int, tiempo_promedio: ?float, vehiculos_unicos: int,
     *   atenciones_por_dia: float,
     * }
     */
    public function resumenOperativo(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $kpis = app(ReporteOperativoService::class)->kpis($desde, $hasta);

        return [
            'atenciones' => $kpis['atenciones'],
            'tiempo_promedio' => $kpis['tiempo_promedio'],
            'vehiculos_unicos' => $kpis['vehiculos_unicos'],
            'atenciones_por_dia' => $kpis['atenciones_por_dia'],
        ];
    }

    /**
     * Convierte el valor de fecha de una fila agregada a texto 'Y-m-d',
     * normalizando objetos DateTime/Carbon (producidos por los casts del modelo).
     */
    private function fechaTexto(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        return (string) $fecha;
    }

    /**
     * Calcula los totales de cada fuente y el total consolidado en un rango.
     *
     * @return array{
     *   ventas: float,
     *   lavados: float,
     *   cambios: float,
     *   total: float,
     *   operaciones: int,
     * }
     */
    private function ingresosEnRango(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $ventas = (float) Venta::whereBetween('created_at', [$desde, $hasta])->sum('total');
        $lavados = (float) Lavado::where('estado', 'confirmado')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])->sum('total');
        $cambios = (float) CambioAceite::where('estado', 'confirmado')
            ->whereBetween('created_at', [$desde, $hasta])->sum('total');

        $operaciones = Venta::whereBetween('created_at', [$desde, $hasta])->count()
            + Lavado::where('estado', 'confirmado')
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])->count()
            + CambioAceite::where('estado', 'confirmado')
                ->whereBetween('created_at', [$desde, $hasta])->count();

        return [
            'ventas' => $ventas,
            'lavados' => $lavados,
            'cambios' => $cambios,
            'total' => $ventas + $lavados + $cambios,
            'operaciones' => $operaciones,
        ];
    }

    /**
     * Acumula las líneas de pago del ticket en el mapa por código de método.
     */
    private function acumularMetodoPago(array &$resultado, object $registro): void
    {
        foreach ($registro->pagos as $pago) {
            $codigo = $pago->metodoPago?->codigo ?? '—';
            $resultado[$codigo] = ($resultado[$codigo] ?? 0) + (float) $pago->monto;
        }
    }
}
