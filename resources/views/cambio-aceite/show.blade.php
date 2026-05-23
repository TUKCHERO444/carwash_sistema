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
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">
            Cambio de Aceite — {{ $cambioAceite->fecha->format('d/m/Y') }}
        </h1>
        <div class="flex flex-wrap items-center gap-2">
            {{-- Volver al listado --}}
            <a href="{{ route('cambio-aceite.index') }}"
               aria-label="Volver al listado de cambios de aceite"
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Volver al listado
            </a>

            {{-- Editar --}}
            <a href="{{ route('cambio-aceite.edit', $cambioAceite) }}"
               aria-label="Editar cambio de aceite"
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Editar
            </a>

            {{-- Generar ticket --}}
            <a href="{{ route('cambio-aceite.ticket', $cambioAceite) }}"
               aria-label="Generar ticket del cambio de aceite"
               class="inline-flex items-center gap-2 px-4 py-2 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-sm font-medium rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Generar ticket
            </a>
        </div>
    </div>

    {{-- Main data card --}}
    <div class="bg-surface rounded-lg border border-main p-6 mb-6 transition-colors duration-300">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            {{-- Fecha --}}
            <div>
                <dt class="text-xs font-medium text-secondary uppercase tracking-wider">Fecha</dt>
                <dd class="mt-1 text-sm text-primary">{{ $cambioAceite->fecha->format('d/m/Y') }}</dd>
            </div>

            {{-- Cliente --}}
            <div>
                <dt class="text-xs font-medium text-secondary uppercase tracking-wider">Cliente</dt>
                <dd class="mt-1 text-sm text-primary">
                    {{ $cambioAceite->cliente->placa }}
                    @if($cambioAceite->cliente->nombre)
                        — {{ $cambioAceite->cliente->nombre }}
                    @endif
                </dd>
            </div>

            {{-- Trabajadores --}}
            <div>
                <dt class="text-xs font-medium text-secondary uppercase tracking-wider">Trabajadores</dt>
                <dd class="mt-1 text-sm text-primary">
                    @if($cambioAceite->trabajadores->isNotEmpty())
                        {{ $cambioAceite->trabajadores->pluck('nombre')->join(', ') }}
                    @else
                        <span class="text-gray-400 dark:text-text-secondary-dark/60">No asignado</span>
                    @endif
                </dd>
            </div>

            {{-- Registrado por --}}
            <div>
                <dt class="text-xs font-medium text-secondary uppercase tracking-wider">Registrado por</dt>
                <dd class="mt-1 text-sm text-primary">{{ $cambioAceite->user->name }}</dd>
            </div>

            {{-- Descripción (solo si existe) --}}
            @if($cambioAceite->descripcion)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-secondary uppercase tracking-wider">Descripción</dt>
                    <dd class="mt-1 text-sm text-primary">{{ $cambioAceite->descripcion }}</dd>
                </div>
            @endif

        </dl>
    </div>

    {{-- Products table --}}
    <div class="bg-surface rounded-lg border border-main overflow-hidden mb-6 transition-colors duration-300">
        <div class="px-6 py-4 border-b border-main bg-gray-50/50 dark:bg-slate-800/30">
            <h2 class="text-sm font-medium text-primary uppercase tracking-wider">Productos utilizados</h2>
        </div>
        @if($cambioAceite->productos->isEmpty())
            <div class="px-6 py-4">
                <p class="text-sm text-secondary">No hay productos registrados.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-main">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider">
                                Nombre del producto
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider">
                                Cantidad
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider">
                                Precio unitario
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider">
                                Subtotal
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-main bg-surface">
                        @foreach($cambioAceite->productos as $producto)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-primary">
                                    {{ $producto->nombre }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary">
                                    {{ $producto->pivot->cantidad }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary">
                                    S/ {{ number_format($producto->pivot->precio, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary">
                                    S/ {{ number_format($producto->pivot->total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Totals section --}}
    <div class="bg-surface rounded-lg border border-main p-6 mb-6 transition-colors duration-300">
        <div class="flex flex-col items-end gap-2 text-sm">
            @if($cambioAceite->total < $cambioAceite->precio)
                <div class="flex items-center gap-4">
                    <span class="text-secondary">Precio original:</span>
                    <span class="text-primary font-medium">S/ {{ number_format($cambioAceite->precio, 2) }}</span>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-secondary">Descuento aplicado:</span>
                    <span class="text-primary font-medium">S/ {{ number_format($cambioAceite->precio - $cambioAceite->total, 2) }}</span>
                </div>
                <div class="flex items-center gap-4 border-t border-main pt-2">
                    <span class="text-primary font-semibold">Total final:</span>
                    <span class="text-primary font-bold text-base">S/ {{ number_format($cambioAceite->total, 2) }}</span>
                </div>
            @else
                <div class="flex items-center gap-4 border-t border-main pt-2">
                    <span class="text-primary font-semibold">Total:</span>
                    <span class="text-primary font-bold text-base">S/ {{ number_format($cambioAceite->total, 2) }}</span>
                </div>
            @endif
            {{-- Método de pago --}}
            @php $metodosLabel = ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'izipay' => 'Izipay', 'mixto' => 'Mixto']; @endphp
            <div class="flex items-center gap-4">
                <span class="text-secondary">Método de pago:</span>
                <span class="text-primary font-medium">{{ $metodosLabel[$cambioAceite->metodo_pago] ?? ucfirst($cambioAceite->metodo_pago) }}</span>
            </div>
            @if($cambioAceite->metodo_pago === 'mixto')
                @if($cambioAceite->monto_efectivo)
                    <div class="flex items-center gap-4 pl-4">
                        <span class="text-secondary text-xs">Efectivo:</span>
                        <span class="text-primary text-xs">S/ {{ number_format($cambioAceite->monto_efectivo, 2) }}</span>
                    </div>
                @endif
                @if($cambioAceite->monto_yape)
                    <div class="flex items-center gap-4 pl-4">
                        <span class="text-secondary text-xs">Yape:</span>
                        <span class="text-primary text-xs">S/ {{ number_format($cambioAceite->monto_yape, 2) }}</span>
                    </div>
                @endif
                @if($cambioAceite->monto_izipay)
                    <div class="flex items-center gap-4 pl-4">
                        <span class="text-secondary text-xs">Izipay:</span>
                        <span class="text-primary text-xs">S/ {{ number_format($cambioAceite->monto_izipay, 2) }}</span>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Action buttons (bottom) --}}
    <div class="flex flex-wrap items-center gap-3">

        {{-- Volver al listado --}}
        <a href="{{ route('cambio-aceite.index') }}"
           aria-label="Volver al listado de cambios de aceite"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver al listado
        </a>

        {{-- Editar --}}
        <a href="{{ route('cambio-aceite.edit', $cambioAceite) }}"
           aria-label="Editar cambio de aceite"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Editar
        </a>

        {{-- Generar ticket --}}
        <a href="{{ route('cambio-aceite.ticket', $cambioAceite) }}"
           aria-label="Generar ticket del cambio de aceite"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-sm font-medium rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Generar ticket
        </a>

    </div>

</div>
@endsection
