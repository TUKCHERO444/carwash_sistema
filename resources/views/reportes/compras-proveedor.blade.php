@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Compras por proveedor</h1>
            <p class="mt-1 text-sm text-secondary">Rango aplicado: <strong>{{ $etiqueta }}</strong></p>
        </div>
        @include('reportes.partials.acciones')
    </div>

    {{-- Filtros --}}
    @push('filtros-reporte')
        <div>
            <label for="proveedor_id" class="label-main mb-1 inline-block">Proveedor</label>
            <select id="proveedor_id" name="proveedor_id" class="input-main px-3 py-2 border rounded-lg text-sm">
                <option value="">Resumen general</option>
                @foreach($proveedores as $item)
                    <option value="{{ $item->id }}" @selected(request('proveedor_id') == $item->id)>{{ $item->razon_social }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="producto_id" class="label-main mb-1 inline-block">Producto</label>
            <select id="producto_id" name="producto_id" class="input-main px-3 py-2 border rounded-lg text-sm">
                <option value="">Todos</option>
                @foreach($productos as $producto)
                    <option value="{{ $producto->id }}" @selected(request('producto_id') == $producto->id)>{{ $producto->nombre }}</option>
                @endforeach
            </select>
        </div>
    @endpush
    @include('reportes.partials.filtros', ['ruta' => 'reportes.comprasProveedor'])

    {{-- Resumen general --}}
    <div class="bg-surface rounded-lg border border-main overflow-x-auto mb-6">
        <div class="px-6 py-4 border-b border-main">
            <h2 class="text-sm font-semibold text-primary">Comparativo de proveedores (compras recibidas)</h2>
        </div>
        <table class="min-w-full divide-y divide-main">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Proveedor</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">RUC</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Compras</th>
                    <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Total</th>
                    <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Última compra</th>
                </tr>
            </thead>
            <tbody class="bg-surface divide-y divide-main">
                @forelse($resumen as $fila)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">
                            <a href="{{ route('reportes.comprasProveedor', array_merge(request()->except('page'), ['proveedor_id' => $fila['proveedor_id']])) }}"
                               class="text-blue-600 dark:text-blue-400 hover:underline">{{ $fila['razon_social'] }}</a>
                        </td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $fila['ruc'] ?: '—' }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['compras'] }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">S/ {{ number_format($fila['total'], 2) }}</td>
                        <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $fila['ultima_compra'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-secondary">Sin compras recibidas en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($proveedor)
        {{-- Ficha + KPIs --}}
        <div class="bg-surface rounded-lg border border-main p-5 mb-6">
            <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
                <div>
                    <p class="text-xs font-medium text-secondary uppercase tracking-wider">Proveedor</p>
                    <p class="mt-1 text-xl font-bold text-primary">{{ $proveedor->razon_social }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-secondary uppercase tracking-wider">RUC</p>
                    <p class="mt-1 text-sm text-primary">{{ $proveedor->ruc ?: '—' }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-surface rounded-lg border border-main p-5">
                <p class="text-xs font-medium text-secondary uppercase tracking-wider">Total comprado</p>
                <p class="mt-2 text-2xl font-bold text-primary">S/ {{ number_format($kpis['total'], 2) }}</p>
                <p class="mt-1 text-xs text-secondary">{{ $kpis['compras'] }} compras recibidas</p>
            </div>
            <div class="bg-surface rounded-lg border border-main p-5">
                <p class="text-xs font-medium text-secondary uppercase tracking-wider">Compra promedio</p>
                <p class="mt-2 text-2xl font-bold text-primary">S/ {{ number_format($kpis['ticket_promedio'], 2) }}</p>
                <p class="mt-1 text-xs text-secondary">{{ $kpis['productos_distintos'] }} productos distintos</p>
            </div>
            <div class="bg-surface rounded-lg border border-main p-5">
                <p class="text-xs font-medium text-secondary uppercase tracking-wider">Última compra</p>
                <p class="mt-2 text-2xl font-bold text-primary">{{ $kpis['ultima_compra'] ?? '—' }}</p>
                <p class="mt-1 text-xs text-secondary">En el período consultado</p>
            </div>
            <div class="bg-surface rounded-lg border border-main p-5">
                <p class="text-xs font-medium text-secondary uppercase tracking-wider">Nota</p>
                <p class="mt-2 text-sm text-secondary">KPIs sobre compras <strong class="text-primary">recibidas</strong>. El historial incluye también anuladas con su estado.</p>
            </div>
        </div>

        {{-- Productos frecuentes + evolución --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <div class="bg-surface rounded-lg border border-main overflow-x-auto">
                <div class="px-6 py-4 border-b border-main">
                    <h2 class="text-sm font-semibold text-primary">Productos con mayor frecuencia</h2>
                </div>
                <table class="min-w-full divide-y divide-main">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Producto</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Unidades</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Veces</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Último costo</th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface divide-y divide-main">
                        @forelse($porProducto as $fila)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $fila['nombre'] }}</td>
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['cantidad'] }}</td>
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">{{ $fila['veces'] }}</td>
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">S/ {{ number_format($fila['ultimo_costo'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-sm text-secondary">Sin datos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-surface rounded-lg border border-main overflow-x-auto">
                <div class="px-6 py-4 border-b border-main">
                    <h2 class="text-sm font-semibold text-primary">Evolución de precios de compra</h2>
                </div>
                <table class="min-w-full divide-y divide-main">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Producto</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Primero → Último</th>
                            <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Variación</th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface divide-y divide-main">
                        @forelse($evolucion as $evo)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-6 py-8 text-sm text-primary">
                                    {{ $evo['nombre'] }}
                                    <span class="block text-xs text-secondary font-normal">{{ count($evo['registros']) }} registros · mín S/ {{ number_format($evo['minimo'], 2) }} · máx S/ {{ number_format($evo['maximo'], 2) }}</span>
                                    <span class="block text-xs text-secondary font-normal">{{ collect($evo['registros'])->map(fn ($r) => $r['fecha'].': S/ '.number_format($r['costo'], 2))->implode(' · ') }}</span>
                                </td>
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">S/ {{ number_format($evo['primero'], 2) }} → S/ {{ number_format($evo['ultimo'], 2) }}</td>
                                <td class="px-6 py-8 whitespace-nowrap text-sm text-right font-semibold {{ is_null($evo['variacion_pct']) ? 'text-secondary' : ($evo['variacion_pct'] > 0 ? 'text-red-600 dark:text-red-400' : ($evo['variacion_pct'] < 0 ? 'text-green-600 dark:text-green-400' : 'text-secondary')) }}">
                                    {{ is_null($evo['variacion_pct']) ? '—' : ($evo['variacion_pct'] > 0 ? '+' : '').$evo['variacion_pct'].' %' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-secondary">Sin datos suficientes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Historial --}}
        <div class="bg-surface rounded-lg border border-main overflow-x-auto">
            <div class="px-6 py-4 border-b border-main">
                <h2 class="text-sm font-semibold text-primary">Historial de compras</h2>
            </div>
            <table class="min-w-full divide-y divide-main">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Correlativo</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Fecha</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Recepción</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Estado</th>
                        <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-secondary uppercase tracking-wider">Productos</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Subtotal</th>
                        <th scope="col" class="px-6 py-6 text-right text-xs font-medium text-secondary uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-main">
                    @forelse($detalle as $compra)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-primary">{{ $compra->correlativo ?? '—' }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $compra->fecha?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary">{{ $compra->fecha_recepcion?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-6 py-8 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $compra->estado === 'recibida' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' }}">
                                    {{ $compra->etiquetaEstado() }}
                                </span>
                            </td>
                            <td class="px-6 py-8 text-sm text-secondary">{{ $compra->detalles->map(fn ($d) => ($d->producto?->nombre ?? '—').' ×'.$d->cantidad.' (S/ '.number_format($d->costo_unitario, 2).')')->implode(' + ') }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm text-secondary text-right">S/ {{ number_format($compra->subtotal, 2) }}</td>
                            <td class="px-6 py-8 whitespace-nowrap text-sm font-semibold text-primary text-right">S/ {{ number_format($compra->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-8 text-center text-sm text-secondary">El proveedor no tiene compras en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($detalle->hasPages())
            <div class="mt-4"> {{ $detalle->links() }} </div>
        @endif
    @else
        <div class="bg-surface rounded-lg border border-main p-12 text-center">
            <p class="text-sm text-secondary">Seleccione un proveedor para ver su historial, frecuencia de productos y evolución de precios. El comparativo general ya se muestra arriba.</p>
        </div>
    @endif

</div>
@vite('resources/js/reportes/print.js')
@endsection
