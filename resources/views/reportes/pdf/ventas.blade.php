@extends('reportes.pdf.base')

@section('titulo', 'Reporte de ventas')
@section('subtitulo', 'Rango aplicado: '.$etiqueta)

@section('contenido')
    @php
        $labelsMetodo = ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'izipay' => 'Izipay', 'mixto' => 'Mixto'];
    @endphp

    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Ingreso total</div>
                <div class="kpi-valor">S/ {{ number_format($kpis['total'], 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Operaciones</div>
                <div class="kpi-valor">{{ number_format($kpis['operaciones']) }}</div>
            </td>
            <td>
                <div class="kpi-label">Ticket promedio</div>
                <div class="kpi-valor">S/ {{ number_format($kpis['ticket_promedio'], 2) }}</div>
            </td>
        </tr>
    </table>

    <h2>Por usuario</h2>
    <table>
        <thead>
            <tr>
                <th>Usuario</th>
                <th class="derecha">Ventas</th>
                <th class="derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($porUsuario as $fila)
                <tr>
                    <td>{{ $fila['usuario'] }}</td>
                    <td class="derecha">{{ $fila['operaciones'] }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($fila['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="vacio">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Por método de pago</h2>
    <table>
        <thead>
            <tr>
                <th>Método</th>
                <th class="derecha">Operaciones</th>
                <th class="derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($porMetodo as $fila)
                <tr>
                    <td>{{ $labelsMetodo[$fila['metodo']] ?? ucfirst($fila['metodo']) }}</td>
                    <td class="derecha">{{ $fila['operaciones'] }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($fila['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="vacio">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Detalle</h2>
    <table>
        <thead>
            <tr>
                <th>Correlativo</th>
                <th>Fecha</th>
                <th>Usuario</th>
                <th>Método</th>
                <th class="derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detalle as $venta)
                <tr>
                    <td>{{ $venta->correlativo }}</td>
                    <td>{{ $venta->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $venta->user?->name ?? '—' }}</td>
                    <td>{{ $labelsMetodo[$venta->metodo_pago] ?? ucfirst($venta->metodo_pago) }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($venta->total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="vacio">No hay ventas en el período.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection