@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Reporte de ingresos</h1>
            <p class="mt-1 text-sm text-secondary">Rango aplicado: <strong>{{ $etiqueta }}</strong></p>
        </div>
        @include('reportes.partials.acciones')
    </div>

    {{-- Filtros --}}
    @include('reportes.partials.filtros', ['ruta' => 'reportes.ingresos'])

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Ingreso total</p>
            <p class="mt-2 text-2xl font-bold text-primary">S/ {{ number_format($consolidado['total'], 2) }}</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Operaciones</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ number_format($consolidado['operaciones']) }}</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Ticket promedio</p>
            <p class="mt-2 text-2xl font-bold text-primary">S/ {{ number_format($consolidado['ticket_promedio'], 2) }}</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Por método de pago</p>
            <p class="mt-2 text-sm text-primary">
                Efectivo: S/ {{ number_format($metodoPago['efectivo'], 2) }} · Yape: S/ {{ number_format($metodoPago['yape'], 2) }} · Izipay: S/ {{ number_format($metodoPago['izipay'], 2) }}
            </p>
        </div>
    </div>

    {{-- Desglose por fuente --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <p class="text-xs font-medium text-blue-700 dark:text-blue-400 uppercase tracking-wider">Ventas</p>
            <p class="mt-1 text-lg font-bold text-blue-800 dark:text-blue-300">S/ {{ number_format($consolidado['ventas'], 2) }}</p>
        </div>
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
            <p class="text-xs font-medium text-green-700 dark:text-green-400 uppercase tracking-wider">Lavados</p>
            <p class="mt-1 text-lg font-bold text-green-800 dark:text-green-300">S/ {{ number_format($consolidado['lavados'], 2) }}</p>
        </div>
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
            <p class="text-xs font-medium text-amber-700 dark:text-amber-400 uppercase tracking-wider">Cambio de aceite</p>
            <p class="mt-1 text-lg font-bold text-amber-800 dark:text-amber-300">S/ {{ number_format($consolidado['cambios'], 2) }}</p>
        </div>
    </div>

    {{-- Gráficos --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="lg:col-span-2 bg-surface rounded-lg border border-main p-5">
            <h2 class="text-sm font-semibold text-primary mb-4">Ingresos diarios por fuente</h2>
            <div class="h-72">
                <canvas id="chart-reporte-ingresos"></canvas>
            </div>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <h2 class="text-sm font-semibold text-primary mb-4">Método de pago</h2>
            <div class="h-72">
                <canvas id="chart-reporte-metodo-pago"></canvas>
            </div>
        </div>
    </div>

    {{-- Días de mayor ingreso --}}
    <div class="bg-surface rounded-lg border border-main overflow-x-auto">
        <div class="px-6 py-4 border-b border-main">
            <h2 class="text-sm font-semibold text-primary">Días de mayor ingreso</h2>
            <p class="text-xs text-secondary">Top {{ count($diasTop) }} días del rango por ingreso consolidado.</p>
        </div>
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Fecha</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Ventas</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Lavados</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Cambio de aceite</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Total</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Actividad dominante</th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @forelse($diasTop as $dia)
                    @php
                        $badges = [
                            'ventas' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
                            'lavados' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                            'cambio_aceite' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
                        ];
                        $labels = ['ventas' => 'Ventas', 'lavados' => 'Lavados', 'cambio_aceite' => 'Cambio de aceite'];
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 whitespace-nowrap text-sm font-medium text-primary">
                            {{ \Carbon\Carbon::parse($dia['fecha'])->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">S/ {{ number_format($dia['ventas'], 2) }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">S/ {{ number_format($dia['lavados'], 2) }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">S/ {{ number_format($dia['cambios'], 2) }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary">S/ {{ number_format($dia['total'], 2) }}</td>
                        <td class="px-6 py-8 whitespace-nowrap">
                            @if($dia['actividad_dominante'])
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badges[$dia['actividad_dominante']] }}">
                                    {{ $labels[$dia['actividad_dominante']] }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-secondary-dark">
                                    Mixto
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-sm text-secondary">No hay registros en el período.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
<script>
    window.reportesIngresos = @json($serie);
    window.reportesMetodoPago = @json($metodoPago);
</script>
@vite(['resources/js/reportes/ingresos.js', 'resources/js/reportes/print.js'])
@endsection