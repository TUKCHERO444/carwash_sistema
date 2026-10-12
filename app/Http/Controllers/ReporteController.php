<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Servicio;
use App\Models\TipoVehiculo;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\PagoService;
use App\Services\Reportes\DateRangeFiltro;
use App\Services\Reportes\ReporteCajaService;
use App\Services\Reportes\ReporteCambioAceiteService;
use App\Services\Reportes\ReporteClientesService;
use App\Services\Reportes\ReporteComprasService;
use App\Services\Reportes\ReporteCsvService;
use App\Services\Reportes\ReporteIngresosService;
use App\Services\Reportes\ReporteInventarioService;
use App\Services\Reportes\ReporteKardexService;
use App\Services\Reportes\ReporteLavadosService;
use App\Services\Reportes\ReporteOperativoService;
use App\Services\Reportes\ReportePdfService;
use App\Services\Reportes\ReportePersonalService;
use App\Services\Reportes\ReporteVehiculoService;
use App\Services\Reportes\ReporteVentasService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes de solo lectura (Administrador). Todos los filtros vía GET.
 * Si `export=csv` o `export=pdf`, la misma ruta devuelve la descarga correspondiente.
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
        private readonly ReporteOperativoService $operativoService,
        private readonly ReporteVehiculoService $vehiculoService,
        private readonly ReporteComprasService $comprasService,
        private readonly ReporteCsvService $csvService,
        private readonly ReportePdfService $pdfService,
    ) {}

    public function index(): View
    {
        return view('reportes.index');
    }

    public function ingresos(Request $request): View|StreamedResponse|JsonResponse|Response
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

        if ($request->query('export') === 'pdf') {
            return $this->pdfService->descargar(
                'reportes.pdf.ingresos',
                $this->nombreArchivo('reporte-ingresos', 'pdf'),
                [
                    'consolidado' => $this->ingresosService->consolidado($desde, $hasta),
                    'serie' => $this->ingresosService->serieDiaria($desde, $hasta),
                    'metodoPago' => $this->ingresosService->metodoPago($desde, $hasta),
                    'diasTop' => $this->ingresosService->diasTop($desde, $hasta),
                    'etiqueta' => $etiqueta,
                ]
            );
        }

        return view('reportes.ingresos', [
            'consolidado' => $this->ingresosService->consolidado($desde, $hasta),
            'serie' => $this->ingresosService->serieDiaria($desde, $hasta),
            'metodoPago' => $this->ingresosService->metodoPago($desde, $hasta),
            'metodoPagoChart' => $this->chartMetodoPago($this->ingresosService->metodoPago($desde, $hasta)),
            'nombresMetodosPagos' => MetodoPago::pluck('nombre', 'codigo')->all(),
            'diasTop' => $this->ingresosService->diasTop($desde, $hasta),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    public function ventas(Request $request): View|StreamedResponse|JsonResponse|Response
    {
        $metodosFiltro = array_merge(MetodoPago::where('activo', true)->pluck('codigo')->all(), ['mixto']);
        $datos = $this->validar($request, array_merge(self::REGLAS_DESDE_HASTA, [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'metodo_pago' => ['nullable', 'string', Rule::in($metodosFiltro)],
            'correlativo' => ['nullable', 'string', 'max:50'],
        ]));

        [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar($datos['desde'] ?? null, $datos['hasta'] ?? null);
        $filtros = $this->filtrosExtras($request, ['user_id', 'metodo_pago', 'correlativo']);
        $metodosPago = MetodoPago::where('activo', true)->orderBy('orden')->orderBy('id')->get();

        if ($request->query('export') === 'csv') {
            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-ventas'),
                ['id', 'correlativo', 'fecha', 'usuario', 'metodo_pago', 'total'],
                $this->ventasService->detalleColeccion($desde, $hasta, $filtros)->map(fn ($v) => [
                    $v->id,
                    $v->correlativo,
                    $v->created_at?->format('d/m/Y H:i'),
                    $v->user?->name,
                    app(PagoService::class)->resumen($v)['etiqueta'],
                    $v->total,
                ])
            );
        }

        if ($request->query('export') === 'pdf') {
            return $this->pdfService->descargar(
                'reportes.pdf.ventas',
                $this->nombreArchivo('reporte-ventas', 'pdf'),
                [
                    'kpis' => $this->ventasService->kpis($desde, $hasta, $filtros),
                    'porUsuario' => $this->ventasService->porUsuario($desde, $hasta, $filtros),
                    'porMetodo' => $this->ventasService->porMetodo($desde, $hasta, $filtros),
                    'detalle' => $this->ventasService->detalleColeccion($desde, $hasta, $filtros),
                    'etiqueta' => $etiqueta,
                ]
            );
        }

        return view('reportes.ventas', [
            'kpis' => $this->ventasService->kpis($desde, $hasta, $filtros),
            'porUsuario' => $this->ventasService->porUsuario($desde, $hasta, $filtros),
            'porMetodo' => $this->ventasService->porMetodo($desde, $hasta, $filtros),
            'detalle' => $this->ventasService->detalle($desde, $hasta, $filtros),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
            'metodosPago' => $metodosPago,
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    public function lavados(Request $request): View|StreamedResponse|JsonResponse|Response
    {
        $datos = $this->validar($request, array_merge(self::REGLAS_DESDE_HASTA, [
            'tipo_vehiculo_id' => ['nullable', 'integer', 'exists:tipos_vehiculo,id'],
            'servicio_id' => ['nullable', 'integer', 'exists:servicios,id'],
            'trabajador_id' => ['nullable', 'integer', 'exists:trabajadores,id'],
        ]));

        [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar($datos['desde'] ?? null, $datos['hasta'] ?? null);
        $filtros = $this->filtrosExtras($request, ['tipo_vehiculo_id', 'servicio_id', 'trabajador_id']);

        if ($request->query('export') === 'csv') {
            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-lavados'),
                ['id', 'fecha', 'cliente', 'placa', 'vehiculo', 'servicios', 'total', 'estado'],
                $this->lavadosService->detalleColeccion($desde, $hasta, $filtros)->map(fn ($l) => [
                    $l->id,
                    $l->fecha?->format('d/m/Y'),
                    $l->cliente?->nombre_completo,
                    $l->vehiculo?->placa,
                    $l->tipoVehiculo?->nombre,
                    $l->servicios->pluck('nombre')->implode(' + '),
                    $l->total,
                    $l->estado,
                ])
            );
        }

        if ($request->query('export') === 'pdf') {
            return $this->pdfService->descargar(
                'reportes.pdf.lavados',
                $this->nombreArchivo('reporte-lavados', 'pdf'),
                [
                    'kpis' => $this->lavadosService->kpis($desde, $hasta, $filtros),
                    'porVehiculo' => $this->lavadosService->porVehiculo($desde, $hasta, $filtros),
                    'porServicio' => $this->lavadosService->porServicio($desde, $hasta, $filtros),
                    'porTrabajador' => $this->lavadosService->porTrabajador($desde, $hasta, $filtros),
                    'detalle' => $this->lavadosService->detalleColeccion($desde, $hasta, $filtros),
                    'etiqueta' => $etiqueta,
                ]
            );
        }

        return view('reportes.lavados', [
            'kpis' => $this->lavadosService->kpis($desde, $hasta, $filtros),
            'porVehiculo' => $this->lavadosService->porVehiculo($desde, $hasta, $filtros),
            'porServicio' => $this->lavadosService->porServicio($desde, $hasta, $filtros),
            'porTrabajador' => $this->lavadosService->porTrabajador($desde, $hasta, $filtros),
            'detalle' => $this->lavadosService->detalle($desde, $hasta, $filtros),
            'tiposVehiculo' => TipoVehiculo::orderBy('nombre')->get(['id', 'nombre']),
            'servicios' => Servicio::orderBy('nombre')->get(['id', 'nombre']),
            'trabajadores' => Trabajador::orderBy('nombre')->get(['id', 'nombre', 'apellido_paterno', 'apellido_materno']),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    public function cambioAceite(Request $request): View|StreamedResponse|JsonResponse|Response
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
                    $c->vehiculo?->placa,
                    $c->trabajadores->pluck('nombre_completo')->implode(' + '),
                    $c->productos->pluck('nombre')->implode(' + '),
                    $c->total,
                    $c->estado,
                ])
            );
        }

        if ($request->query('export') === 'pdf') {
            return $this->pdfService->descargar(
                'reportes.pdf.cambio-aceite',
                $this->nombreArchivo('reporte-cambio-aceite', 'pdf'),
                [
                    'kpis' => $this->cambioAceiteService->kpis($desde, $hasta, $filtros),
                    'porProducto' => $this->cambioAceiteService->porProducto($desde, $hasta, $filtros),
                    'porTrabajador' => $this->cambioAceiteService->porTrabajador($desde, $hasta, $filtros),
                    'detalle' => $this->cambioAceiteService->detalleColeccion($desde, $hasta, $filtros),
                    'etiqueta' => $etiqueta,
                ]
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

    public function inventario(Request $request): View|StreamedResponse|JsonResponse|Response
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

        if ($request->query('export') === 'pdf') {
            return $this->pdfService->descargar(
                'reportes.pdf.inventario',
                $this->nombreArchivo('reporte-inventario', 'pdf'),
                [
                    'top' => $top,
                    'stockActual' => $this->inventarioService->stockActual($filtros),
                    'resumenCategorias' => $this->inventarioService->resumenCategorias($desde, $hasta, $filtros),
                    'resumenMarcas' => $this->inventarioService->resumenMarcas($desde, $hasta, $filtros),
                    'etiqueta' => $etiqueta,
                ]
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

    public function clientes(Request $request): View|StreamedResponse|JsonResponse|Response
    {
        $datos = $this->validar($request, self::REGLAS_DESDE_HASTA);

        [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar($datos['desde'] ?? null, $datos['hasta'] ?? null);

        if ($request->query('export') === 'csv') {
            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-clientes'),
                ['cliente', 'gasto', 'visitas', 'vehiculos', 'lavados', 'cambio_aceite', 'visitas_por_mes'],
                $this->clientesService->topClientes($desde, $hasta)->map(fn ($c) => [
                    $c['nombre'], $c['gasto'], $c['visitas'], $c['vehiculos'], $c['lavados'], $c['cambio_aceite'], $c['visitas_por_mes'],
                ])
            );
        }

        if ($request->query('export') === 'pdf') {
            return $this->pdfService->descargar(
                'reportes.pdf.clientes',
                $this->nombreArchivo('reporte-clientes', 'pdf'),
                [
                    'topClientes' => $this->clientesService->topClientes($desde, $hasta),
                    'topVehiculos' => $this->clientesService->topVehiculos($desde, $hasta),
                    'frecuencia' => $this->clientesService->detalle($desde, $hasta),
                    'etiqueta' => $etiqueta,
                ]
            );
        }

        return view('reportes.clientes', [
            'topClientes' => $this->clientesService->topClientes($desde, $hasta),
            'topVehiculos' => $this->clientesService->topVehiculos($desde, $hasta),
            'detalle' => $this->clientesService->detalle($desde, $hasta),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    public function caja(Request $request): View|StreamedResponse|JsonResponse|Response
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

        if ($request->query('export') === 'pdf') {
            return $this->pdfService->descargar(
                'reportes.pdf.caja',
                $this->nombreArchivo('reporte-caja', 'pdf'),
                [
                    'kpis' => $this->cajaService->kpis($desde, $hasta),
                    'detalle' => $this->cajaService->detalle($desde, $hasta, 100000)->getCollection(),
                    'egresos' => $this->cajaService->egresos($desde, $hasta),
                    'egresosPorDescripcion' => $this->cajaService->egresosPorDescripcion($desde, $hasta),
                    'egresosPorCategoria' => $this->cajaService->egresosPorCategoria($desde, $hasta),
                    'etiqueta' => $etiqueta,
                ]
            );
        }

        return view('reportes.caja', [
            'kpis' => $this->cajaService->kpis($desde, $hasta),
            'detalle' => $this->cajaService->detalle($desde, $hasta),
            'egresos' => $this->cajaService->egresos($desde, $hasta),
            'egresosPorDescripcion' => $this->cajaService->egresosPorDescripcion($desde, $hasta),
            'egresosPorCategoria' => $this->cajaService->egresosPorCategoria($desde, $hasta),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    public function personal(Request $request): View|StreamedResponse|JsonResponse|Response
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

        if ($request->query('export') === 'pdf') {
            return $this->pdfService->descargar(
                'reportes.pdf.personal',
                $this->nombreArchivo('reporte-personal-'.str_replace('-', '', $mes), 'pdf'),
                [
                    'resumen' => $resumen,
                ]
            );
        }

        return view('reportes.personal', [
            'resumen' => $resumen,
            'trabajadores' => $this->personalService->trabajadoresDisponibles(),
            'mes' => $mes,
        ]);
    }

    public function kardex(Request $request): View|StreamedResponse|JsonResponse|Response
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

        if ($request->query('export') === 'pdf') {
            return $this->pdfService->descargar(
                'reportes.pdf.kardex',
                $this->nombreArchivo('reporte-kardex', 'pdf'),
                [
                    'agregado' => $this->kardexService->agregado($desde, $hasta, $filtros),
                    'detalle' => $this->kardexService->detalleColeccion($desde, $hasta, $filtros),
                    'etiqueta' => $etiqueta,
                ]
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
     * Reporte operativo: eficiencia y demanda (tiempos, atenciones/día,
     * trabajadores, servicios, tipos de vehículo, placas y horas pico).
     * Reutiliza filtros, CSV/PDF e impresión del módulo.
     */
    public function operativo(Request $request): View|StreamedResponse|JsonResponse|Response
    {
        $datos = $this->validar($request, self::REGLAS_DESDE_HASTA);

        [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar($datos['desde'] ?? null, $datos['hasta'] ?? null);

        if ($request->query('export') === 'csv') {
            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-operativo'),
                ['fecha', 'atenciones', 'vehiculos_unicos'],
                $this->operativoService->filasSerie($desde, $hasta)->map(fn ($f) => [
                    $f['fecha'],
                    $f['atenciones'],
                    $f['vehiculos_unicos'],
                ])
            );
        }

        if ($request->query('export') === 'pdf') {
            return $this->pdfService->descargar(
                'reportes.pdf.operativo',
                $this->nombreArchivo('reporte-operativo', 'pdf'),
                [
                    'kpis' => $this->operativoService->kpis($desde, $hasta),
                    'serie' => $this->operativoService->serieDiaria($desde, $hasta),
                    'tiempos' => $this->operativoService->tiempos($desde, $hasta),
                    'porTrabajador' => $this->operativoService->porTrabajador($desde, $hasta),
                    'porServicio' => $this->operativoService->porServicio($desde, $hasta),
                    'porTipoVehiculo' => $this->operativoService->porTipoVehiculo($desde, $hasta),
                    'topPlacas' => $this->operativoService->topPlacas($desde, $hasta),
                    'porHora' => $this->operativoService->porHora($desde, $hasta),
                    'etiqueta' => $etiqueta,
                ]
            );
        }

        return view('reportes.operativo', [
            'kpis' => $this->operativoService->kpis($desde, $hasta),
            'serie' => $this->operativoService->serieDiaria($desde, $hasta),
            'tiempos' => $this->operativoService->tiempos($desde, $hasta),
            'porTrabajador' => $this->operativoService->porTrabajador($desde, $hasta),
            'porServicio' => $this->operativoService->porServicio($desde, $hasta),
            'porTipoVehiculo' => $this->operativoService->porTipoVehiculo($desde, $hasta),
            'topPlacas' => $this->operativoService->topPlacas($desde, $hasta),
            'porHora' => $this->operativoService->porHora($desde, $hasta),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    public function vehiculo(Request $request): View|StreamedResponse|JsonResponse|Response
    {
        $datos = $this->validar($request, array_merge(self::REGLAS_DESDE_HASTA, [
            'placa' => ['nullable', 'string', 'max:7', 'regex:/^[A-Z0-9-]{6,7}$/i'],
            'tipo' => ['nullable', 'in:lavado,cambio_aceite'],
        ]));

        [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar($datos['desde'] ?? null, $datos['hasta'] ?? null);
        $filtros = $this->filtrosExtras($request, ['tipo']);
        $placa = ! empty($datos['placa']) ? Vehiculo::normalizarPlaca($datos['placa']) : null;
        $vehiculo = $placa ? $this->vehiculoService->buscarVehiculo($placa) : null;

        if ($request->query('export') && ! $vehiculo) {
            throw new HttpResponseException(response()->json([
                'errors' => ['placa' => ['Debe indicar una placa registrada para exportar el historial.']],
            ], 422));
        }

        if ($request->query('export') === 'csv') {
            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-vehiculo-'.$placa),
                ['fecha', 'tipo', 'cliente', 'placa', 'detalle', 'trabajadores', 'total', 'estado'],
                $this->vehiculoService->historial($placa, $desde, $hasta, $filtros)->map(fn ($h) => [
                    $h['fecha'],
                    $h['tipo_etiqueta'],
                    $h['cliente'],
                    $placa,
                    $h['detalle'],
                    $h['trabajadores'],
                    $h['total'],
                    $h['estado'],
                ])
            );
        }

        if ($request->query('export') === 'pdf') {
            return $this->pdfService->descargar(
                'reportes.pdf.vehiculo',
                $this->nombreArchivo('reporte-vehiculo-'.$placa, 'pdf'),
                [
                    'vehiculo' => $vehiculo,
                    'kpis' => $this->vehiculoService->kpis($placa, $desde, $hasta, $filtros),
                    'porServicio' => $this->vehiculoService->porServicio($placa, $desde, $hasta, $filtros),
                    'porProducto' => $this->vehiculoService->porProducto($placa, $desde, $hasta, $filtros),
                    'historial' => $this->vehiculoService->historial($placa, $desde, $hasta, $filtros),
                    'etiqueta' => $etiqueta,
                ]
            );
        }

        return view('reportes.vehiculo', [
            'vehiculo' => $vehiculo,
            'placa' => $placa,
            'kpis' => $vehiculo ? $this->vehiculoService->kpis($placa, $desde, $hasta, $filtros) : null,
            'porServicio' => $vehiculo ? $this->vehiculoService->porServicio($placa, $desde, $hasta, $filtros) : [],
            'porProducto' => $vehiculo ? $this->vehiculoService->porProducto($placa, $desde, $hasta, $filtros) : [],
            'detalle' => $vehiculo
                ? $this->vehiculoService->historialPaginado($placa, $desde, $hasta, $filtros, (int) $request->query('page', 1))
                : null,
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    public function comprasProveedor(Request $request): View|StreamedResponse|JsonResponse|Response
    {
        $datos = $this->validar($request, array_merge(self::REGLAS_DESDE_HASTA, [
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'producto_id' => ['nullable', 'integer', 'exists:productos,id'],
        ]));

        [$desde, $hasta, $etiqueta] = DateRangeFiltro::aplicar($datos['desde'] ?? null, $datos['hasta'] ?? null);
        $filtros = $this->filtrosExtras($request, ['producto_id']);
        $proveedorId = ! empty($datos['proveedor_id']) ? (int) $datos['proveedor_id'] : null;
        $proveedor = $proveedorId ? Proveedor::find($proveedorId) : null;

        if ($request->query('export') === 'csv') {
            if ($proveedor) {
                $filas = $this->comprasService->historialColeccion($proveedor->id, $desde, $hasta, $filtros)
                    ->flatMap(fn ($c) => $c->detalles->map(fn ($d) => [
                        $c->correlativo ?? '—',
                        $c->fecha?->format('d/m/Y'),
                        $c->fecha_recepcion?->format('d/m/Y H:i'),
                        $c->estado,
                        $d->producto?->nombre,
                        $d->cantidad,
                        $d->costo_unitario,
                        $d->subtotal,
                        $c->total,
                    ]));

                return $this->csvService->descargar(
                    $this->nombreArchivo('reporte-compras-proveedor-'.$proveedor->id),
                    ['correlativo', 'fecha', 'recepcion', 'estado', 'producto', 'cantidad', 'costo_unitario', 'subtotal', 'total_compra'],
                    $filas
                );
            }

            return $this->csvService->descargar(
                $this->nombreArchivo('reporte-compras-resumen'),
                ['proveedor', 'ruc', 'compras', 'total', 'ultima_compra'],
                $this->comprasService->resumenProveedores($desde, $hasta)->map(fn ($r) => [
                    $r['razon_social'],
                    $r['ruc'],
                    $r['compras'],
                    $r['total'],
                    $r['ultima_compra'],
                ])
            );
        }

        if ($request->query('export') === 'pdf') {
            return $this->pdfService->descargar(
                'reportes.pdf.compras-proveedor',
                $this->nombreArchivo($proveedor ? 'reporte-compras-proveedor-'.$proveedor->id : 'reporte-compras-resumen', 'pdf'),
                [
                    'proveedor' => $proveedor,
                    'resumen' => $this->comprasService->resumenProveedores($desde, $hasta),
                    'kpis' => $proveedor ? $this->comprasService->kpis($proveedor->id, $desde, $hasta, $filtros) : null,
                    'porProducto' => $proveedor ? $this->comprasService->porProducto($proveedor->id, $desde, $hasta, $filtros) : [],
                    'evolucion' => $proveedor ? $this->comprasService->evolucionPrecios($proveedor->id, $desde, $hasta, $filtros) : [],
                    'historial' => $proveedor ? $this->comprasService->historialColeccion($proveedor->id, $desde, $hasta, $filtros) : collect(),
                    'etiqueta' => $etiqueta,
                ]
            );
        }

        return view('reportes.compras-proveedor', [
            'proveedor' => $proveedor,
            'proveedores' => Proveedor::orderBy('razon_social')->get(['id', 'razon_social']),
            'productos' => Producto::orderBy('nombre')->get(['id', 'nombre']),
            'resumen' => $this->comprasService->resumenProveedores($desde, $hasta),
            'kpis' => $proveedor ? $this->comprasService->kpis($proveedor->id, $desde, $hasta, $filtros) : null,
            'porProducto' => $proveedor ? $this->comprasService->porProducto($proveedor->id, $desde, $hasta, $filtros) : [],
            'evolucion' => $proveedor ? $this->comprasService->evolucionPrecios($proveedor->id, $desde, $hasta, $filtros) : [],
            'detalle' => $proveedor ? $this->comprasService->historial($proveedor->id, $desde, $hasta, $filtros) : null,
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => $etiqueta,
        ]);
    }

    /**
     * Valida con reglas inline; si falla y se pidió export=csv o export=pdf
     * responde 422 JSON (para no colgar la descarga).
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
            if (in_array($request->query('export'), ['csv', 'pdf'], true)) {
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

    private function nombreArchivo(string $base, string $ext = 'csv'): string
    {
        return $base.'-'.now()->format('Ymd-His').'.'.$ext;
    }

    /**
     * Convierte el mapa codigo=>total en etiquetas/datos para los charts doughnut.
     *
     * @param  array<string, float>  $mapa
     * @return array{labels: list<string>, data: list<float>}
     */
    private function chartMetodoPago(array $mapa): array
    {
        $nombres = MetodoPago::pluck('nombre', 'codigo')->all();

        return [
            'labels' => array_map(fn ($codigo) => $nombres[$codigo] ?? ucfirst($codigo), array_keys($mapa)),
            'data' => array_values(array_map(fn ($monto) => round((float) $monto, 2), $mapa)),
        ];
    }
}
