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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Ventas</h1>
        <a href="{{ route('ventas.create') }}"
           aria-label="Nueva venta"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nueva venta
        </a>
    </div>

    {{-- Table or empty state --}}
    @if($ventas->isEmpty())
        <div class="text-center py-12 text-gray-500 dark:text-text-secondary-dark text-sm">No hay ventas registradas.</div>
    @else
        <div class="bg-surface rounded-lg border border-main overflow-x-auto transition-colors duration-300">
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Correlativo
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Fecha
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Usuario
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Subtotal
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Total
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Pago
                        </th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @foreach($ventas as $venta)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">
                                {{ $venta->correlativo }}
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                                {{ $venta->created_at->format('d/m/Y') }}
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                                {{ $venta->user->name }}
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                                S/ {{ number_format($venta->subtotal, 2) }}
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">
                                S/ {{ number_format($venta->total, 2) }}
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap">
                                 @php
                                    $colores = [
                                        'efectivo' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                                        'yape'     => 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400',
                                        'izipay'   => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
                                        'mixto'    => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400'
                                    ];
                                    $labels  = ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'izipay' => 'Izipay', 'mixto' => 'Mixto'];
                                    $metodo  = $venta->metodo_pago ?? 'efectivo';
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $colores[$metodo] ?? 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-secondary-dark' }}">
                                    {{ $labels[$metodo] ?? ucfirst($metodo) }}
                                </span>
                            </td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm flex items-center gap-2">
                                {{-- Ver detalle --}}
                                <a href="{{ route('ventas.show', $venta) }}"
                                   aria-label="Ver detalle de venta {{ $venta->correlativo }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-xs font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Ver detalle
                                </a>

                                {{-- Ticket --}}
                                <a href="{{ route('ventas.ticket', $venta) }}"
                                   aria-label="Ticket de venta {{ $venta->correlativo }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-xs font-medium rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Ticket
                                </a>

                                {{-- Eliminar --}}
                                <form method="POST" action="{{ route('ventas.destroy', $venta) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            aria-label="Eliminar venta {{ $venta->correlativo }}"
                                            onclick="return confirm('¿Estás seguro de que deseas eliminar esta venta?')"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-xs font-medium rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $ventas->links() }}
        </div>
    @endif

</div>
@endsection
