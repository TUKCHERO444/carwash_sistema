@extends('reportes.pdf.base')

@section('titulo', 'Reporte de cambio de aceite')
@section('subtitulo', 'Rango aplicado: '.$etiqueta)

@section('contenido')
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Ingreso total</div>
                <div class="kpi-valor">S/ {{ number_format($kpis['total'], 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Cambios confirmados</div>
                <div class="kpi-valor">{{ number_format($kpis['operaciones']) }}</div>
            </td>
            <td>
                <div class="kpi-label">Ticket promedio</div>
                <div class="kpi-valor">S/ {{ number_format($kpis['ticket_promedio'], 2) }}</div>
            </td>
        </tr>
    </table>

    <h2>Por producto</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="derecha">Unidades</th>
                <th class="derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($porProducto as $fila)
                <tr>
                    <td>{{ $fila['nombre'] }}</td>
                    <td class="derecha">{{ $fila['cantidad'] }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($fila['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="vacio">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Por trabajador</h2>
    <table>
        <thead>
            <tr>
                <th>Trabajador</th>
                <th class="derecha">Cambios</th>
                <th class="derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($porTrabajador as $fila)
                <tr>
                    <td>{{ $fila['nombre'] }}</td>
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
                <th>ID</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Placa</th>
                <th>Trabajadores</th>
                <th>Productos</th>
                <th class="derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detalle as $cambio)
                <tr>
                    <td>{{ $cambio->id }}</td>
                    <td>{{ $cambio->created_at ? $cambio->created_at->format('d/m/Y H:i') : '—' }}</td>
                    <td>{{ $cambio->cliente?->nombre_completo ?? '—' }}</td>
                    <td>{{ $cambio->automotor?->placa ?? '—' }}</td>
                    <td>{{ $cambio->trabajadores->pluck('nombre_completo')->implode(' + ') }}</td>
                    <td>{{ $cambio->productos->pluck('nombre')->implode(' + ') }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($cambio->total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="vacio">No hay cambios de aceite confirmados en el período.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection