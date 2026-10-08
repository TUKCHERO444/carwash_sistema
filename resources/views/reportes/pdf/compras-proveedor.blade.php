@extends('reportes.pdf.base')

@section('titulo', 'Compras por proveedor')
@section('subtitulo', 'Rango aplicado: '.$etiqueta)

@section('contenido')
    <h2>Comparativo de proveedores (compras recibidas)</h2>
    <table>
        <thead>
            <tr>
                <th>Proveedor</th>
                <th>RUC</th>
                <th class="derecha">Compras</th>
                <th class="derecha">Total</th>
                <th>Última compra</th>
            </tr>
        </thead>
        <tbody>
            @forelse($resumen as $fila)
                <tr>
                    <td>{{ $fila['razon_social'] }}</td>
                    <td>{{ $fila['ruc'] ?: '—' }}</td>
                    <td class="derecha">{{ $fila['compras'] }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($fila['total'], 2) }}</td>
                    <td>{{ $fila['ultima_compra'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="vacio">Sin compras recibidas en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($proveedor)
        <h2>{{ $proveedor->razon_social }}</h2>
        <table class="kpis">
            <tr>
                <td>
                    <div class="kpi-label">Total comprado</div>
                    <div class="kpi-valor">S/ {{ number_format($kpis['total'], 2) }}</div>
                </td>
                <td>
                    <div class="kpi-label">Compras recibidas</div>
                    <div class="kpi-valor">{{ number_format($kpis['compras']) }}</div>
                </td>
                <td>
                    <div class="kpi-label">Última compra</div>
                    <div class="kpi-valor">{{ $kpis['ultima_compra'] ?? '—' }}</div>
                </td>
            </tr>
        </table>

        <h2>Productos con mayor frecuencia</h2>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th class="derecha">Unidades</th>
                    <th class="derecha">Veces</th>
                    <th class="derecha">Último costo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($porProducto as $fila)
                    <tr>
                        <td>{{ $fila['nombre'] }}</td>
                        <td class="derecha">{{ $fila['cantidad'] }}</td>
                        <td class="derecha">{{ $fila['veces'] }}</td>
                        <td class="derecha">S/ {{ number_format($fila['ultimo_costo'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="vacio">Sin datos.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Evolución de precios de compra</h2>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th class="derecha">Primero → Último</th>
                    <th class="derecha">Variación</th>
                </tr>
            </thead>
            <tbody>
                @forelse($evolucion as $evo)
                    <tr>
                        <td>{{ $evo['nombre'] }} ({{ count($evo['registros']) }} registros)</td>
                        <td class="derecha">S/ {{ number_format($evo['primero'], 2) }} → S/ {{ number_format($evo['ultimo'], 2) }}</td>
                        <td class="derecha">{{ is_null($evo['variacion_pct']) ? '—' : (($evo['variacion_pct'] > 0 ? '+' : '').$evo['variacion_pct'].' %') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="vacio">Sin datos suficientes.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Historial de compras</h2>
        <table>
            <thead>
                <tr>
                    <th>Correlativo</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Productos</th>
                    <th class="derecha">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($historial as $compra)
                    <tr>
                        <td>{{ $compra->correlativo ?? '—' }}</td>
                        <td>{{ $compra->fecha?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $compra->etiquetaEstado() }}</td>
                        <td>{{ $compra->detalles->map(fn ($d) => ($d->producto?->nombre ?? '—').' ×'.$d->cantidad.' (S/ '.number_format($d->costo_unitario, 2).')')->implode(' + ') }}</td>
                        <td class="derecha resaltado">S/ {{ number_format($compra->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="vacio">El proveedor no tiene compras en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
@endsection
