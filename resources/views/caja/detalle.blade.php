@extends('layouts.app')

@section('content')
<div class="p-6 max-w-7xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-text-primary-dark">Detalle de Caja Cerrada</h1>
            <p class="text-sm text-gray-500 dark:text-text-secondary-dark mt-1">Apertura: {{ $caja->fecha_apertura->format('d/m/Y H:i') }} | Cierre: {{ $caja->fecha_cierre->format('d/m/Y H:i') }} | Usuario: {{ $caja->user->name }}</p>
        </div>
        <a href="{{ route('caja.historial') }}" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-text-primary-dark bg-white dark:bg-slate-800 border border-gray-300 dark:border-border-dark rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver al Historial
        </a>
    </div>

    <!-- Tarjetas de Resumen -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-6 transition-colors duration-300">
            <p class="text-sm font-medium text-gray-500 dark:text-text-secondary-dark mb-1">Monto Inicial</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-text-primary-dark">S/ {{ number_format($caja->monto_inicial, 2) }}</p>
        </div>
        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-6 transition-colors duration-300">
            <p class="text-sm font-medium text-gray-500 dark:text-text-secondary-dark mb-1">Total Ingresos</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400">S/ {{ number_format($resumen['total_ingresos'], 2) }}</p>
        </div>
        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-6 transition-colors duration-300">
            <p class="text-sm font-medium text-gray-500 dark:text-text-secondary-dark mb-1">Total Egresos</p>
            <p class="text-2xl font-bold text-red-600 dark:text-red-400">S/ {{ number_format($resumen['total_egresos'], 2) }}</p>
        </div>
        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border-blue-500 dark:border-blue-700/50 p-6 relative overflow-hidden transition-colors duration-300">
            <div class="absolute right-0 top-0 w-24 h-24 bg-blue-50 dark:bg-blue-900/10 rounded-full -mr-8 -mt-8 opacity-50"></div>
            <p class="text-sm font-medium text-blue-800 dark:text-blue-400 mb-1 relative z-10">Balance Final</p>
            <p class="text-2xl font-bold text-blue-900 dark:text-blue-300 relative z-10">S/ {{ number_format($resumen['balance_final'], 2) }}</p>
        </div>
    </div>

    <!-- Distribución de Pagos -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-6 transition-colors duration-300">
            <h3 class="text-base font-semibold text-gray-900 dark:text-text-primary-dark mb-4">Ingresos por Método de Pago</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 rounded-lg">
                    <span class="text-sm font-medium text-gray-600 dark:text-text-secondary-dark">Efectivo</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-text-primary-dark">S/ {{ number_format($resumen['monto_efectivo'], 2) }}</span>
                </div>
                <div class="flex items-center justify-between p-4 bg-purple-50 dark:bg-purple-900/10 rounded-lg border border-purple-100 dark:border-purple-900/30">
                    <span class="text-sm font-medium text-purple-800 dark:text-purple-400">Yape</span>
                    <span class="text-lg font-bold text-purple-900 dark:text-purple-300">S/ {{ number_format($resumen['monto_yape'], 2) }}</span>
                </div>
                <div class="flex items-center justify-between p-4 bg-blue-50 dark:bg-blue-900/10 rounded-lg border border-blue-100 dark:border-blue-900/30">
                    <span class="text-sm font-medium text-blue-800 dark:text-blue-400">Izipay</span>
                    <span class="text-lg font-bold text-blue-900 dark:text-blue-300">S/ {{ number_format($resumen['monto_izipay'], 2) }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-6 transition-colors duration-300">
            <h3 class="text-base font-semibold text-gray-900 dark:text-text-primary-dark mb-4">Ingresos por Origen</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex flex-col p-4 bg-orange-50 dark:bg-orange-900/10 rounded-lg border border-orange-100 dark:border-orange-900/30">
                    <span class="text-xs font-medium text-orange-800 dark:text-orange-400 uppercase tracking-wider mb-1">Ventas</span>
                    <span class="text-lg font-bold text-orange-900 dark:text-orange-300">S/ {{ number_format($resumen['total_ventas'], 2) }}</span>
                </div>
                <div class="flex flex-col p-4 bg-green-50 dark:bg-green-900/10 rounded-lg border border-green-100 dark:border-green-900/30">
                    <span class="text-xs font-medium text-green-800 dark:text-green-400 uppercase tracking-wider mb-1">Vehicular</span>
                    <span class="text-lg font-bold text-green-900 dark:text-green-300">S/ {{ number_format($resumen['total_ingresos_vehiculares'], 2) }}</span>
                </div>
                <div class="flex flex-col p-4 bg-blue-50 dark:bg-blue-900/10 rounded-lg border border-blue-100 dark:border-blue-900/30">
                    <span class="text-xs font-medium text-blue-800 dark:text-blue-400 uppercase tracking-wider mb-1">Aceite</span>
                    <span class="text-lg font-bold text-blue-900 dark:text-blue-300">S/ {{ number_format($resumen['total_cambios'], 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tablas de Detalle -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Ingresos -->
        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark overflow-hidden flex flex-col transition-colors duration-300">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-border-dark bg-gray-50 dark:bg-slate-800/50">
                <h3 class="font-semibold text-gray-900 dark:text-text-primary-dark">Detalle de Ingresos</h3>
            </div>
            <div class="flex-1 overflow-y-auto" style="max-height: 400px;">
                <ul class="divide-y divide-gray-200 dark:divide-border-dark">
                    @php
                        $todosIngresos = collect()
                            ->concat($caja->ventas->map(fn($v) => ['tipo' => 'Venta', 'total' => $v->total, 'metodo' => $v->metodo_pago, 'hora' => $v->created_at]))
                            ->concat($caja->cambioAceites->map(fn($c) => ['tipo' => 'Cambio de Aceite', 'total' => $c->total, 'metodo' => $c->metodo_pago, 'hora' => $c->created_at]))
                            ->concat($caja->ingresos->where('estado', 'confirmado')->map(fn($i) => ['tipo' => 'Ingreso Vehicular', 'total' => $i->total, 'metodo' => $i->metodo_pago, 'hora' => $i->created_at]))
                            ->sortByDesc('hora');
                    @endphp
                    
                    @forelse($todosIngresos as $ing)
                        <li class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-text-primary-dark">{{ $ing['tipo'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-text-secondary-dark">{{ $ing['hora']->format('H:i:s') }} - {{ ucfirst($ing['metodo']) }}</p>
                            </div>
                            <span class="text-sm font-bold text-green-600 dark:text-green-400">+ S/ {{ number_format($ing['total'], 2) }}</span>
                        </li>
                    @empty
                        <li class="px-6 py-8 text-center text-sm text-gray-500">
                            No se registraron ingresos.
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>

        <!-- Egresos -->
        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark overflow-hidden flex flex-col transition-colors duration-300">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-border-dark bg-gray-50 dark:bg-slate-800/50">
                <h3 class="font-semibold text-gray-900 dark:text-text-primary-dark">Detalle de Egresos</h3>
            </div>
            <div class="flex-1 overflow-y-auto" style="max-height: 400px;">
                <ul class="divide-y divide-gray-200 dark:divide-border-dark">
                    @forelse($caja->egresos as $egreso)
                        <li class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-text-primary-dark">{{ $egreso->descripcion }}</p>
                                <p class="text-xs text-gray-500 dark:text-text-secondary-dark">{{ $egreso->created_at->format('H:i:s') }} - {{ ucfirst($egreso->tipo_pago) }} | Usuario: {{ $egreso->user->name }}</p>
                            </div>
                            <span class="text-sm font-bold text-red-600 dark:text-red-400">- S/ {{ number_format($egreso->monto, 2) }}</span>
                        </li>
                    @empty
                        <li class="px-6 py-8 text-center text-sm text-gray-500">
                            No se registraron egresos.
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
