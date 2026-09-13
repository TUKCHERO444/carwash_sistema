@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Reporte de clientes</h1>
            <p class="mt-1 text-sm text-secondary">Rango aplicado: <strong>{{ $etiqueta }}</strong></p>
        </div>
        @include('reportes.partials.acciones')
    </div>

    {{-- Filtros --}}
    @include('reportes.partials.filtros', ['ruta' => 'reportes.clientes'])

    @php
        $totalGastoTop = collect($topClientes)->sum('gasto');
    @endphp

    {{-- Top clientes --}}
    <div class="bg-surface rounded-lg border border-main overflow-x-auto mb-6">
        <div class="px-6 py-4 border-b border-main">
            <h2 class="text-sm font-semibold text-primary">Top clientes por gasto</h2>
            <p class="text-xs text-secondary">Ventas, lavados confirmados y cambios de aceite confirmados del rango. Total: S/ {{ number_format($totalGastoTop, 2) }}</p>
        </div>
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Cliente</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Gasto</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Visitas</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Automotores</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Visitas / mes</th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @forelse($topClientes as $fila)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 whitespace-nowrap text-sm font-medium text-primary">{{ $fila['nombre'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">S/ {{ number_format($fila['gasto'], 2) }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['visitas'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['automotores'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ number_format($fila['visitas_por_mes'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-secondary">Sin datos en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        {{-- Top automotores --}}
        <div class="bg-surface rounded-lg border border-main overflow-x-auto">
            <div class="px-6 py-4 border-b border-main">
                <h2 class="text-sm font-semibold text-primary">Automotores más atendidos</h2>
            </div>
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Placa</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Cliente</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Visitas</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @forelse($topAutomotores as $fila)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm font-medium text-primary">{{ $fila['placa'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $fila['cliente'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['visitas'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-secondary">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Detalle frecuencias --}}
        <div class="bg-surface rounded-lg border border-main overflow-x-auto">
            <div class="px-6 py-4 border-b border-main">
                <h2 class="text-sm font-semibold text-primary">Frecuencia por cliente</h2>
                <p class="text-xs text-secondary">Gasto, visitas y automotores registrados del rango.</p>
            </div>
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Cliente</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Gasto</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Visitas</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Automotores</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Visitas / mes</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @forelse($detalle as $fila)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $fila['nombre'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">S/ {{ number_format($fila['gasto'], 2) }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['visitas'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['automotores'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ number_format($fila['visitas_por_mes'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-secondary">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@vite('resources/js/reportes/print.js')
@endsection