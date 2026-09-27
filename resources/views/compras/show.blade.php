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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">
            Compra {{ $compra->correlativo ?? '#' . $compra->id }}
        </h1>
        <div class="flex items-center gap-3">
            @can('acceso-compras')
            @if($compra->esBorrador())
            <form method="POST" action="{{ route('compras.recibir', $compra) }}" class="inline">
                @csrf
                <button type="submit"
                        aria-label="Recibir compra {{ $compra->correlativo ?? '#'.$compra->id }}"
                        data-confirm="¿Recibir esta compra? Se ingresarán las existencias, se registrará el egreso en caja y se actualizarán los costos. Esta acción no se puede deshacer."
                        class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Recibir
                </button>
            </form>
            <a href="{{ route('compras.edit', $compra) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-sm font-medium rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Editar
            </a>
            @endif
            @if($compra->esRecibida())
            <form method="POST" action="{{ route('compras.anular-recibida', $compra) }}" class="inline">
                @csrf
                <button type="submit"
                        aria-label="Anular compra {{ $compra->correlativo ?? '#'.$compra->id }}"
                        data-confirm="¿Anular esta compra recibida? Se generará salida compensatoria en Kardex y nota de crédito en caja. Esta acción es irreversible."
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Anular (con reversión)
                </button>
            </form>
            @endif
            @endcan
            <a href="{{ route('compras.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Volver
            </a>
        </div>
    </div>

    {{-- Tarjeta principal --}}
    <div class="bg-surface rounded-lg border border-main p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Correlativo</p>
                <p class="text-sm font-mono text-primary">{{ $compra->correlativo ?? '<span class="text-gray-400 italic">Sin asignar (borrador)</span>' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Proveedor</p>
                <p class="text-sm text-primary">{{ $compra->proveedor->razon_social }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Fecha</p>
                <p class="text-sm text-secondary">{{ $compra->fecha->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Estado</p>
                <p class="text-sm">
                    @php
                        $badge = match($compra->estado) {
                            'recibida' => ['Recibida', 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'],
                            'anulada' => ['Anulada', 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'],
                            default => ['Borrador', 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'],
                        };
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge[1] }}">
                        {{ $badge[0] }}
                    </span>
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Tipo Documento</p>
                <p class="text-sm text-secondary">{{ $compra->tipo_documento ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Nº Documento</p>
                <p class="text-sm text-secondary">{{ $compra->numero_documento ?? '—' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 pt-4 border-t border-main">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Subtotal</p>
                <p class="text-sm font-mono text-secondary">S/ {{ number_format($compra->subtotal, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Total</p>
                <p class="text-sm font-mono text-primary font-semibold">S/ {{ number_format($compra->total, 2) }}</p>
            </div>
        </div>

        @if($compra->observaciones)
        <div class="mb-4 pt-4 border-t border-main">
            <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Observaciones</p>
            <p class="text-sm text-secondary whitespace-pre-wrap">{{ $compra->observaciones }}</p>
        </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-main">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Creado por</p>
                <p class="text-sm text-secondary">{{ $compra->user->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Creado</p>
                <p class="text-sm text-secondary">{{ $compra->created_at->format('d/m/Y H:i') }}</p>
            </div>
            @if($compra->fecha_recepcion)
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Recibido</p>
                <p class="text-sm text-secondary">{{ $compra->fecha_recepcion->format('d/m/Y H:i') }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Detalle de líneas --}}
    <div class="bg-surface rounded-lg border border-main overflow-x-auto">
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Producto
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Cantidad
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Costo Unit.
                    </th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                        Subtotal
                    </th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @foreach($compra->detalles as $detalle)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 text-sm text-primary font-medium">
                            {{ $detalle->producto->nombre ?? 'Producto eliminado' }}
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary font-mono">
                            {{ $detalle->cantidad }}
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary font-mono">
                            S/ {{ number_format($detalle->costo_unitario, 2) }}
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary font-mono">
                            S/ {{ number_format($detalle->subtotal, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totales --}}
    <div class="mt-4 max-w-sm text-right">
        <div class="text-sm text-secondary">Subtotal: <span class="font-mono">S/ {{ number_format($compra->subtotal, 2) }}</span></div>
        <div class="text-lg font-semibold text-primary font-mono">Total: S/ {{ number_format($compra->total, 2) }}</div>
    </div>

    {{-- Modal caja cerrada --}}
    @if(session('error_caja'))
    <x-modal id="modal-caja-cerrada" title="Caja Cerrada" open>
        <div class="px-6 pb-4 sm:pb-6">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 sm:h-10 sm:w-10">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <p class="text-sm text-secondary">No puedes recibir la compra porque no hay ninguna sesión de caja activa. Debes iniciar la caja primero.</p>
            </div>
        </div>
        <x-slot:footer>
            <a href="{{ route('caja.index') }}" class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:text-sm">
                Ir a Caja
            </a>
            <a href="{{ route('compras.index') }}" class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-md border border-main shadow-sm px-4 py-2 bg-surface text-base font-medium text-secondary hover:bg-slate-50 dark:hover:bg-slate-700 sm:text-sm">
                Cancelar
            </a>
        </x-slot:footer>
    </x-modal>
    @endif

</div>
@endsection