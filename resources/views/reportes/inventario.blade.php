@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Reporte de inventario</h1>
            <p class="mt-1 text-sm text-secondary">Rango aplicado: <strong>{{ $etiqueta }}</strong></p>
        </div>
        @include('reportes.partials.acciones')
    </div>

    {{-- Filtros --}}
    @push('filtros-reporte')
        <div>
            <label for="categoria_id" class="label-main mb-1 inline-block">Categoría</label>
            <select id="categoria_id" name="categoria_id" class="input-main px-3 py-2 border rounded-lg text-sm">
                <option value="">Todas</option>
                @foreach($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected(request('categoria_id') == $categoria->id)>{{ $categoria->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="marca_id" class="label-main mb-1 inline-block">Marca</label>
            <select id="marca_id" name="marca_id" class="input-main px-3 py-2 border rounded-lg text-sm">
                <option value="">Todas</option>
                @foreach($marcas as $marca)
                    <option value="{{ $marca->id }}" @selected(request('marca_id') == $marca->id)>{{ $marca->nombre }}</option>
                @endforeach
            </select>
        </div>
    @endpush
    @include('reportes.partials.filtros', ['ruta' => 'reportes.inventario'])

    {{-- Top --}}
    <div class="bg-surface rounded-lg border border-main overflow-x-auto mb-6">
        <div class="px-6 py-4 border-b border-main">
            <h2 class="text-sm font-semibold text-primary">Top de productos vendidos (por cantidad)</h2>
            <p class="text-xs text-secondary">Cantidad e ingresos combinando ventas y cambios de aceite confirmados del rango.</p>
        </div>
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Producto</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Categoría</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Marca</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Cantidad</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Ingreso</th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @forelse($top as $fila)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $fila['nombre'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $fila['categoria'] ?: '—' }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $fila['marca'] ?: '—' }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['cantidad'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">S/ {{ number_format($fila['ingreso'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-secondary">Sin datos en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Stock actual + resúmenes --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-surface rounded-lg border border-main overflow-x-auto">
            <div class="px-6 py-4 border-b border-main">
                <h2 class="text-sm font-semibold text-primary">Stock actual valorizado</h2>
                <p class="text-xs text-secondary">Stock × precio de compra (independiente del rango).</p>
            </div>
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Producto</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Stock</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Valorizado</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @forelse($stockActual as $producto)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $producto->nombre }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $producto->stock }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">
                                S/ {{ number_format($producto->stock * $producto->precio_compra, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-secondary">Sin productos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-4">
            <div class="bg-surface rounded-lg border border-main overflow-x-auto">
                <div class="px-6 py-4 border-b border-main">
                    <h2 class="text-sm font-semibold text-primary">Resumen por categoría</h2>
                </div>
                <table class="min-w-full divide-y divide-main">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Categoría</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Productos</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Stock</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Ingresos</th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface divide-y divide-main">
                        @forelse($resumenCategorias as $fila)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $fila['nombre'] }}</td>
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['productos'] }}</td>
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['stock_total'] }}</td>
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">S/ {{ number_format($fila['ingresos'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-sm text-secondary">Sin datos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-surface rounded-lg border border-main overflow-x-auto">
                <div class="px-6 py-4 border-b border-main">
                    <h2 class="text-sm font-semibold text-primary">Resumen por marca</h2>
                </div>
                <table class="min-w-full divide-y divide-main">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Marca</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Productos</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Stock</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Ingresos</th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface divide-y divide-main">
                        @forelse($resumenMarcas as $fila)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $fila['nombre'] }}</td>
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['productos'] }}</td>
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['stock_total'] }}</td>
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">S/ {{ number_format($fila['ingresos'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-sm text-secondary">Sin datos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@vite('resources/js/reportes/print.js')
@endsection