@extends('reportes.pdf.base')

@section('titulo', 'Reporte de ingresos')
@section('subtitulo', 'Rango aplicado: '.$etiqueta)

@section('contenido')
    @php
        $labelsFuente = [
            'ventas' => 'Ventas',
            'lavados' => 'Lavados',
            'cambio_aceite' => 'Cambio de aceite',
        ];
    @endphp

    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Ingreso total</div>
                <div class="kpi-valor">S/ {{ number_format($consolidado['total'], 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Operaciones</div>
                <div class="kpi-valor">{{ number_format($consolidado['operaciones']) }}</div>
            </td>
            <td>
                <div class="kpi-label">Ticket promedio</div>
                <div class="kpi-valor">S/ {{ number_format($consolidado['ticket_promedio'], 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Método de pago</div>
                <div class="kpi-valor" style="font-size: 9pt; font-weight: normal;">
                    Efectivo: S/ {{ number_format($metodoPago['efectivo'], 2) }}<br>
                    Yape: S/ {{ number_format($metodoPago['yape'], 2) }}<br>
                    Izipay: S/ {{ number_format($metodoPago['izipay'], 2) }}
                </div>
            </td>
        </tr>
    </table>

    <h2>Desglose por fuente</h2>
    <table>
        <thead>
            <tr>
                <th>Fuente</th>
                <th class="derecha">Monto</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Ventas</td>
                <td class="derecha">S/ {{ number_format($consolidado['ventas'], 2) }}</td>
            </tr>
            <tr>
                <td>Lavados</td>
                <td class="derecha">S/ {{ number_format($consolidado['lavados'], 2) }}</td>
            </tr>
            <tr>
                <td>Cambio de aceite</td>
                <td class="derecha">S/ {{ number_format($consolidado['cambios'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Ingresos diarios por fuente</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th class="derecha">Ventas</th>
                <th class="derecha">Lavados</th>
                <th class="derecha">Cambio de aceite</th>
                <th class="derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($serie['dias'] as $i => $dia)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($dia)->format('d/m/Y') }}</td>
                    <td class="derecha">S/ {{ number_format($serie['ventas'][$i], 2) }}</td>
                    <td class="derecha">S/ {{ number_format($serie['lavados'][$i], 2) }}</td>
                    <td class="derecha">S/ {{ number_format($serie['cambios'][$i], 2) }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($serie['totals'][$i], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="vacio">Sin datos en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Días de mayor ingreso</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th class="derecha">Ventas</th>
                <th class="derecha">Lavados</th>
                <th class="derecha">Cambio de aceite</th>
                <th class="derecha">Total</th>
                <th>Actividad dominante</th>
            </tr>
        </thead>
        <tbody>
            @forelse($diasTop as $dia)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($dia['fecha'])->format('d/m/Y') }}</td>
                    <td class="derecha">S/ {{ number_format($dia['ventas'], 2) }}</td>
                    <td class="derecha">S/ {{ number_format($dia['lavados'], 2) }}</td>
                    <td class="derecha">S/ {{ number_format($dia['cambios'], 2) }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($dia['total'], 2) }}</td>
                    <td>{{ $dia['actividad_dominante'] ? ($labelsFuente[$dia['actividad_dominante']] ?? $dia['actividad_dominante']) : 'Mixto' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="vacio">Sin datos en el período.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection