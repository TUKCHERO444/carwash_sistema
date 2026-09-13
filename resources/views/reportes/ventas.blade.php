@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Reporte de ventas</h1>
            <p class="mt-1 text-sm text-secondary">Rango aplicado: <strong>{{ $etiqueta }}</strong></p>
        </div>
        @include('reportes.partials.acciones')
    </div>

    {{-- Filtros --}}
    @push('filtros-reporte')
        <div>
            <label for="user_id" class="label-main mb-1 inline-block">Usuario</label>
            <select id="user_id" name="user_id" class="input-main px-3 py-2 border rounded-lg text-sm">
                <option value="">Todos</option>
                @foreach($usuarios as $usuario)
                    <option value="{{ $usuario->id }}" @selected(request('user_id') == $usuario->id)>{{ $usuario->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="metodo_pago" class="label-main mb-1 inline-block">Método de pago</label>
            <select id="metodo_pago" name="metodo_pago" class="input-main px-3 py-2 border rounded-lg text-sm">
                <option value="">Todos</option>
                @foreach(['efectivo' => 'Efectivo', 'yape' => 'Yape', 'izipay' => 'Izipay', 'mixto' => 'Mixto'] as $valor => $label)
                    <option value="{{ $valor }}" @selected(request('metodo_pago') === $valor)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="correlativo" class="label-main mb-1 inline-block">Correlativo</label>
            <input type="text" id="correlativo" name="correlativo" value="{{ request('correlativo') }}"
                   placeholder="V0001-000001"
                   class="input-main px-3 py-2 border rounded-lg text-sm">
        </div>
    @endpush
    @include('reportes.partials.filtros', ['ruta' => 'reportes.ventas'])

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Ingreso total</p>
            <p class="mt-2 text-2xl font-bold text-primary">S/ {{ number_format($kpis['total'], 2) }}</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Operaciones</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ number_format($kpis['operaciones']) }}</p>
        </div>
        <div class="bg-surface rounded-lg border border-main p-5">
            <p class="text-xs font-medium text-secondary uppercase tracking-wider">Ticket promedio</p>
            <p class="mt-2 text-2xl font-bold text-primary">S/ {{ number_format($kpis['ticket_promedio'], 2) }}</p>
        </div>
    </div>

    {{-- Agregados --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-surface rounded-lg border border-main overflow-x-auto">
            <div class="px-6 py-4 border-b border-main">
                <h2 class="text-sm font-semibold text-primary">Por usuario</h2>
            </div>
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Usuario</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Ventas</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @forelse($porUsuario as $fila)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $fila['usuario'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['operaciones'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">S/ {{ number_format($fila['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-secondary">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-surface rounded-lg border border-main overflow-x-auto">
            <div class="px-6 py-4 border-b border-main">
                <h2 class="text-sm font-semibold text-primary">Por método de pago</h2>
            </div>
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Método</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Operaciones</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @forelse($porMetodo as $fila)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ ucfirst($fila['metodo']) }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['operaciones'] }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">S/ {{ number_format($fila['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-secondary">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Detalle --}}
    <div class="bg-surface rounded-lg border border-main overflow-x-auto">
        <div class="px-6 py-4 border-b border-main">
            <h2 class="text-sm font-semibold text-primary">Detalle</h2>
        </div>
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Correlativo</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Fecha</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Usuario</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Método</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Total</th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @forelse($detalle as $venta)
                    @php
                        $coloresMetodo = [
                            'efectivo' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                            'yape' => 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400',
                            'izipay' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
                            'mixto' => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400',
                        ];
                        $labelsMetodo = ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'izipay' => 'Izipay', 'mixto' => 'Mixto'];
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $venta->correlativo }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $venta->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $venta->user->name }}</td>
                        <td class="px-6 py-8 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $coloresMetodo[$venta->metodo_pago] ?? 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-text-secondary-dark' }}">
                                {{ $labelsMetodo[$venta->metodo_pago] ?? ucfirst($venta->metodo_pago) }}
                            </span>
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">S/ {{ number_format($venta->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-secondary">No hay ventas en el período.</td></tr>
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