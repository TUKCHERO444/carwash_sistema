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
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Devoluciones</h1>
        <a href="{{ route('ventas.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver a ventas
        </a>
    </div>

    {{-- Table or empty state --}}
    @if($devoluciones->isEmpty())
        <div class="text-center py-12 text-gray-500 dark:text-text-secondary-dark text-sm">No hay devoluciones registradas.</div>
    @else
        <div class="bg-surface rounded-lg border border-main overflow-x-auto transition-colors duration-300">
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Correlativo</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Venta</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Tipo</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Motivo</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Total devuelto</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Usuario</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Fecha</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @foreach($devoluciones as $devolucion)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $devolucion->correlativo }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $devolucion->venta->correlativo }}</td>
                            <td class="px-6 py-8 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $devolucion->tipo === 'cambio' ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' }}">
                                    {{ $devolucion->tipo === 'cambio' ? 'Cambio' : 'Devolución' }}
                                </span>
                            </td>
                            <td class="px-6 py-8 text-sm text-secondary max-w-xs truncate" title="{{ $devolucion->motivo }}">{{ $devolucion->motivo }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">S/ {{ number_format($devolucion->total_devuelto, 2) }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $devolucion->user->name }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $devolucion->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm">
                                <a href="{{ route('devoluciones.show', $devolucion) }}"
                                   aria-label="Ver detalle de devolución {{ $devolucion->correlativo }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-xs font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                                    Ver detalle
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $devoluciones->links() }}
        </div>
    @endif

</div>
@endsection
