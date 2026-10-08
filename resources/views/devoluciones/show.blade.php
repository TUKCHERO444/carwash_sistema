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
            {{ $devolucion->correlativo }}
            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $devolucion->tipo === 'cambio' ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' }}">
                {{ $devolucion->tipo === 'cambio' ? 'Cambio' : 'Devolución' }}
            </span>
        </h1>
        <a href="{{ route('devoluciones.index') }}"
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
                <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Venta original</dt>
                <dd class="mt-1 text-sm text-primary">
                    <a href="{{ route('ventas.show', $devolucion->venta) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $devolucion->venta->correlativo }}</a>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Fecha</dt>
                <dd class="mt-1 text-sm text-primary">{{ $devolucion->created_at->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Usuario</dt>
                <dd class="mt-1 text-sm text-primary">{{ $devolucion->user->name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Total devuelto</dt>
                <dd class="mt-1 text-sm text-primary font-semibold">S/ {{ number_format($devolucion->total_devuelto, 2) }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Motivo</dt>
                <dd class="mt-1 text-sm text-primary">{{ $devolucion->motivo }}</dd>
            </div>
            @if($devolucion->ventaNueva)
                <div>
                    <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Venta sustituta (cambio)</dt>
                    <dd class="mt-1 text-sm text-primary">
                        <a href="{{ route('ventas.show', $devolucion->ventaNueva) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $devolucion->ventaNueva->correlativo }}</a>
                        <span class="text-secondary">· S/ {{ number_format($devolucion->ventaNueva->total, 2) }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-text-secondary-dark uppercase tracking-wider">Diferencia a favor del cliente</dt>
                    <dd class="mt-1 text-sm text-primary">S/ {{ number_format($devolucion->ventaNueva->total - $devolucion->total_devuelto, 2) }}</dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Products table --}}
    <div class="bg-surface rounded-lg border border-main overflow-x-auto mb-6">
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto devuelto</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio unitario</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @foreach($devolucion->detalles as $detalle)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $detalle->producto->nombre }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $detalle->cantidad }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">S/ {{ number_format($detalle->precio_unitario, 2) }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">S/ {{ number_format($detalle->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection
