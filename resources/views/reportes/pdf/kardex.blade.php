@extends('reportes.pdf.base')

@section('titulo', 'Reporte de kardex')
@section('subtitulo', 'Rango aplicado: '.$etiqueta.' · Saldo neto = entradas − salidas del rango')

@section('contenido')
    @php
        $totalEntradas = $agregado->sum('entradas');
        $totalSalidas = $agregado->sum('salidas');
    @endphp

    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Productos con movimiento</div>
                <div class="kpi-valor">{{ $agregado->count() }}</div>
            </td>
            <td>
                <div class="kpi-label">Entradas (unidades)</div>
                <div class="kpi-valor">{{ number_format($totalEntradas) }}</div>
            </td>
            <td>
                <div class="kpi-label">Salidas (unidades)</div>
                <div class="kpi-valor">{{ number_format($totalSalidas) }}</div>
            </td>
            <td>
                <div class="kpi-label">Movimientos en el rango</div>
                <div class="kpi-valor">{{ $detalle->count() }}</div>
            </td>
        </tr>
    </table>

    <h2>Movimientos agregados por producto</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="derecha">Entradas</th>
                <th class="derecha">Salidas</th>
                <th class="derecha">Saldo neto</th>
                <th class="derecha">Stock actual</th>
            </tr>
        </thead>
        <tbody>
            @forelse($agregado as $fila)
                <tr>
                    <td>{{ $fila['producto'] }}</td>
                    <td class="derecha">{{ $fila['entradas'] }}</td>
                    <td class="derecha">{{ $fila['salidas'] }}</td>
                    <td class="derecha resaltado">{{ $fila['saldo_neto'] }}</td>
                    <td class="derecha">{{ $fila['stock_actual'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="vacio">Sin movimientos en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Detalle de movimientos</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Producto</th>
                <th>Tipo</th>
                <th>Fuente</th>
                <th class="derecha">Cantidad</th>
                <th class="derecha">Stock antes</th>
                <th class="derecha">Stock después</th>
                <th>Usuario</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detalle as $movimiento)
                <tr>
                    <td>{{ $movimiento->id }}</td>
                    <td>{{ $movimiento->fecha_movimiento->format('d/m/Y H:i') }}</td>
                    <td>{{ $movimiento->producto?->nombre ?? '—' }}</td>
                    <td>{{ ucfirst($movimiento->tipo) }}</td>
                    <td>{{ $movimiento->fuente }}</td>
                    <td class="derecha">{{ $movimiento->cantidad }}</td>
                    <td class="derecha">{{ $movimiento->stock_antes }}</td>
                    <td class="derecha">{{ $movimiento->stock_despues }}</td>
                    <td>{{ $movimiento->usuario?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="vacio">Sin movimientos en el período.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection