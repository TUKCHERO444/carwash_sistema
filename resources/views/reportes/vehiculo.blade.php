@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Historial de atención por vehículo</h1>
            <p class="mt-1 text-sm text-secondary">Rango aplicado: <strong>{{ $etiqueta }}</strong></p>
        </div>
        @include('reportes.partials.acciones')
    </div>

    {{-- Filtros --}}
    @push('filtros-reporte')
        <div>
            <label for="placa" class="label-main mb-1 inline-block">Placa <span class="text-red-500">*</span></label>
            <input type="text" id="placa" name="placa" value="{{ request('placa') }}" maxlength="7" placeholder="ABC-123"
                   class="input-main px-3 py-2 border rounded-lg text-sm uppercase">
        </div>
        <div>
            <label for="tipo" class="label-main mb-1 inline-block">Tipo de atención</label>
            <select id="tipo" name="tipo" class="input-main px-3 py-2 border rounded-lg text-sm">
                <option value="">Todas</option>
                <option value="lavado" @selected(request('tipo') === 'lavado')>Lavados</option>
                <option value="cambio_aceite" @selected(request('tipo') === 'cambio_aceite')>Cambios de aceite</option>
            </select>
        </div>
    @endpush
    @include('reportes.partials.filtros', ['ruta' => 'reportes.vehiculo'])

    @if(! $vehiculo)
        <div class="bg-surface rounded-lg border border-main p-12 text-center">
            <p class="text-sm text-secondary">
                @if($placa)
                    No se encontró ningún vehículo con la placa <strong class="text-primary">{{ $placa }}</strong>. Verifique la placa e intente nuevamente.
                @else
                    Ingrese la placa del vehículo para consultar su historial de atenciones (solo operaciones confirmadas).
                @endif
            </p>
        </div>
    @else
        {{-- Ficha del vehículo --}}
        <div class="bg-surface rounded-lg border border-main p-5 mb-6">
            <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
                <div>
                    <p class="text-xs font-medium text-secondary uppercase tracking-wider">Placa</p>
                    <p class="mt-1 text-xl font-bold text-primary">{{ $vehiculo->placa }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-secondary uppercase tracking-wider">Cliente</p>
                    <p class="mt-1 text-sm text-primary">{{ $vehiculo->cliente?->nombre_completo ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-secondary uppercase tracking-wider">Marca / Modelo</p>
                    <p class="mt-1 text-sm text-primary">{{ trim(($vehiculo->marca ?? '').' '.($vehiculo->modelo ?? '')) ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-secondary uppercase tracking-wider">Color</p>
                    <p class="mt-1 text-sm text-primary">{{ $vehiculo->color ?? '—' }}</p>
                </div>
            </div>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-surface rounded-lg border border-main p-5">
                <p class="text-xs font-medium text-secondary uppercase tracking-wider">Operaciones</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ number_format($kpis['operaciones']) }}</p>
                <p class="mt-1 text-xs text-secondary">{{ $kpis['lavados'] }} lavados · {{ $kpis['cambios'] }} cambios</p>
            </div>
            <div class="bg-surface rounded-lg border border-main p-5">
                <p class="text-xs font-medium text-secondary uppercase tracking-wider">Total gastado</p>
                <p class="mt-2 text-2xl font-bold text-primary">S/ {{ number_format($kpis['total'], 2) }}</p>
                <p class="mt-1 text-xs text-secondary">Ticket promedio S/ {{ number_format($kpis['ticket_promedio'], 2) }}</p>
            </div>
            <div class="bg-surface rounded-lg border border-main p-5">
                <p class="text-xs font-medium text-secondary uppercase tracking-wider">Primera / Última atención</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ $kpis['ultima_atencion'] ?? '—' }}</p>
                <p class="mt-1 text-xs text-secondary">
                    @if($kpis['primera_atencion'])
                        Desde {{ $kpis['primera_atencion'] }}
                        @if(! is_null($kpis['dias_promedio_entre_atenciones']))
                            · cada {{ $kpis['dias_promedio_entre_atenciones'] }} días aprox.
                        @endif
                    @else
                        Sin atenciones en el período
                    @endif
                </p>
            </div>
            <div class="bg-surface rounded-lg border border-main p-5">
                <p class="text-xs font-medium text-secondary uppercase tracking-wider">Último cambio de aceite</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ $kpis['ultimo_cambio']['fecha'] ?? '—' }}</p>
                <p class="mt-1 text-xs text-secondary">{{ $kpis['ultimo_cambio']['productos'] ?? 'Sin cambios en el período' }}</p>
            </div>
        </div>

        {{-- Agregados --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <div class="bg-surface rounded-lg border border-main overflow-x-auto">
                <div class="px-6 py-4 border-b border-main">
                    <h2 class="text-sm font-semibold text-primary">Servicios utilizados</h2>
                </div>
                <table class="min-w-full divide-y divide-main">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Servicio</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Veces</th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface divide-y divide-main">
                        @forelse($porServicio as $fila)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $fila['nombre'] }}</td>
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['operaciones'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-6 py-8 text-center text-sm text-secondary">Sin datos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-surface rounded-lg border border-main overflow-x-auto">
                <div class="px-6 py-4 border-b border-main">
                    <h2 class="text-sm font-semibold text-primary">Productos utilizados (cambios)</h2>
                </div>
                <table class="min-w-full divide-y divide-main">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Producto</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Unidades</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface divide-y divide-main">
                        @forelse($porProducto as $fila)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $fila['nombre'] }}</td>
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['cantidad'] }}</td>
                                <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">S/ {{ number_format($fila['total'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-secondary">Sin datos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Historial --}}
        <div class="bg-surface rounded-lg border border-main overflow-x-auto">
            <div class="px-6 py-4 border-b border-main">
                <h2 class="text-sm font-semibold text-primary">Historial cronológico</h2>
            </div>
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Fecha</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Tipo</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Cliente</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Servicios / Productos</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Trabajadores</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Importe</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Estado</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @forelse($detalle as $atencion)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $atencion['fecha'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $atencion['tipo_etiqueta'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $atencion['cliente'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $atencion['detalle'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $atencion['trabajadores'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">S/ {{ number_format($atencion['total'], 2) }}</td>
                            <td class="px-6 py-8 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
                                    {{ ucfirst($atencion['estado']) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-8 text-center text-sm text-secondary">El vehículo no tiene atenciones confirmadas en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($detalle->hasPages())
            <div class="mt-4"> {{ $detalle->links() }} </div>
        @endif
    @endif

</div>
@vite('resources/js/reportes/print.js')
@endsection
