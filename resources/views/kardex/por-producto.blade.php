@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('kardex.index') }}" class="text-sm text-secondary hover:text-blue-600 dark:hover:text-blue-400">← Kardex</a>
            </div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark mt-1">Kardex — {{ $producto->nombre }}</h1>
            <p class="text-sm text-secondary mt-1">Stock actual: <span class="font-medium text-primary">{{ $producto->stock }}</span></p>
        </div>
    </div>

    {{-- Table or empty state --}}
    @if($movimientos->isEmpty())
        <div class="text-center py-12 text-gray-500 text-sm">
            No hay movimientos para este producto.
        </div>
    @else
        <div class="bg-surface rounded-lg border border-main overflow-x-auto transition-colors duration-300">
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Fecha / hora</th>
                        <th scope="col" class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Tipo</th>
                        <th scope="col" class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Cantidad</th>
                        <th scope="col" class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Stock antes</th>
                        <th scope="col" class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Stock después</th>
                        <th scope="col" class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Fuente</th>
                        <th scope="col" class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Origen</th>
                        <th scope="col" class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Usuario</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @foreach($movimientos as $mov)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-secondary">
                                {{ $mov->fecha_movimiento?->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                @if($mov->tipo === 'entrada')
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400 text-xs font-medium">Entrada</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-xs font-medium">Salida</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-secondary">{{ $mov->cantidad }}</td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-secondary">{{ $mov->stock_antes }}</td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-secondary">{{ $mov->stock_despues }}</td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-secondary">{{ $mov->fuente }}</td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-mono text-secondary">{{ $mov->origen_id }}</td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-secondary">{{ $mov->usuario?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $movimientos->links() }}
        </div>
    @endif

</div>
@endsection
