@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark">Kardex de movimientos</h1>
            <p class="text-sm text-secondary mt-1">Control de movimientos de productos y sus existencias.</p>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('kardex.index') }}" class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <div>
            <label for="producto_id" class="block text-xs font-medium text-secondary mb-1">Producto</label>
            <select name="producto_id" id="producto_id"
                    class="w-full rounded-lg border border-main bg-surface text-sm px-3 py-2">
                <option value="">Todos</option>
                @foreach($productos as $producto)
                    <option value="{{ $producto->id }}" @selected((string) ($filtros['producto_id'] ?? '') === (string) $producto->id)>
                        {{ $producto->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="fuente" class="block text-xs font-medium text-secondary mb-1">Fuente</label>
            <select name="fuente" id="fuente"
                    class="w-full rounded-lg border border-main bg-surface text-sm px-3 py-2">
                <option value="">Todas</option>
                <option value="venta" @selected(($filtros['fuente'] ?? '') === 'venta')>Venta</option>
                <option value="cambio_aceite" @selected(($filtros['fuente'] ?? '') === 'cambio_aceite')>Cambio de aceite</option>
                <option value="inventario" @selected(($filtros['fuente'] ?? '') === 'inventario')>Inventario</option>
            </select>
        </div>

        <div>
            <label for="origen_id" class="block text-xs font-medium text-secondary mb-1">Origen (correlativo/placa)</label>
            <input type="text" name="origen_id" id="origen_id" value="{{ $filtros['origen_id'] ?? '' }}"
                   placeholder="VTA-0001, INV-0001 o placa"
                   class="w-full rounded-lg border border-main bg-surface text-sm px-3 py-2">
        </div>

        <div>
            <label for="desde" class="block text-xs font-medium text-secondary mb-1">Desde</label>
            <input type="date" name="desde" id="desde" value="{{ $filtros['desde'] ?? '' }}"
                   class="w-full rounded-lg border border-main bg-surface text-sm px-3 py-2">
        </div>

        <div>
            <label for="hasta" class="block text-xs font-medium text-secondary mb-1">Hasta</label>
            <input type="date" name="hasta" id="hasta" value="{{ $filtros['hasta'] ?? '' }}"
                   class="w-full rounded-lg border border-main bg-surface text-sm px-3 py-2">
        </div>

        <div class="sm:col-span-2 lg:col-span-5 flex items-end gap-2">
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                Filtrar
            </button>
            <a href="{{ route('kardex.index') }}"
               class="px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-primary-dark text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                Limpiar
            </a>
        </div>
    </form>

    {{-- Table or empty state --}}
    @if($movimientos->isEmpty())
        <div class="text-center py-12 text-gray-500 text-sm">
            No hay movimientos registrados.
        </div>
    @else
        <div class="bg-surface rounded-lg border border-main overflow-x-auto transition-colors duration-300">
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Fecha / hora</th>
                        <th scope="col" class="px-4 py-4 text-left text-xs font-medium text-gray-500 dark:text-text-secondary-dark uppercase tracking-wider">Producto</th>
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
                            <td class="px-4 py-4 text-sm text-primary font-medium">
                                <a href="{{ route('kardex.porProducto', $mov->producto) }}" class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $mov->producto?->nombre }}
                                </a>
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
