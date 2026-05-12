@extends('layouts.app')

@section('content')
<div class="p-6 max-w-7xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-text-primary-dark">Panel de Caja</h1>
        <div class="flex items-center gap-3">
            @if(auth()->user()?->hasRole('Administrador'))
                <a href="{{ route('caja.historial') }}" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-text-primary-dark bg-white dark:bg-slate-800 border border-gray-300 dark:border-border-dark rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                    Historial
                </a>
            @endif
            
            @if(!$caja)
                <button type="button" onclick="document.getElementById('modal-abrir-caja').classList.remove('hidden')" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                    Iniciar Caja
                </button>
            @else
                <button type="button" onclick="document.getElementById('modal-registrar-egreso').classList.remove('hidden')" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-text-primary-dark bg-white dark:bg-slate-800 border border-gray-300 dark:border-border-dark rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                    Registrar Egreso
                </button>
                <button type="button" onclick="document.getElementById('modal-cerrar-caja').classList.remove('hidden')" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                    Cerrar Caja
                </button>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50">
            {{ session('error') }}
        </div>
    @endif
    
    @if($errors->any())
        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!$caja)
        <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-gray-200 dark:border-border-dark p-12 text-center transition-colors duration-300">
            <div class="w-16 h-16 bg-gray-100 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-400 dark:text-text-secondary-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-text-primary-dark mb-2">Caja Cerrada</h3>
            <p class="text-gray-500 dark:text-text-secondary-dark mb-6">No hay ninguna sesión de caja activa en este momento.</p>
            <button type="button" onclick="document.getElementById('modal-abrir-caja').classList.remove('hidden')" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                Iniciar Caja
            </button>
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
                                ->concat($caja->ingresos->map(fn($i) => ['tipo' => 'Ingreso Vehicular', 'total' => $i->total, 'metodo' => $i->metodo_pago, 'hora' => $i->created_at]))
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
                                    <p class="text-xs text-gray-500 dark:text-text-secondary-dark">{{ $egreso->created_at->format('H:i:s') }} - {{ ucfirst($egreso->tipo_pago) }}</p>
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
<div id="modal-abrir-caja" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-900/75 transition-opacity" onclick="document.getElementById('modal-abrir-caja').classList.add('hidden')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="relative inline-block align-bottom bg-white dark:bg-surface-dark rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md w-full border dark:border-border-dark">
            <form action="{{ route('caja.abrir') }}" method="POST">
                @csrf
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900/30 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-text-primary-dark" id="modal-title">Iniciar Sesión de Caja</h3>
                            <div class="mt-4">
                                <label for="monto_inicial" class="block text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Monto Inicial (S/)</label>
                                <input type="number" step="0.01" min="0.01" name="monto_inicial" id="monto_inicial" required class="mt-1 block w-full border-gray-300 dark:border-border-dark bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-slate-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t dark:border-border-dark">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Iniciar Caja
                    </button>
                    <button type="button" onclick="document.getElementById('modal-abrir-caja').classList.add('hidden')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-border-dark shadow-sm px-4 py-2 bg-white dark:bg-slate-800 text-base font-medium text-gray-700 dark:text-text-primary-dark hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<!-- Modal Registrar Egreso -->
@if($caja)
<div id="modal-registrar-egreso" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-900/75 transition-opacity" onclick="document.getElementById('modal-registrar-egreso').classList.add('hidden')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="relative inline-block align-bottom bg-white dark:bg-surface-dark rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md w-full border dark:border-border-dark">
            <form action="{{ route('caja.egresos.store') }}" method="POST">
                @csrf
                <div class="bg-white dark:bg-surface-dark px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-text-primary-dark mb-4">Registrar Egreso Manual</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="monto" class="block text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Monto (S/)</label>
                            <input type="number" step="0.01" min="0.01" name="monto" id="monto" required class="mt-1 block w-full border-gray-300 dark:border-border-dark bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="0.00">
                        </div>
                        <div>
                            <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Descripción</label>
                            <input type="text" name="descripcion" id="descripcion" required class="mt-1 block w-full border-gray-300 dark:border-border-dark bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Ej. Pago de servicios">
                        </div>
                        <div>
                            <label for="tipo_pago" class="block text-sm font-medium text-gray-700 dark:text-text-secondary-dark">Tipo de Pago</label>
                            <select name="tipo_pago" id="tipo_pago" required class="mt-1 block w-full border-gray-300 dark:border-border-dark bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="efectivo">Efectivo</option>
                                <option value="yape">Yape</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-slate-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t dark:border-border-dark">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Registrar Egreso
                    </button>
                    <button type="button" onclick="document.getElementById('modal-registrar-egreso').classList.add('hidden')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-border-dark shadow-sm px-4 py-2 bg-white dark:bg-slate-800 text-base font-medium text-gray-700 dark:text-text-primary-dark hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Cerrar Caja -->
<div id="modal-cerrar-caja" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-900/75 transition-opacity" onclick="document.getElementById('modal-cerrar-caja').classList.add('hidden')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="relative inline-block align-bottom bg-white dark:bg-surface-dark rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md w-full border dark:border-border-dark">
            <form action="{{ route('caja.cerrar') }}" method="POST">
                @csrf
                <div class="bg-white dark:bg-surface-dark px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-text-primary-dark" id="modal-title">¿Cerrar Caja?</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-text-secondary-dark">¿Estás seguro de que deseas cerrar la caja actual? El balance final registrado será de <strong class="text-gray-900 dark:text-text-primary-dark">S/ {{ number_format($resumen['balance_final'], 2) }}</strong>. Esta acción no se puede deshacer.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-slate-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t dark:border-border-dark">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Sí, Cerrar Caja
                    </button>
                    <button type="button" onclick="document.getElementById('modal-cerrar-caja').classList.add('hidden')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-border-dark shadow-sm px-4 py-2 bg-white dark:bg-slate-800 text-base font-medium text-gray-700 dark:text-text-primary-dark hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection
