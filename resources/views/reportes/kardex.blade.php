@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Reporte de kardex</h1>
            <p class="mt-1 text-sm text-secondary">Rango aplicado: <strong>{{ $etiqueta }}</strong></p>
        </div>
        @include('reportes.partials.acciones')
    </div>

    {{-- Filtros --}}
    @push('filtros-reporte')
        <div>
            <label for="producto_id" class="label-main mb-1 inline-block">Producto</label>
            <select id="producto_id" name="producto_id" class="input-main px-3 py-2 border rounded-lg text-sm">
                <option value="">Todos</option>
                @foreach($productos as $producto)
                    <option value="{{ $producto->id }}" @selected(request('producto_id') == $producto->id)>{{ $producto->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="tipo" class="label-main mb-1 inline-block">Tipo</label>
            <select id="tipo" name="tipo" class="input-main px-3 py-2 border rounded-lg text-sm">
                <option value="">Todos</option>
                <option value="entrada" @selected(request('tipo') === 'entrada')>Entrada</option>
                <option value="salida" @selected(request('tipo') === 'salida')>Salida</option>
            </select>
        </div>
    @endpush
    @include('reportes.partials.filtros', ['ruta' => 'reportes.kardex'])

    {{-- Agregado por producto --}}
    <div class="bg-surface rounded-lg border border-main overflow-x-auto mb-6">
        <div class="px-6 py-4 border-b border-main">
            <h2 class="text-sm font-semibold text-primary">Movimientos agregados por producto</h2>
            <p class="text-xs text-secondary">Saldo neto = entradas − salidas del rango.</p>
        </div>
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Producto</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Entradas</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Salidas</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Saldo neto</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Stock actual</th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @forelse($agregado as $fila)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 whitespace-nowrap text-sm font-medium text-primary">{{ $fila['producto'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-green-600 dark:text-green-400 text-right">{{ $fila['entradas'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-red-600 dark:text-red-400 text-right">{{ $fila['salidas'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">{{ $fila['saldo_neto'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['stock_actual'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-secondary">Sin movimientos en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Detalle --}}
    <div class="bg-surface rounded-lg border border-main overflow-x-auto">
        <div class="px-6 py-4 border-b border-main">
            <h2 class="text-sm font-semibold text-primary">Detalle de movimientos</h2>
        </div>
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">ID</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Fecha</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Producto</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Tipo</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Fuente</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Cantidad</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Stock antes</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Stock después</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Usuario</th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @forelse($detalle as $movimiento)
                    @php
                        $tipoBadge = $movimiento->tipo === 'entrada'
                            ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                            : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400';
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $movimiento->id }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $movimiento->fecha_movimiento->format('d/m/Y H:i') }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $movimiento->producto?->nombre ?? '—' }}</td>
                        <td class="px-6 py-8 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $tipoBadge }}">{{ ucfirst($movimiento->tipo) }}</span>
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $movimiento->fuente }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $movimiento->cantidad }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $movimiento->stock_antes }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $movimiento->stock_despues }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $movimiento->usuario?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-6 py-8 text-center text-sm text-secondary">Sin movimientos en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($detalle->hasPages())
        <div class="mt-4"> {{ $detalle->links() }} </div>
    @endif

</div>
@vite('resources/js/reportes/print.js')
@endsection