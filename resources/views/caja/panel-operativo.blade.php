@extends('layouts.app')

@section('content')
<div class="p-6 max-w-5xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-text-primary-dark">Caja Chica</h1>
        <p class="text-sm text-gray-500 dark:text-text-secondary-dark">Estado operativo de la caja activa</p>
    </div>

    @include('caja.partials.mensajes')

    @if(!$caja)
        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-12 text-center transition-colors duration-300">
            <div class="w-16 h-16 bg-gray-100 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-400 dark:text-text-secondary-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-text-primary-dark mb-2">Caja cerrada</h3>
            <p class="text-gray-500 dark:text-text-secondary-dark">No hay una caja activa. Un administrador debe aperturarla para registrar ventas y egresos.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-6 transition-colors duration-300">
                <p class="text-sm font-medium text-gray-500 dark:text-text-secondary-dark mb-1">Estado</p>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">Caja abierta</p>
                <p class="text-xs text-gray-400 dark:text-text-secondary-dark mt-2">Desde las {{ $caja->fecha_apertura->format('H:i') }}</p>
            </div>
            <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-6 transition-colors duration-300">
                <p class="text-sm font-medium text-gray-500 dark:text-text-secondary-dark mb-1">Total Ingresos</p>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">S/ {{ number_format($resumen['total_ingresos'], 2) }}</p>
                <p class="text-xs text-gray-400 dark:text-text-secondary-dark mt-2">Acumulado de la caja</p>
            </div>
            <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-6 transition-colors duration-300">
                <p class="text-sm font-medium text-gray-500 dark:text-text-secondary-dark mb-1">Total Egresos</p>
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">S/ {{ number_format($resumen['total_egresos'], 2) }}</p>
                <p class="text-xs text-gray-400 dark:text-text-secondary-dark mt-2">Acumulado de la caja</p>
            </div>
        </div>

        @if($puedeRegistrarEgreso)
            <div class="flex justify-end">
                <button type="button" data-modal-open="modal-registrar-egreso" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                    Registrar Egreso
                </button>
            </div>
        @endif
    @endif
</div>

@include('caja.partials.modal-egreso')

@endsection
