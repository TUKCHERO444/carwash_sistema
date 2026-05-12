@extends('layouts.app')

@section('content')

<style>
    @media print {
        nav,
        aside,
        #btn-imprimir,
        .no-print {
            display: none !important;
        }

        body {
            background: white !important;
            overflow: visible !important;
        }

        #ticket-content {
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
        }
    }
</style>

<div class="p-6">
    <div id="ticket-content" class="max-w-sm mx-auto bg-surface border border-main rounded-lg p-6 font-mono text-sm shadow-sm transition-colors duration-300">

        {{-- Header --}}
        <div class="text-center mb-4 border-b border-dashed border-main pb-4">
            <h1 class="text-lg font-bold text-primary uppercase tracking-wide">
                {{ config('app.name') }}
            </h1>
            <p class="text-secondary mt-1">Nota de Venta</p>
        </div>

        {{-- Sale info --}}
        <div class="mb-4 border-b border-dashed border-main pb-4 space-y-1">
            <div class="flex justify-between">
                <span class="text-secondary">Correlativo:</span>
                <span class="font-semibold text-primary">{{ $venta->correlativo }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-secondary">Fecha:</span>
                <span class="text-primary">{{ $venta->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-secondary">Atendido por:</span>
                <span class="text-primary">{{ $venta->user->name }}</span>
            </div>
        </div>

        {{-- Products table --}}
        <div class="mb-4 border-b border-dashed border-main pb-4">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-main">
                        <th class="text-left py-1 text-secondary font-medium">Nombre</th>
                        <th class="text-center py-1 text-secondary font-medium">Cant.</th>
                        <th class="text-right py-1 text-secondary font-medium">P.Unit.</th>
                        <th class="text-right py-1 text-secondary font-medium">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($venta->detalles as $detalle)
                        <tr class="border-b border-main/50">
                            <td class="py-1 text-primary pr-2">{{ $detalle->producto->nombre }}</td>
                            <td class="py-1 text-center text-secondary">{{ $detalle->cantidad }}</td>
                            <td class="py-1 text-right text-secondary">S/ {{ number_format($detalle->precio_unitario, 2) }}</td>
                            <td class="py-1 text-right text-secondary">S/ {{ number_format($detalle->subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Totals --}}
        <div class="mb-4 border-b border-dashed border-main pb-4 space-y-1">
            <div class="flex justify-between">
                <span class="text-secondary">Subtotal:</span>
                <span class="text-primary">S/ {{ number_format($venta->subtotal, 2) }}</span>
            </div>
            @if($venta->total != $venta->subtotal)
                <div class="flex justify-between">
                    <span class="text-secondary">Descuento:</span>
                    <span class="text-primary">- S/ {{ number_format($venta->subtotal - $venta->total, 2) }}</span>
                </div>
                <div class="flex justify-between font-bold text-primary border-t border-main pt-1 mt-1">
                    <span>Total:</span>
                    <span>S/ {{ number_format($venta->total, 2) }}</span>
                </div>
            @else
                <div class="flex justify-between font-bold text-primary border-t border-main pt-1 mt-1">
                    <span>Total:</span>
                    <span>S/ {{ number_format($venta->total, 2) }}</span>
                </div>
            @endif
            {{-- Método de pago --}}
            @php $metodosLabel = ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'izipay' => 'Izipay', 'mixto' => 'Mixto']; @endphp
            <div class="flex justify-between">
                <span class="text-secondary">Pago:</span>
                <span class="text-primary">{{ $metodosLabel[$venta->metodo_pago] ?? ucfirst($venta->metodo_pago) }}</span>
            </div>
            @if($venta->metodo_pago === 'mixto')
                @if($venta->monto_efectivo)
                    <div class="flex justify-between text-xs text-text-secondary-dark pl-2">
                        <span>Efectivo:</span>
                        <span>S/ {{ number_format($venta->monto_efectivo, 2) }}</span>
                    </div>
                @endif
                @if($venta->monto_yape)
                    <div class="flex justify-between text-xs text-text-secondary-dark pl-2">
                        <span>Yape:</span>
                        <span>S/ {{ number_format($venta->monto_yape, 2) }}</span>
                    </div>
                @endif
                @if($venta->monto_izipay)
                    <div class="flex justify-between text-xs text-text-secondary-dark pl-2">
                        <span>Izipay:</span>
                        <span>S/ {{ number_format($venta->monto_izipay, 2) }}</span>
                    </div>
                @endif
            @endif
        </div>

        {{-- Observation --}}
        @if($venta->observacion !== null)
            <div class="mb-4 border-b border-dashed border-main pb-4">
                <p class="text-text-secondary-dark text-xs">Observación:</p>
                <p class="text-primary text-xs mt-1">{{ $venta->observacion }}</p>
            </div>
        @endif

        {{-- Footer --}}
        <div class="text-center text-xs text-text-secondary-dark mb-4">
            <p>¡Gracias por su compra!</p>
        </div>

        {{-- Print button --}}
        <div class="text-center no-print">
            <button id="btn-imprimir"
                    onclick="window.print()"
                    class="px-6 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                Imprimir
            </button>
        </div>

    </div>

    {{-- Back link --}}
    <div class="max-w-sm mx-auto mt-4 no-print">
        <a href="{{ route('ventas.show', $venta) }}"
           class="inline-flex items-center gap-2 text-sm text-text-secondary-dark hover:text-text-primary-dark transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver al detalle
        </a>
    </div>
</div>
@endsection
