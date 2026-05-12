@extends('layouts.app')

@section('content')
<div class="p-6 max-w-7xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-text-primary-dark">Historial de Cajas</h1>
        <a href="{{ route('caja.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-text-primary-dark bg-white dark:bg-slate-800 border border-gray-300 dark:border-border-dark rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver a Caja
        </a>
    </div>

    <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark overflow-hidden transition-colors duration-300">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-border-dark">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Fecha / Usuario</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Monto Inicial</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider text-green-600">Ingresos</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider text-red-600">Egresos</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-blue-800 uppercase tracking-wider">Balance Final</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-surface-dark divide-y divide-gray-200 dark:divide-border-dark">
                    @forelse($cajas as $c)
                        @php
                            // Se asume que total_ingresos y total_egresos podrían ser agregados si es necesario, 
                            // pero es mejor tener una forma rápida de verlos sin calcular por registro.
                            // Si el controlador no los pasa calculados en el index, se debe usar calcularResumen o guardarlo en la tabla.
                            // Dado el diseño, el balance final no se guarda en la tabla cajas, se calcula al vuelo.
                            // Por rendimiento, en historial sería ideal que estuvieran guardados, 
                            // pero según el diseño, usamos calcularResumen.
                            $resumenHistorial = app(App\Services\CajaService::class)->calcularResumen($c);
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-text-primary-dark">{{ $c->fecha_apertura->format('d/m/Y') }}</div>
                                <div class="text-xs text-gray-500 dark:text-text-secondary-dark">{{ $c->fecha_apertura->format('H:i') }} - {{ $c->fecha_cierre ? $c->fecha_cierre->format('H:i') : 'En curso' }}</div>
                                <div class="text-xs text-gray-500 dark:text-text-secondary-dark">Por: {{ $c->user->name }}</div>
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-text-primary-dark">S/ {{ number_format($c->monto_inicial, 2) }}</div>
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap">
                                <div class="text-sm text-green-600 font-medium">+ S/ {{ number_format($resumenHistorial['total_ingresos'], 2) }}</div>
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap">
                                <div class="text-sm text-red-600 dark:text-red-400 font-medium">- S/ {{ number_format($resumenHistorial['total_egresos'], 2) }}</div>
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap">
                                <div class="text-sm text-blue-900 dark:text-blue-400 font-bold">S/ {{ number_format($resumenHistorial['balance_final'], 2) }}</div>
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('caja.detalle', $c->id) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">Ver Detalle</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                                No hay cajas cerradas en el historial.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($cajas->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-border-dark">
                {{ $cajas->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
