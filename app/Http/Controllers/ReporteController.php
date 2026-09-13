<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Servicio;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Reportes\DateRangeFiltro;
use App\Services\Reportes\ReporteCajaService;
use App\Services\Reportes\ReporteCambioAceiteService;
use App\Services\Reportes\ReporteClientesService;
use App\Services\Reportes\ReporteCsvService;
use App\Services\Reportes\ReporteIngresosService;
use App\Services\Reportes\ReporteInventarioService;
use App\Services\Reportes\ReporteKardexService;
use App\Services\Reportes\ReporteLavadosService;
use App\Services\Reportes\ReportePersonalService;
use App\Services\Reportes\ReporteVentasService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes de solo lectura (Administrador). Todos los filtros vía GET.
 * Si `export=csv` la misma ruta devuelve la descarga correspondiente.
 */
class ReporteController extends Controller
{
    private const REGLAS_DESDE_HASTA = [
        'desde' => ['nullable', 'date_format:Y-m-d'],
        'hasta' => ['nullable', 'date_format:Y-m-d'],
    ];

    public function __construct(
        private readonly ReporteIngresosService $ingresosService,
        private readonly ReporteVentasService $ventasService,
        private readonly ReporteLavadosService $lavadosService,
        private readonly ReporteCambioAceiteService $cambioAceiteService,
        private readonly ReporteInventarioService $inventarioService,
        private readonly ReporteClientesService $clientesService,
        private readonly ReporteCajaService $cajaService,
        private readonly ReportePersonalService $personalService,
        private readonly ReporteKardexService $kardexService,
        private readonly ReporteCsvService $csvService,
    ) {}

    public function index(): View
    {
        return view('reportes.index');
    }

    public function ingresos(Request $request): View|StreamedResponse|JsonResponse
    {
        $reglas = self::REGLAS_DESDE_HASTA;
        $datos = $this->validar($request, $reglas);

        [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar($datos['desde'] ?? null, $datos['hasta'] ?? null);

        if ($request->query('export') === 'csv') {
            $serie = $this->ingresosService->serieDiaria($desde, $hasta);
            $filas = collect($serie['dias'])->map(fn ($dia, $i) => [
                'fecha' => $dia,
                'ventas' => $serie['ventas'][$i],
                'lavados' => $serie['lavados'][$i],
                'cambio_aceite' => $serie['cambios'][$i],
                'total' => $serie['totals'][$i],
            ]);

            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-ingresos'),
                ['fecha', 'ventas', 'lavados', 'cambio_aceite', 'total'],
                $filas
            );
        }

        return view('reportes.ingresos', [
            'consolidado' => $this->ingresosService->consolidado($desde, $hasta),
            'serie' => $this->ingresosService->serieDiaria($desde, $hasta),
            'metodoPago' => $this->ingresosService->metodoPago($desde, $hasta),
            'diasTop' => $this->ingresosService->diasTop($desde, $hasta),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    public function ventas(Request $request): View|StreamedResponse|JsonResponse
    {
        $datos = $this->validar($request, array_merge(self::REGLAS_DESDE_HASTA, [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'metodo_pago' => ['nullable', 'in:efectivo,yape,izipay,mixto'],
            'correlativo' => ['nullable', 'string', 'max:50'],
        ]));

        [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar($datos['desde'] ?? null, $datos['hasta'] ?? null);
        $filtros = $this->filtrosExtras($request, ['user_id', 'metodo_pago', 'correlativo']);

        if ($request->query('export') === 'csv') {
            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-ventas'),
                ['id', 'correlativo', 'fecha', 'usuario', 'metodo_pago', 'total'],
                $this->ventasService->detalleColeccion($desde, $hasta, $filtros)->map(fn ($v) => [
                    $v->id,
                    $v->correlativo,
                    $v->created_at?->format('d/m/Y H:i'),
                    $v->user?->name,
                    $v->metodo_pago,
                    $v->total,
                ])
            );
        }

        return view('reportes.ventas', [
            'kpis' => $this->ventasService->kpis($desde, $hasta, $filtros),
            'porUsuario' => $this->ventasService->porUsuario($desde, $hasta, $filtros),
            'porMetodo' => $this->ventasService->porMetodo($desde, $hasta, $filtros),
            'detalle' => $this->ventasService->detalle($desde, $hasta, $filtros),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    public function lavados(Request $request): View|StreamedResponse|JsonResponse
    {
        $datos = $this->validar($request, array_merge(self::REGLAS_DESDE_HASTA, [
            'vehiculo_id' => ['nullable', 'integer', 'exists:vehiculos,id'],
            'servicio_id' => ['nullable', 'integer', 'exists:servicios,id'],
            'trabajador_id' => ['nullable', 'integer', 'exists:trabajadores,id'],
        ]));

        [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar($datos['desde'] ?? null, $datos['hasta'] ?? null);
        $filtros = $this->filtrosExtras($request, ['vehiculo_id', 'servicio_id', 'trabajador_id']);

        if ($request->query('export') === 'csv') {
            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-lavados'),
                ['id', 'fecha', 'cliente', 'placa', 'vehiculo', 'servicios', 'total', 'estado'],
                $this->lavadosService->detalleColeccion($desde, $hasta, $filtros)->map(fn ($l) => [
                    $l->id,
                    $l->fecha?->format('d/m/Y'),
                    $l->cliente?->nombre_completo,
                    $l->automotor?->placa,
                    $l->vehiculo?->nombre,
                    $l->servicios->pluck('nombre')->implode(' + '),
                    $l->total,
                    $l->estado,
                ])
            );
        }

        return view('reportes.lavados', [
            'kpis' => $this->lavadosService->kpis($desde, $hasta, $filtros),
            'porVehiculo' => $this->lavadosService->porVehiculo($desde, $hasta, $filtros),
            'porServicio' => $this->lavadosService->porServicio($desde, $hasta, $filtros),
            'porTrabajador' => $this->lavadosService->porTrabajador($desde, $hasta, $filtros),
            'detalle' => $this->lavadosService->detalle($desde, $hasta, $filtros),
            'vehiculos' => Vehiculo::orderBy('nombre')->get(['id', 'nombre']),
            'servicios' => Servicio::orderBy('nombre')->get(['id', 'nombre']),
            'trabajadores' => Trabajador::orderBy('nombre')->get(['id', 'nombre', 'apellido_paterno', 'apellido_materno']),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    public function cambioAceite(Request $request): View|StreamedResponse|JsonResponse
    {
        $datos = $this->validar($request, array_merge(self::REGLAS_DESDE_HASTA, [
            'trabajador_id' => ['nullable', 'integer', 'exists:trabajadores,id'],
            'producto_id' => ['nullable', 'integer', 'exists:productos,id'],
        ]));

        [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar($datos['desde'] ?? null, $datos['hasta'] ?? null);
        $filtros = $this->filtrosExtras($request, ['trabajador_id', 'producto_id']);

        if ($request->query('export') === 'csv') {
            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-cambio-aceite'),
                ['id', 'fecha', 'cliente', 'placa', 'trabajadores', 'productos', 'total', 'estado'],
                $this->cambioAceiteService->detalleColeccion($desde, $hasta, $filtros)->map(fn ($c) => [
                    $c->id,
                    $c->created_at?->format('d/m/Y H:i'),
                    $c->cliente?->nombre_completo,
                    $c->automotor?->placa,
                    $c->trabajadores->pluck('nombre_completo')->implode(' + '),
                    $c->productos->pluck('nombre')->implode(' + '),
                    $c->total,
                    $c->estado,
                ])
            );
        }

        return view('reportes.cambio-aceite', [
            'kpis' => $this->cambioAceiteService->kpis($desde, $hasta, $filtros),
            'porProducto' => $this->cambioAceiteService->porProducto($desde, $hasta, $filtros),
            'porTrabajador' => $this->cambioAceiteService->porTrabajador($desde, $hasta, $filtros),
            'detalle' => $this->cambioAceiteService->detalle($desde, $hasta, $filtros),
            'trabajadores' => Trabajador::orderBy('nombre')->get(['id', 'nombre', 'apellido_paterno', 'apellido_materno']),
            'productos' => Producto::orderBy('nombre')->get(['id', 'nombre']),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    public function inventario(Request $request): View|StreamedResponse|JsonResponse
    {
        $datos = $this->validar($request, array_merge(self::REGLAS_DESDE_HASTA, [
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'marca_id' => ['nullable', 'integer', 'exists:marcas,id'],
        ]));

        [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar($datos['desde'] ?? null, $datos['hasta'] ?? null);
        $filtros = $this->filtrosExtras($request, ['categoria_id', 'marca_id']);
        $top = $this->inventarioService->topPorCantidad($desde, $hasta, $filtros);

        if ($request->query('export') === 'csv') {
            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-inventario'),
                ['producto', 'categoria', 'marca', 'cantidad_vendida', 'ingreso'],
                $top->map(fn ($p) => [$p['nombre'], $p['categoria'], $p['marca'], $p['cantidad'], $p['ingreso']])
            );
        }

        return view('reportes.inventario', [
            'top' => $top,
            'stockActual' => $this->inventarioService->stockActual($filtros),
            'resumenCategorias' => $this->inventarioService->resumenCategorias($desde, $hasta, $filtros),
            'resumenMarcas' => $this->inventarioService->resumenMarcas($desde, $hasta, $filtros),
            'categorias' => Categoria::orderBy('nombre')->get(['id', 'nombre']),
            'marcas' => Marca::orderBy('nombre')->get(['id', 'nombre']),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    public function clientes(Request $request): View|StreamedResponse|JsonResponse
    {
        $datos = $this->validar($request, self::REGLAS_DESDE_HASTA);

        [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar($datos['desde'] ?? null, $datos['hasta'] ?? null);

        if ($request->query('export') === 'csv') {
            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-clientes'),
                ['cliente', 'gasto', 'visitas', 'automotores', 'visitas_por_mes'],
                $this->clientesService->topClientes($desde, $hasta)->map(fn ($c) => [
                    $c['nombre'], $c['gasto'], $c['visitas'], $c['automotores'], $c['visitas_por_mes'],
                ])
            );
        }

        return view('reportes.clientes', [
            'topClientes' => $this->clientesService->topClientes($desde, $hasta),
            'topAutomotores' => $this->clientesService->topAutomotores($desde, $hasta),
            'detalle' => $this->clientesService->detalle($desde, $hasta),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    public function caja(Request $request): View|StreamedResponse|JsonResponse
    {
        $datos = $this->validar($request, self::REGLAS_DESDE_HASTA);

        [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar($datos['desde'] ?? null, $datos['hasta'] ?? null);

        if ($request->query('export') === 'csv') {
            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-caja'),
                ['caja', 'usuario', 'apertura', 'cierre', 'monto_inicial', 'total_ingresos', 'total_egresos', 'balance_final'],
                $this->cajaService->detalle($desde, $hasta, 100)->getCollection()->map(fn ($c) => [
                    $c['id'], $c['usuario'], $c['fecha_apertura'], $c['fecha_cierre'],
                    $c['monto_inicial'], $c['total_ingresos'], $c['total_egresos'], $c['balance_final'],
                ])
            );
        }

        return view('reportes.caja', [
            'kpis' => $this->cajaService->kpis($desde, $hasta),
            'detalle' => $this->cajaService->detalle($desde, $hasta),
            'egresos' => $this->cajaService->egresos($desde, $hasta),
            'egresosPorDescripcion' => $this->cajaService->egresosPorDescripcion($desde, $hasta),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    public function personal(Request $request): View|StreamedResponse|JsonResponse
    {
        $datos = $this->validar($request, [
            'mes' => ['nullable', 'date_format:Y-m'],
            'trabajador_id' => ['nullable', 'integer', 'exists:trabajadores,id'],
        ]);

        $mes = $datos['mes'] ?? now()->format('Y-m');
        $trabajadorId = $datos['trabajador_id'] ?? null;
        $resumen = $this->personalService->resumen($mes, $trabajadorId);

        if ($request->query('export') === 'csv') {
            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-personal-'.str_replace('-', '', $mes)),
                ['trabajador', 'pago_diario', 'asistencias', 'porcentaje_asistencia', 'hora_promedio', 'total_pago', 'sin_jornal', 'activo'],
                collect($resumen['trabajadores'])->map(fn ($t) => [
                    $t['nombre'], $t['pago_diario'] ?? 'Sin jornal', $t['asistencias'],
                    $t['porcentaje_asistencia'], $t['hora_promedio'], $t['total_pago'],
                    $t['sin_jornal'] ? 'Sí' : 'No', $t['activo'] ? 'Activo' : 'Inactivo',
                ])
            );
        }

        return view('reportes.personal', [
            'resumen' => $resumen,
            'trabajadores' => $this->personalService->trabajadoresDisponibles(),
            'mes' => $mes,
        ]);
    }

    public function kardex(Request $request): View|StreamedResponse|JsonResponse
    {
        $datos = $this->validar($request, array_merge(self::REGLAS_DESDE_HASTA, [
            'producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'tipo' => ['nullable', 'in:entrada,salida'],
        ]));

        [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar($datos['desde'] ?? null, $datos['hasta'] ?? null);
        $filtros = $this->filtrosExtras($request, ['producto_id', 'tipo']);

        if ($request->query('export') === 'csv') {
            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-kardex'),
                ['id', 'fecha', 'producto', 'tipo', 'fuente', 'cantidad', 'stock_antes', 'stock_despues', 'usuario'],
                $this->kardexService->detalleColeccion($desde, $hasta, $filtros)->map(fn ($m) => [
                    $m->id,
                    $m->fecha_movimiento?->format('d/m/Y H:i'),
                    $m->producto?->nombre,
                    $m->tipo,
                    $m->fuente,
                    $m->cantidad,
                    $m->stock_antes,
                    $m->stock_despues,
                    $m->usuario?->name,
                ])
            );
        }

        return view('reportes.kardex', [
            'agregado' => $this->kardexService->agregado($desde, $hasta, $filtros),
            'detalle' => $this->kardexService->detalle($desde, $hasta, $filtros),
            'productos' => Producto::orderBy('nombre')->get(['id', 'nombre']),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    /**
     * Valida con reglas inline; si falla y se pidió export=csv responde
     * 422 JSON (para no colgar la descarga).
     *
     * @param  array<int, mixed>  $reglas
     * @return array<string, mixed>
     */
    private function validar(Request $request, array $reglas): array
    {
        $validator = Validator::make($request->query(), $reglas);

        $validator->after(function ($validator) use ($request) {
            $desde = $request->query('desde');
            $hasta = $request->query('hasta');
            $hoy = now()->format('Y-m-d');

            if ($desde && $desde > $hoy) {
                $validator->errors()->add('desde', 'La fecha «desde» no puede ser futura.');
            }

            if ($hasta && $hasta > $hoy) {
                $validator->errors()->add('hasta', 'La fecha «hasta» no puede ser futura.');
            }

            if ($desde && $hasta && $desde > $hasta && ! $validator->errors()->has('desde')) {
                $validator->errors()->add('desde', 'La fecha «desde» no puede ser posterior a «hasta».');
            }
        });

        if ($validator->fails()) {
            if ($request->query('export') === 'csv') {
                $response = response()->json(['errors' => $validator->errors()], 422);
                throw new HttpResponseException($response);
            }

            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * @param  list<string>  $claves
     * @return array<string, mixed>
     */
    private function filtrosExtras(Request $request, array $claves): array
    {
        return collect($claves)
            ->mapWithKeys(fn ($clave) => [$clave => $request->query($clave)])
            ->filter(fn ($valor) => $valor !== null && $valor !== '')
            ->all();
    }

    private function nombreArchivo(string $base): string
    {
        return $base.'-'.now()->format('Ymd-His').'.csv';
    }
}
