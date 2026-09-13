@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Reporte de personal</h1>
            <p class="mt-1 text-sm text-secondary">Mes consultado: <strong>{{ $resumen['mes'] }}</strong> ({{ $resumen['desde'] }} al {{ $resumen['hasta'] }})</p>
        </div>
        @include('reportes.partials.acciones')
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('reportes.personal') }}"
          class="print:hidden flex flex-wrap items-end gap-3 bg-surface rounded-lg border border-main p-4 mb-6">
        <div>
            <label for="mes" class="label-main mb-1 inline-block">Mes</label>
            <input type="month" id="mes" name="mes" value="{{ $mes }}"
                   class="input-main px-3 py-2 border rounded-lg text-sm">
        </div>
        <div>
            <label for="trabajador_id" class="label-main mb-1 inline-block">Trabajador</label>
            <select id="trabajador_id" name="trabajador_id" class="input-main px-3 py-2 border rounded-lg text-sm">
                <option value="">Todos</option>
                @foreach($trabajadores as $trabajador)
                    <option value="{{ $trabajador['id'] }}" @selected(request('trabajador_id') == $trabajador['id'])>{{ $trabajador['nombre'] }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            Filtrar
        </button>
        @if($errors->any())
            <p class="w-full text-xs text-red-600">{{ $errors->first() }}</p>
        @endif
    </form>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Trabajadores con marca</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ count($resumen['trabajadores']) }}</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Días con marcación</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ $resumen['total_dias_con_marca'] }}</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Total a pagar (jornales)</p>
            <p class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">S/ {{ number_format($resumen['total_pago_general'], 2) }}</p>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="bg-surface rounded-lg border border-main overflow-x-auto">
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Trabajador</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Jornal</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Asistencias</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">% asistencia</th>
                    <th scope="col" class="px-6 py-6 text-center text-xs font-medium text-secondary uppercase tracking-wider">Hora promedio</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Total a pagar</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Estado</th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @forelse($resumen['trabajadores'] as $fila)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 whitespace-nowrap text-sm font-medium text-primary">{{ $fila['nombre'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">
                            @if($fila['sin_jornal'])
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-secondary-dark">Sin jornal</span>
                            @else
                                S/ {{ number_format($fila['pago_diario'], 2) }}
                            @endif
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['asistencias'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ number_format($fila['porcentaje_asistencia'], 2) }}%</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-center">{{ $fila['hora_promedio'] ?: '—' }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">
                            @if($fila['sin_jornal'])
                                <span class="text-secondary">S/ 0.00</span>
                            @else
                                S/ {{ number_format($fila['total_pago'], 2) }}
                            @endif
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap">
                            @if($fila['activo'])
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">Activo</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">Inactivo</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-8 text-center text-sm text-secondary">No hay marcaciones en el mes seleccionado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@vite('resources/js/reportes/print.js')
@endsection