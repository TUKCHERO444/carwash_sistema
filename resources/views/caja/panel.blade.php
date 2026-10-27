@extends('layouts.app')

@section('content')
<div class="p-6 max-w-7xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-text-primary-dark">Caja General</h1>
            <p class="text-sm text-gray-500 dark:text-text-secondary-dark">Resumen general de la caja activa</p>
        </div>
        <div class="flex items-center gap-3">
            @can('historial-caja')
                <a href="{{ route('caja.historial') }}" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-text-primary-dark bg-white dark:bg-slate-800 border border-gray-300 dark:border-border-dark rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                    Historial
                </a>
            @endcan
            
            @if(!$caja)
                @can('gestionar-caja')
                    <button type="button" data-modal-open="modal-abrir-caja" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                        Iniciar Caja
                    </button>
                @endcan
            @else
                @if($puedeRegistrarEgreso)
                    <button type="button" data-modal-open="modal-registrar-egreso" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-text-primary-dark bg-white dark:bg-slate-800 border border-gray-300 dark:border-border-dark rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                        Registrar Egreso
                    </button>
                @endif
                @can('gestionar-caja')
                    <button type="button" data-modal-open="modal-cerrar-caja" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                        Cerrar Caja
                    </button>
                @endcan
            @endif
        </div>
    </div>

    @include('caja.partials.mensajes')

    @if(!$caja)
        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-12 text-center transition-colors duration-300">
            <div class="w-16 h-16 bg-gray-100 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-400 dark:text-text-secondary-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-text-primary-dark mb-2">Caja Cerrada</h3>
            <p class="text-gray-500 dark:text-text-secondary-dark mb-6">No hay ninguna sesión de caja activa en este momento.</p>
            @can('gestionar-caja')
                <button type="button" data-modal-open="modal-abrir-caja" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                    Iniciar Caja
                </button>
            @else
                <p class="text-sm text-gray-500 dark:text-text-secondary-dark">Solicita a un administrador que aperture la caja.</p>
            @endcan
        </div>
    @else
        <!-- Tarjetas de Resumen -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-6 transition-colors duration-300">
                <p class="text-sm font-medium text-gray-500 dark:text-text-secondary-dark mb-1">Monto Inicial</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-text-primary-dark">S/ {{ number_format($caja->monto_inicial, 2) }}</p>
                <p class="text-xs text-gray-400 dark:text-text-secondary-dark mt-2">Apertura: {{ $caja->fecha_apertura->format('H:i') }}</p>
            </div>
            <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-6 transition-colors duration-300">
                <p class="text-sm font-medium text-gray-500 dark:text-text-secondary-dark mb-1">Total Ingresos</p>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">S/ {{ number_format($resumen['total_ingresos'], 2) }}</p>
                <p class="text-xs text-gray-400 dark:text-text-secondary-dark mt-2">Ventas, Aceite, Ingresos Vehiculares</p>
            </div>
            <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-6 transition-colors duration-300">
                <p class="text-sm font-medium text-gray-500 dark:text-text-secondary-dark mb-1">Total Egresos</p>
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">S/ {{ number_format($resumen['total_egresos'], 2) }}</p>
                <p class="text-xs text-gray-400 dark:text-text-secondary-dark mt-2">Retiros manuales</p>
            </div>
            <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border-blue-500 dark:border-blue-700/50 p-6 relative overflow-hidden transition-colors duration-300">
                <div class="absolute right-0 top-0 w-24 h-24 bg-blue-50 dark:bg-blue-900/10 rounded-full -mr-8 -mt-8 opacity-50"></div>
                <p class="text-sm font-medium text-blue-800 dark:text-blue-400 mb-1 relative z-10">Balance Neto</p>
                <p class="text-2xl font-bold text-blue-900 dark:text-blue-300 relative z-10">S/ {{ number_format($resumen['balance_final'], 2) }}</p>
                <p class="text-xs text-blue-600 dark:text-blue-500 mt-2 relative z-10">Dinero esperado en caja</p>
            </div>
        </div>

        <!-- Distribución de Pagos -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-6 transition-colors duration-300">
                <h3 class="text-base font-semibold text-gray-900 dark:text-text-primary-dark mb-4">Ingresos por Método de Pago</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @forelse($resumen['por_metodo'] as $codigoMetodo => $montoMetodo)
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 rounded-lg">
                            <span class="text-sm font-medium text-gray-600 dark:text-text-secondary-dark">{{ $nombresMetodos[$codigoMetodo] ?? ucfirst($codigoMetodo) }}</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-text-primary-dark">S/ {{ number_format($montoMetodo, 2) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-text-secondary-dark">Sin ingresos por método en esta caja.</p>
                    @endforelse
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
                                ->concat($caja->lavados->map(fn($i) => ['tipo' => 'Lavado Vehicular', 'total' => $i->total, 'metodo' => $i->metodo_pago, 'hora' => $i->created_at]))
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
                                No se han registrado ingresos en esta caja.
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
                                    <p class="text-xs text-gray-500 dark:text-text-secondary-dark">{{ $egreso->created_at->format('H:i:s') }} - {{ ucfirst($egreso->tipo_pago) }} · {{ \App\Models\EgresoCaja::etiquetaCategoria($egreso->categoria) }}</p>
                                </div>
                                <span class="text-sm font-bold text-red-600 dark:text-red-400">- S/ {{ number_format($egreso->monto, 2) }}</span>
                            </li>
                        @empty
                            <li class="px-6 py-8 text-center text-sm text-gray-500">
                                No se han registrado egresos en esta caja.
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Modal Abrir Caja -->
@if(!$caja)
@can('gestionar-caja')
<x-modal id="modal-abrir-caja" title="Iniciar Sesión de Caja" :open="session()->hasOldInput('monto_inicial')">
    <form action="{{ route('caja.abrir') }}" method="POST" id="form-abrir-caja">
        @csrf
        <div class="px-6 pb-4 sm:pb-6">
            <div class="flex items-start gap-3 mb-4">
                <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900/30 sm:h-10 sm:w-10">
                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                </div>
                <p class="text-sm text-secondary">Registra el monto con el que abres la caja del día.</p>
            </div>
            <label for="monto_inicial" class="block text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Monto Inicial (S/)</label>
            <input type="number" step="0.01" min="0.01" name="monto_inicial" id="monto_inicial" required value="{{ old('monto_inicial') }}" class="mt-1 block w-full border-gray-300 dark:border-border-dark bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="0.00">
        </div>
    </form>
    <x-slot:footer>
        <button type="submit" form="form-abrir-caja" class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:text-sm">
            Iniciar Caja
        </button>
        <button type="button" data-modal-close class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 dark:border-border-dark shadow-sm px-4 py-2 bg-white dark:bg-slate-800 text-base font-medium text-gray-700 dark:text-text-primary-dark hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm transition-colors">
            Cancelar
        </button>
    </x-slot:footer>
</x-modal>
@endcan
@endif

@include('caja.partials.modal-egreso')

@if($caja)
@can('gestionar-caja')
<!-- Modal Cerrar Caja con Arqueo -->
<x-modal id="modal-cerrar-caja" title="Arqueo y Cierre de Caja" maxWidth="lg" :open="session()->hasOldInput('efectivo_contado')">
    <form action="{{ route('caja.cerrar') }}" method="POST" id="form-cerrar-caja">
        @csrf
        <div class="px-6 pb-4 sm:pb-6 space-y-4">
            <p class="text-sm text-secondary">Cuente el efectivo físico de la caja. El sistema calcula el esperado y la diferencia automáticamente.</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-4 bg-gray-50 dark:bg-slate-800/50 rounded-lg">
                    <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Esperado</p>
                    <p class="mt-1 text-xl font-bold text-gray-900 dark:text-text-primary-dark">S/ {{ number_format($efectivoEsperado, 2) }}</p>
                </div>
                <div>
                    <label for="efectivo_contado" class="block text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Contado (S/) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" name="efectivo_contado" id="efectivo_contado" required
                           data-esperado="{{ number_format($efectivoEsperado, 2, '.', '') }}"
                           value="{{ old('efectivo_contado') }}"
                           class="mt-1 block w-full border-gray-300 dark:border-border-dark bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="0.00">
                </div>
                <div class="p-4 bg-gray-50 dark:bg-slate-800/50 rounded-lg">
                    <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Diferencia</p>
                    <p id="diferencia-preview" class="mt-1 text-xl font-bold text-gray-900 dark:text-text-primary-dark">S/ 0.00</p>
                    <p id="diferencia-etiqueta" class="text-xs text-gray-500 dark:text-text-secondary-dark"></p>
                </div>
            </div>
            <div>
                <label for="observacion_arqueo" class="block text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Observación <span class="text-xs text-gray-400">(obligatoria si hay diferencia)</span></label>
                <textarea name="observacion_arqueo" id="observacion_arqueo" rows="2" maxlength="500"
                          class="mt-1 block w-full border-gray-300 dark:border-border-dark bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                          placeholder="Ej. Faltante por vuelto mal dado...">{{ old('observacion_arqueo') }}</textarea>
            </div>
        </div>
    </form>
    <x-slot:footer>
        <button type="submit" form="form-cerrar-caja" class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:text-sm">
            Registrar Arqueo y Cerrar
        </button>
        <button type="button" data-modal-close class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 dark:border-border-dark shadow-sm px-4 py-2 bg-white dark:bg-slate-800 text-base font-medium text-gray-700 dark:text-text-primary-dark hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:text-sm transition-colors">
            Cancelar
        </button>
    </x-slot:footer>
</x-modal>
@endcan
@endif

@can('gestionar-caja')
    @vite('resources/js/caja/cerrar.js')
@endcan

@endsection
