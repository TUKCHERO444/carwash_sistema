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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Detalle del Ingreso</h1>
        <a href="{{ route('ingresos.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver al listado
        </a>
    </div>

    {{-- Main data card --}}
    <div class="bg-surface rounded-lg border border-main p-6 mb-6">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            {{-- Fecha --}}
            <div>
                <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Fecha</dt>
                <dd class="mt-1 text-sm text-primary">{{ $ingreso->fecha->format('d/m/Y') }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Cliente</dt>
                <dd class="mt-1 text-sm text-primary">
                    {{ $ingreso->cliente->placa }}
                    @if($ingreso->cliente->nombre)
                        — {{ $ingreso->cliente->nombre }}
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Vehículo</dt>
                <dd class="mt-1 text-sm text-primary">
                    {{ $ingreso->vehiculo->nombre }}
                    <span class="text-text-secondary-dark">(S/ {{ number_format($ingreso->vehiculo->precio, 2) }})</span>
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Registrado por</dt>
                <dd class="mt-1 text-sm text-primary">{{ $ingreso->user->name }}</dd>
            </div>

        </dl>

        {{-- Foto del vehículo --}}
        @if($ingreso->foto)
            <div class="mt-4 pt-4 border-t border-main">
                <p class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider mb-2">Foto del vehículo</p>
                <img
                    src="{{ Storage::url($ingreso->foto) }}"
                    alt="Foto del vehículo"
                    class="rounded-lg max-h-64 object-cover border border-main"
                >
            </div>
        @endif
    </div>

    {{-- Trabajadores asignados --}}
    <div class="bg-surface rounded-lg border border-main p-6 mb-6">
        <h2 class="text-sm font-medium text-secondary uppercase tracking-wider mb-3">Trabajadores asignados</h2>
        @if($ingreso->trabajadores->isEmpty())
            <p class="text-sm text-secondary">No hay trabajadores asignados.</p>
        @else
            <ul class="space-y-1">
                @foreach($ingreso->trabajadores as $trabajador)
                    <li class="flex items-center gap-2 text-sm text-secondary">
                        <svg class="w-4 h-4 text-text-secondary-dark flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        {{ $trabajador->nombre }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Servicios --}}
    <div class="bg-surface rounded-lg border border-main overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-main">
            <h2 class="text-sm font-medium text-secondary uppercase tracking-wider">Servicios</h2>
        </div>
        @if($ingreso->servicios->isEmpty())
            <div class="px-6 py-4">
                <p class="text-sm text-secondary">No hay servicios asignados.</p>
            </div>
        @else
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Nombre
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Precio
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @foreach($ingreso->servicios as $servicio)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-primary">
                                {{ $servicio->nombre }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary">
                                S/ {{ number_format($servicio->precio, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Totals section --}}
    <div class="bg-surface rounded-lg border border-main p-6 mb-6">
        <div class="flex flex-col items-end gap-2 text-sm">
            @if($ingreso->total < $ingreso->precio)
                <div class="flex items-center gap-4">
                    <span class="text-secondary">Precio original:</span>
                    <span class="text-primary font-medium">S/ {{ number_format($ingreso->precio, 2) }}</span>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-secondary">Descuento aplicado:</span>
                    <span class="text-primary font-medium">S/ {{ number_format($ingreso->precio - $ingreso->total, 2) }}</span>
                </div>
                <div class="flex items-center gap-4 border-t border-main pt-2">
                    <span class="text-secondary font-semibold">Total final:</span>
                    <span class="text-primary font-bold text-base">S/ {{ number_format($ingreso->total, 2) }}</span>
                </div>
            @else
                <div class="flex items-center gap-4 border-t border-main pt-2">
                    <span class="text-secondary font-semibold">Total:</span>
                    <span class="text-primary font-bold text-base">S/ {{ number_format($ingreso->total, 2) }}</span>
                </div>
            @endif
            {{-- Método de pago --}}
            @php $metodosLabel = ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'izipay' => 'Izipay', 'mixto' => 'Mixto']; @endphp
            <div class="flex items-center gap-4">
                <span class="text-secondary">Método de pago:</span>
                <span class="text-primary font-medium">{{ $metodosLabel[$ingreso->metodo_pago] ?? ucfirst($ingreso->metodo_pago) }}</span>
            </div>
            @if($ingreso->metodo_pago === 'mixto')
                @if($ingreso->monto_efectivo)
                    <div class="flex items-center gap-4 pl-4">
                        <span class="text-text-secondary-dark text-xs">Efectivo:</span>
                        <span class="text-secondary text-xs">S/ {{ number_format($ingreso->monto_efectivo, 2) }}</span>
                    </div>
                @endif
                @if($ingreso->monto_yape)
                    <div class="flex items-center gap-4 pl-4">
                        <span class="text-text-secondary-dark text-xs">Yape:</span>
                        <span class="text-secondary text-xs">S/ {{ number_format($ingreso->monto_yape, 2) }}</span>
                    </div>
                @endif
                @if($ingreso->monto_izipay)
                    <div class="flex items-center gap-4 pl-4">
                        <span class="text-text-secondary-dark text-xs">Izipay:</span>
                        <span class="text-secondary text-xs">S/ {{ number_format($ingreso->monto_izipay, 2) }}</span>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Action buttons --}}
    <div class="flex flex-wrap items-center gap-3">

        {{-- Generar ticket --}}
        <a href="{{ route('ingresos.ticket', $ingreso) }}"
           aria-label="Generar ticket del ingreso"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-sm font-medium rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Generar ticket
        </a>

        {{-- Editar --}}
        <a href="{{ route('ingresos.edit', $ingreso) }}"
           aria-label="Editar ingreso"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Editar
        </a>

        {{-- Volver al listado --}}
        <a href="{{ route('ingresos.index') }}"
           aria-label="Volver al listado de ingresos"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver al listado
        </a>

    </div>

</div>
@endsection
