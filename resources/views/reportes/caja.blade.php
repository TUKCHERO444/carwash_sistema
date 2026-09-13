@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Reporte de caja</h1>
            <p class="mt-1 text-sm text-secondary">Cajas cerradas con fecha de cierre en: <strong>{{ $etiqueta }}</strong></p>
        </div>
        @include('reportes.partials.acciones')
    </div>

    {{-- Filtros --}}
    @include('reportes.partials.filtros', ['ruta' => 'reportes.caja'])

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Cajas cerradas</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ number_format($kpis['cajas']) }}</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Ingresos</p>
            <p class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">S/ {{ number_format($kpis['ingresos'], 2) }}</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Egresos</p>
            <p class="mt-2 text-2xl font-bold text-red-600 dark:text-red-400">S/ {{ number_format($kpis['egresos'], 2) }}</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Saldo neto</p>
            <p class="mt-2 text-2xl font-bold text-primary">S/ {{ number_format($kpis['saldo_neto'], 2) }}</p>
        </div>
    </div>

    {{-- Detalle de cajas --}}
    <div class="bg-surface rounded-lg border border-main overflow-x-auto mb-6">
        <div class="px-6 py-4 border-b border-main">
            <h2 class="text-sm font-semibold text-primary">Balance por jornada</h2>
        </div>
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Caja</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Usuario</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Apertura</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Cierre</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Monto inicial</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Ingresos</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Egresos</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Balance final</th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @forelse($detalle as $caja)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">#{{ $caja['id'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $caja['usuario'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $caja['fecha_apertura'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $caja['fecha_cierre'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">S/ {{ number_format($caja['monto_inicial'], 2) }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">S/ {{ number_format($caja['total_ingresos'], 2) }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">S/ {{ number_format($caja['total_egresos'], 2) }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">S/ {{ number_format($caja['balance_final'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-6 py-8 text-center text-sm text-secondary">No hay cajas cerradas en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($detalle->hasPages())
        <div class="mt-4 mb-4"> {{ $detalle->links() }} </div>
    @endif

    {{-- Egresos --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-surface rounded-lg border border-main overflow-x-auto">
            <div class="px-6 py-4 border-b border-main">
                <h2 class="text-sm font-semibold text-primary">Egresos por descripción</h2>
            </div>
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Descripción</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Tipo de pago</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Cantidad</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @forelse($egresosPorDescripcion as $fila)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $fila['descripcion'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $fila['tipo_pago'] ?: '—' }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['cantidad'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">S/ {{ number_format($fila['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-sm text-secondary">Sin egresos en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-surface rounded-lg border border-main overflow-x-auto">
            <div class="px-6 py-4 border-b border-main">
                <h2 class="text-sm font-semibold text-primary">Detalle de egresos</h2>
            </div>
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Fecha</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Caja</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Descripción</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Usuario</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Monto</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @forelse($egresos as $egreso)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $egreso->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">#{{ $egreso->caja_id }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $egreso->descripcion }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $egreso->user?->name ?? '—' }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-red-600 dark:text-red-400 text-right">S/ {{ number_format($egreso->monto, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-secondary">Sin egresos en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@vite('resources/js/reportes/print.js')
@endsection