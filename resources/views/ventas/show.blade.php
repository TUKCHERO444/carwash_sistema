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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">{{ $venta->correlativo }}</h1>
        <a href="{{ route('ventas.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver al listado
        </a>
    </div>

    {{-- Data card --}}
    <div class="bg-surface rounded-lg border border-main p-6 mb-6">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Correlativo</dt>
                <dd class="mt-1 text-sm text-primary">{{ $venta->correlativo }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Fecha</dt>
                <dd class="mt-1 text-sm text-primary">{{ $venta->created_at->format('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Usuario</dt>
                <dd class="mt-1 text-sm text-primary">{{ $venta->user->name }}</dd>
            </div>
            @if($venta->observacion !== null)
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Observación</dt>
                <dd class="mt-1 text-sm text-primary">{{ $venta->observacion }}</dd>
            </div>
            @endif
        </dl>
    </div>

    {{-- Products table --}}
    <div class="bg-surface rounded-lg border border-main overflow-x-auto mb-6">
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Nombre del producto
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Cantidad
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Precio unitario
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Subtotal
                    </th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @foreach($venta->detalles as $detalle)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">
                            {{ $detalle->producto->nombre }}
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                            {{ $detalle->cantidad }}
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                            S/ {{ number_format($detalle->precio_unitario, 2) }}
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                            S/ {{ number_format($detalle->subtotal, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totals section --}}
    <div class="bg-surface rounded-lg border border-main p-6 mb-6">
        <div class="flex flex-col items-end gap-2 text-sm">
            <div class="flex items-center gap-4">
                <span class="text-secondary">Subtotal:</span>
                <span class="text-primary font-medium">S/ {{ number_format($venta->subtotal, 2) }}</span>
            </div>
            @if($venta->total != $venta->subtotal)
                <div class="flex items-center gap-4">
                    <span class="text-secondary">Descuento aplicado:</span>
                    <span class="text-primary font-medium">S/ {{ number_format($venta->subtotal - $venta->total, 2) }}</span>
                </div>
                <div class="flex items-center gap-4 border-t border-main pt-2">
                    <span class="text-primary font-semibold">Total:</span>
                    <span class="text-primary font-bold text-base">S/ {{ number_format($venta->total, 2) }}</span>
                </div>
            @else
                <div class="flex items-center gap-4 border-t border-main pt-2">
                    <span class="text-primary font-semibold">Total:</span>
                    <span class="text-primary font-bold text-base">S/ {{ number_format($venta->total, 2) }}</span>
                </div>
            @endif
            {{-- Método de pago --}}
            @php $metodosLabel = ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'izipay' => 'Izipay', 'mixto' => 'Mixto']; @endphp
            <div class="flex items-center gap-4">
                <span class="text-secondary">Método de pago:</span>
                <span class="text-primary font-medium">{{ $metodosLabel[$venta->metodo_pago] ?? ucfirst($venta->metodo_pago) }}</span>
            </div>
            @if($venta->metodo_pago === 'mixto')
                @if($venta->monto_efectivo)
                    <div class="flex items-center gap-4 pl-4">
                        <span class="text-text-secondary-dark text-xs">Efectivo:</span>
                        <span class="text-secondary text-xs">S/ {{ number_format($venta->monto_efectivo, 2) }}</span>
                    </div>
                @endif
                @if($venta->monto_yape)
                    <div class="flex items-center gap-4 pl-4">
                        <span class="text-text-secondary-dark text-xs">Yape:</span>
                        <span class="text-secondary text-xs">S/ {{ number_format($venta->monto_yape, 2) }}</span>
                    </div>
                @endif
                @if($venta->monto_izipay)
                    <div class="flex items-center gap-4 pl-4">
                        <span class="text-text-secondary-dark text-xs">Izipay:</span>
                        <span class="text-secondary text-xs">S/ {{ number_format($venta->monto_izipay, 2) }}</span>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('ventas.ticket', $venta) }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Generar ticket
        </a>
    </div>

</div>
@endsection
