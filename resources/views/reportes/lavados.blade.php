@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Reporte de lavados</h1>
            <p class="mt-1 text-sm text-secondary">Rango aplicado: <strong>{{ $etiqueta }}</strong></p>
        </div>
        @include('reportes.partials.acciones')
    </div>

    {{-- Filtros --}}
    @push('filtros-reporte')
        <div>
            <label for="vehiculo_id" class="label-main mb-1 inline-block">Vehículo</label>
            <select id="vehiculo_id" name="vehiculo_id" class="input-main px-3 py-2 border rounded-lg text-sm">
                <option value="">Todos</option>
                @foreach($vehiculos as $vehiculo)
                    <option value="{{ $vehiculo->id }}" @selected(request('vehiculo_id') == $vehiculo->id)>{{ $vehiculo->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="servicio_id" class="label-main mb-1 inline-block">Servicio</label>
            <select id="servicio_id" name="servicio_id" class="input-main px-3 py-2 border rounded-lg text-sm">
                <option value="">Todos</option>
                @foreach($servicios as $servicio)
                    <option value="{{ $servicio->id }}" @selected(request('servicio_id') == $servicio->id)>{{ $servicio->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="trabajador_id" class="label-main mb-1 inline-block">Trabajador</label>
            <select id="trabajador_id" name="trabajador_id" class="input-main px-3 py-2 border rounded-lg text-sm">
                <option value="">Todos</option>
                @foreach($trabajadores as $trabajador)
                    <option value="{{ $trabajador->id }}" @selected(request('trabajador_id') == $trabajador->id)>{{ $trabajador->nombre_completo }}</option>
                @endforeach
            </select>
        </div>
    @endpush
    @include('reportes.partials.filtros', ['ruta' => 'reportes.lavados'])

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Ingreso total</p>
            <p class="mt-2 text-2xl font-bold text-primary">S/ {{ number_format($kpis['total'], 2) }}</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Lavados confirmados</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ number_format($kpis['operaciones']) }}</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Ticket promedio</p>
            <p class="mt-2 text-2xl font-bold text-primary">S/ {{ number_format($kpis['ticket_promedio'], 2) }}</p>
        </div>
    </div>

    {{-- Agregados --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        @foreach([
            ['titulo' => 'Por vehículo', 'filas' => $porVehiculo, 'colores' => ['azul' => true]],
            ['titulo' => 'Por servicio', 'filas' => $porServicio, 'colores' => ['verde' => true]],
            ['titulo' => 'Por trabajador', 'filas' => $porTrabajador, 'colores' => ['ambar' => true]],
        ] as $bloque)
            <div class="bg-surface rounded-lg border border-main overflow-x-auto">
                <div class="px-6 py-4 border-b border-main">
                    <h2 class="text-sm font-semibold text-primary">{{ $bloque['titulo'] }}</h2>
                </div>
                <table class="min-w-full divide-y divide-main">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Nombre</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Lavados</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface divide-y divide-main">
                        @forelse($bloque['filas'] as $fila)
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
        @endforeach
    </div>

    {{-- Detalle --}}
    <div class="bg-surface rounded-lg border border-main overflow-x-auto">
        <div class="px-6 py-4 border-b border-main">
            <h2 class="text-sm font-semibold text-primary">Detalle</h2>
        </div>
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">ID</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Fecha</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Cliente</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Placa</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Vehículo</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Servicios</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Total</th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @forelse($detalle as $lavado)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $lavado->id }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $lavado->fecha->format('d/m/Y') }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $lavado->cliente?->nombre_completo ?? '—' }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $lavado->automotor?->placa ?? '—' }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $lavado->vehiculo?->nombre ?? '—' }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $lavado->servicios->pluck('nombre')->implode(' + ') }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">S/ {{ number_format($lavado->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-8 text-center text-sm text-secondary">No hay lavados confirmados en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($detalle->hasPages())
        <div class="mt-4"> {{ $detalle->links() }} </div>
    @endif

</div>
@vite('resources/js/reportes/print.js')
@endsection