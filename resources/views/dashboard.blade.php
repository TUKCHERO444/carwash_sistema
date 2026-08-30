@extends('layouts.app')

@php
    $soles = fn ($v) => 'S/ ' . number_format((float) $v, 2);
@endphp

@section('content')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Dashboard</h1>
            <p class="text-sm text-secondary mt-1">Resumen de ventas, lavados y cambios de aceite del local.</p>
        </div>

        {{-- Selector de período --}}
        <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
            @foreach ([7 => '7 días', 30 => '30 días', 'mes' => 'Este mes'] as $valor => $etiqueta)
                <button type="submit"
                        name="periodo"
                        value="{{ $valor }}"
                        @class([
                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border',
                            'bg-blue-600 text-white border-blue-600' => (string) $periodo === (string) $valor,
                            'bg-surface border-main text-secondary hover:bg-gray-100 dark:hover:bg-slate-800' => (string) $periodo !== (string) $valor,
                        ])>
                    {{ $etiqueta }}
                </button>
            @endforeach
        </form>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-surface border border-main rounded-xl p-5 shadow-sm">
            <p class="text-sm text-secondary">Ingresos de hoy</p>
            <p class="text-2xl font-bold text-primary mt-1">{{ $soles($resumen['ingresos_hoy']) }}</p>
        </div>
        <div class="bg-surface border border-main rounded-xl p-5 shadow-sm">
            <p class="text-sm text-secondary">Ingresos del mes</p>
            <p class="text-2xl font-bold text-primary mt-1">{{ $soles($resumen['ingresos_mes']) }}</p>
        </div>
        <div class="bg-surface border border-main rounded-xl p-5 shadow-sm">
            <p class="text-sm text-secondary">Operaciones del mes</p>
            <p class="text-2xl font-bold text-primary mt-1">{{ number_format($resumen['operaciones_mes']) }}</p>
        </div>
        <div class="bg-surface border border-main rounded-xl p-5 shadow-sm">
            <p class="text-sm text-secondary">Ticket promedio (mes)</p>
            <p class="text-2xl font-bold text-primary mt-1">{{ $soles($resumen['ticket_promedio_mes']) }}</p>
        </div>
    </div>

    {{-- Desglose mensual por fuente --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-surface border border-main rounded-xl p-5 shadow-sm">
            <p class="text-sm text-secondary">Ventas (mes)</p>
            <p class="text-xl font-semibold text-blue-600 dark:text-blue-400 mt-1">{{ $soles($resumen['ventas_mes']) }}</p>
        </div>
        <div class="bg-surface border border-main rounded-xl p-5 shadow-sm">
            <p class="text-sm text-secondary">Lavados (mes)</p>
            <p class="text-xl font-semibold text-emerald-600 dark:text-emerald-400 mt-1">{{ $soles($resumen['lavados_mes']) }}</p>
        </div>
        <div class="bg-surface border border-main rounded-xl p-5 shadow-sm">
            <p class="text-sm text-secondary">Cambios de aceite (mes)</p>
            <p class="text-xl font-semibold text-amber-600 dark:text-amber-400 mt-1">{{ $soles($resumen['cambios_mes']) }}</p>
        </div>
    </div>

    {{-- Gráficos --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-surface border border-main rounded-xl p-5 shadow-sm lg:col-span-2">
            <h2 class="text-base font-semibold text-primary mb-4">Ingresos por día</h2>
            <div class="h-72">
                <canvas id="chart-series" data-serie="{{ json_encode($serie) }}"></canvas>
            </div>
        </div>
        <div class="bg-surface border border-main rounded-xl p-5 shadow-sm">
            <h2 class="text-base font-semibold text-primary mb-4">Método de pago</h2>
            <div class="h-72">
                <canvas id="chart-metodo-pago" data-pagos="{{ json_encode($metodoPago) }}"></canvas>
            </div>
        </div>
    </div>

    {{-- Tablas --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-surface border border-main rounded-xl p-5 shadow-sm">
            <h2 class="text-base font-semibold text-primary mb-4">Top productos más vendidos</h2>
            @if ($topProductos->isEmpty())
                <p class="text-sm text-secondary">Aún no hay ventas registradas.</p>
            @else
                <ul class="divide-y divide-main">
                    @foreach ($topProductos as $index => $producto)
                        <li class="flex items-center justify-between py-3">
                            <div class="flex items-center gap-3">
                                <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-100 dark:bg-slate-800 text-xs font-semibold text-secondary">
                                    {{ $index + 1 }}
                                </span>
                                <span class="text-sm font-medium text-primary">{{ $producto['nombre'] }}</span>
                            </div>
                            <span class="text-sm text-secondary">{{ number_format($producto['cantidad']) }} u.</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-surface border border-main rounded-xl p-5 shadow-sm">
            <h2 class="text-base font-semibold text-primary mb-4">Productos con stock bajo</h2>
            @if ($stockBajo->isEmpty())
                <p class="text-sm text-secondary">No hay productos con stock bajo.</p>
            @else
                <ul class="divide-y divide-main">
                    @foreach ($stockBajo as $producto)
                        <li class="flex items-center justify-between py-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-primary truncate">{{ $producto->nombre }}</p>
                                @if ($producto->categoria)
                                    <p class="text-xs text-secondary">{{ $producto->categoria->nombre }}</p>
                                @endif
                            </div>
                            <span @class([
                                'text-sm font-semibold',
                                'text-red-600 dark:text-red-400' => $producto->stock <= 2,
                                'text-amber-600 dark:text-amber-400' => $producto->stock > 2,
                            ])>
                                {{ $producto->stock }} u.
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
