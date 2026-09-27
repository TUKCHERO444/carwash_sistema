@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Flash messages --}}
    @if(session('success'))
        <div role="alert" class="mb-4 px-4 py-3 rounded-lg bg-green-100 text-green-800 border border-green-200 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div role="alert" class="mb-4 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-200 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">
            Ajuste {{ $ajuste->correlativo }}
        </h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('ajustes.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Volver
            </a>
        </div>
    </div>

    {{-- Tarjeta principal --}}
    <div class="bg-surface rounded-lg border border-main p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Correlativo</p>
                <p class="text-sm font-mono text-primary">{{ $ajuste->correlativo }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Tipo</p>
                <p class="text-sm">
                    @php
                        $badge = match($ajuste->tipo) {
                            'positivo' => ['Ajuste Positivo', 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'],
                            'negativo' => ['Ajuste Negativo', 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'],
                            'merma' => ['Merma', 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'],
                            'daño' => ['Daño', 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'],
                            'conteo_fisico' => ['Conteo Físico', 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'],
                            default => ['—', 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400'],
                        };
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge[1] }}">
                        {{ $badge[0] }}
                    </span>
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Usuario</p>
                <p class="text-sm text-secondary">{{ $ajuste->user->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Fecha</p>
                <p class="text-sm text-secondary">{{ $ajuste->created_at->format('d/m/Y H:i') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Motivo</p>
                <p class="text-sm text-secondary">{{ $ajuste->motivo ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Observaciones</p>
                <p class="text-sm text-secondary whitespace-pre-wrap">{{ $ajuste->observaciones ?? '—' }}</p>
            </div>
        </div>
    </div>

    {{-- Detalle de líneas --}}
    <div class="bg-surface rounded-lg border border-main overflow-x-auto mb-6">
        <h3 class="px-6 py-4 text-sm font-medium text-gray-800 dark:text-text-primary-dark border-b border-main">Líneas del Ajuste</h3>
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Producto
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Cantidad
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Stock Antes
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Stock Después
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Tipo Movimiento
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Kardex
                    </th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @foreach($ajuste->detalles as $detalle)
                    @php
                        $esEntrada = $detalle->cantidad > 0;
                        $movimiento = $detalle->kardex->first();
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 text-sm text-primary font-medium">
                            {{ $detalle->producto->nombre ?? 'Producto eliminado' }}
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary font-mono">
                            {{ $detalle->cantidad > 0 ? '+' : '' }}{{ $detalle->cantidad }}
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary font-mono">
                            {{ $detalle->stock_antes }}
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary font-mono">
                            {{ $detalle->stock_despues }}
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $detalle->cantidad > 0
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                    : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' }}">
                                {{ $detalle->cantidad > 0 ? 'Entrada' : 'Salida' }}
                            </span>
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm">
                            @if($movimiento)
                                <a href="{{ route('kardex.show', $movimiento) }}"
                                   class="text-blue-600 dark:text-blue-400 hover:underline text-xs">
                                    Ver movimiento
                                </a>
                            @else
                                <span class="text-gray-400 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Kardex generado --}}
    @if($ajuste->detalles->flatMap->kardex->count())
    <div class="bg-surface rounded-lg border border-main overflow-x-auto">
        <h3 class="px-6 py-4 text-sm font-medium text-gray-800 dark:text-text-primary-dark border-b border-main">Movimientos Kardex Generados</h3>
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Fuente
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Tipo
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Cantidad
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Stock Antes
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Stock Después
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Costo Unit.
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Costo Total
                    </th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @foreach($ajuste->detalles->flatMap->kardex as $movimiento)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 text-sm text-secondary">{{ $movimiento->fuente }}</td>
                        <td class="px-6 py-8 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $movimiento->tipo === 'entrada'
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                    : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' }}">
                                {{ $movimiento->tipo === 'entrada' ? 'Entrada' : 'Salida' }}
                            </span>
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary font-mono">{{ $movimiento->cantidad }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary font-mono">{{ $movimiento->stock_antes }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary font-mono">{{ $movimiento->stock_despues }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary font-mono">S/ {{ number_format($movimiento->costo_unitario, 2) }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary font-mono">S/ {{ number_format($movimiento->costo_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>
@endsection