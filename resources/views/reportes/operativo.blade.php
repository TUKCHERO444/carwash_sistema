@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Reporte operativo</h1>
            <p class="mt-1 text-sm text-secondary">Rango aplicado: <strong>{{ $etiqueta }}</strong></p>
        </div>
        @include('reportes.partials.acciones')
    </div>

    {{-- Filtros --}}
    @include('reportes.partials.filtros', ['ruta' => 'reportes.operativo'])

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Atenciones</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ number_format($kpis['atenciones']) }}</p>
            <p class="mt-1 text-xs text-secondary">{{ number_format($kpis['lavados']) }} lavados · {{ number_format($kpis['cambios']) }} cambios</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Vehículos únicos</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ number_format($kpis['vehiculos_unicos']) }}</p>
            <p class="mt-1 text-xs text-secondary">{{ number_format($kpis['atenciones_por_dia'], 1) }} atenciones por día</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Tiempo prom. de atención</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ $kpis['tiempo_promedio'] !== null ? number_format($kpis['tiempo_promedio'], 0).' min' : '—' }}</p>
            <p class="mt-1 text-xs text-secondary">{{ number_format($kpis['con_tiempo']) }} con tiempo · {{ number_format($kpis['sin_tiempo']) }} sin tiempo</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Ticket prom. operativo</p>
            <p class="mt-2 text-2xl font-bold text-primary">S/ {{ number_format($kpis['ticket_promedio'], 2) }}</p>
            <p class="mt-1 text-xs text-secondary">Solo lavados + cambios confirmados</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Clientes recurrentes</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ number_format($kpis['clientes_recurrentes']) }} <span class="text-sm font-medium text-secondary">({{ number_format($kpis['pct_recurrentes'], 1) }} %)</span></p>
            <p class="mt-1 text-xs text-secondary">De {{ number_format($kpis['total_clientes']) }} clientes con ≥1 atención (≥2 visitas)</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Hora pico</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ $porHora['hora_pico'] !== null ? sprintf('%02d:00', $porHora['hora_pico']) : '—' }}</p>
            <p class="mt-1 text-xs text-secondary">{{ $porHora['hora_pico'] !== null ? number_format($porHora['atenciones_pico']).' atenciones en esa hora' : 'Sin atenciones en el rango' }}</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Tiempo lavados</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ $tiempos['lavados'] !== null ? number_format($tiempos['lavados'], 0).' min' : '—' }}</p>
            <p class="mt-1 text-xs text-secondary">Promedio solo lavados confirmados</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Tiempo cambios</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ $tiempos['cambios'] !== null ? number_format($tiempos['cambios'], 0).' min' : '—' }}</p>
            <p class="mt-1 text-xs text-secondary">Promedio solo cambios confirmados</p>
        </div>
    </div>

    {{-- Gráficos --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="lg:col-span-2 bg-surface rounded-lg border border-main p-5">
            <h2 class="text-sm font-semibold text-primary mb-4">Atenciones por día</h2>
            <div class="h-72">
                <canvas id="chart-operativo-serie"></canvas>
            </div>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <h2 class="text-sm font-semibold text-primary mb-4">Atenciones por hora</h2>
            <div class="h-72">
                <canvas id="chart-operativo-horas"></canvas>
            </div>
        </div>
    </div>

    {{-- Trabajadores y demanda --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-surface rounded-lg border border-main overflow-x-auto">
            <div class="px-6 py-4 border-b border-main">
                <h2 class="text-sm font-semibold text-primary">Por trabajador</h2>
                <p class="text-xs text-secondary">Participaciones (co-atención cuenta en cada uno).</p>
            </div>
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Nombre</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Lav.</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Cam.</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @forelse($porTrabajador as $fila)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $fila['nombre'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['lavados'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['cambios'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">{{ $fila['operaciones'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-sm text-secondary">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="bg-surface rounded-lg border border-main overflow-x-auto">
            <div class="px-6 py-4 border-b border-main">
                <h2 class="text-sm font-semibold text-primary">Servicios más solicitados</h2>
                <p class="text-xs text-secondary">Tickets de lavado que incluyen cada servicio.</p>
            </div>
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Servicio</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Atenciones</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @forelse($porServicio as $fila)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $fila['nombre'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">{{ $fila['operaciones'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-6 py-8 text-center text-sm text-secondary">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="bg-surface rounded-lg border border-main overflow-x-auto">
            <div class="px-6 py-4 border-b border-main">
                <h2 class="text-sm font-semibold text-primary">Por tipo de vehículo</h2>
                <p class="text-xs text-secondary">Lavados por tarifa del catálogo.</p>
            </div>
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Tipo</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Lavados</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @forelse($porTipoVehiculo as $fila)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $fila['nombre'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['operaciones'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">S/ {{ number_format($fila['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-secondary">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Placas y horas --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-surface rounded-lg border border-main overflow-x-auto">
            <div class="px-6 py-4 border-b border-main">
                <h2 class="text-sm font-semibold text-primary">Placas más atendidas</h2>
                <p class="text-xs text-secondary">Top {{ count($topPlacas) }} placas del rango.</p>
            </div>
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Placa</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Atenciones</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @forelse($topPlacas as $fila)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm font-medium text-primary">{{ $fila['placa'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['atenciones'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">S/ {{ number_format($fila['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-secondary">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="bg-surface rounded-lg border border-main overflow-x-auto">
            <div class="px-6 py-4 border-b border-main">
                <h2 class="text-sm font-semibold text-primary">Distribución por hora</h2>
                <p class="text-xs text-secondary">Hora de inicio (America/Lima). Solo horas con atenciones.</p>
            </div>
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Hora</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Atenciones</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @php $conDatos = collect($porHora['horas'])->filter(fn ($t) => $t > 0); @endphp
                    @forelse($conDatos as $hora => $total)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ sprintf('%02d:00', $hora) }}{{ $porHora['hora_pico'] === $hora ? ' · pico' : '' }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">{{ $total }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-6 py-8 text-center text-sm text-secondary">Sin atenciones en el rango.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Nota metodológica --}}
    <div class="bg-surface rounded-lg border border-main p-5">
        <h2 class="text-sm font-semibold text-primary mb-2">Cómo se calcula</h2>
        <ul class="text-xs text-secondary space-y-1 list-disc pl-5">
            <li>Universo: lavados y cambios de aceite <strong>confirmados</strong> (los pendientes se excluyen; los eliminados no existen). Rango por fecha efectiva: lavados → <code>fecha</code>, cambios → <code>created_at</code>.</li>
            <li>Tiempo de atención: promedio de <code>tiempo_atencion_minutos</code> (<code>atencion_inicio_at</code> → <code>atencion_fin_at</code>) solo con valor registrado, en minutos enteros.</li>
            <li>Tiempo de espera: no se muestra porque el modelo actual no registra una llegada separada del inicio del ticket; ambos ocurren en <code>atencion_inicio_at</code>.</li>
            <li>Por trabajador: participaciones; en co-atención el ticket suma una vez en cada participante.</li>
            <li>Vehículos únicos: placas (<code>vehiculo_id</code>) distintas y no nulas; las ventas de mostrador no tienen vehículo y se excluyen.</li>
            <li>Ticket operativo: solo lavados + cambios confirmados (el consolidado con ventas está en el reporte de ingresos).</li>
        </ul>
    </div>

</div>
<script>
    window.operativoSerie = @json($serie);
    window.operativoHoras = @json($porHora['horas']);
</script>
@vite(['resources/js/reportes/operativo.js', 'resources/js/reportes/print.js'])
@endsection
