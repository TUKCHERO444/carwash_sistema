@extends('reportes.pdf.base')

@section('titulo', 'Reporte operativo')
@section('subtitulo', 'Rango aplicado: '.$etiqueta)

@section('contenido')
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Atenciones</div>
                <div class="kpi-valor">{{ number_format($kpis['atenciones']) }}</div>
            </td>
            <td>
                <div class="kpi-label">Vehículos únicos</div>
                <div class="kpi-valor">{{ number_format($kpis['vehiculos_unicos']) }}</div>
            </td>
            <td>
                <div class="kpi-label">Tiempo promedio</div>
                <div class="kpi-valor">{{ $kpis['tiempo_promedio'] !== null ? number_format($kpis['tiempo_promedio'], 0).' min' : '—' }}</div>
            </td>
            <td>
                <div class="kpi-label">Ticket operativo</div>
                <div class="kpi-valor">S/ {{ number_format($kpis['ticket_promedio'], 2) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="kpi-label">Atenciones por día</div>
                <div class="kpi-valor">{{ number_format($kpis['atenciones_por_dia'], 1) }}</div>
            </td>
            <td>
                <div class="kpi-label">Clientes recurrentes</div>
                <div class="kpi-valor">{{ number_format($kpis['clientes_recurrentes']) }} ({{ number_format($kpis['pct_recurrentes'], 1) }} %)</div>
            </td>
            <td>
                <div class="kpi-label">Hora pico</div>
                <div class="kpi-valor">{{ $porHora['hora_pico'] !== null ? sprintf('%02d:00', $porHora['hora_pico']) : '—' }}</div>
            </td>
            <td>
                <div class="kpi-label">Tiempos lav/cam</div>
                <div class="kpi-valor">{{ $tiempos['lavados'] !== null ? number_format($tiempos['lavados'], 0).'/' : '—/' }}{{ $tiempos['cambios'] !== null ? number_format($tiempos['cambios'], 0).' min' : '—' }}</div>
            </td>
        </tr>
    </table>

    <h2>Atenciones por día</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th class="derecha">Atenciones</th>
                <th class="derecha">Vehículos únicos</th>
            </tr>
        </thead>
        <tbody>
            @forelse($serie['dias'] as $i => $dia)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($dia)->format('d/m/Y') }}</td>
                    <td class="derecha">{{ $serie['atenciones'][$i] }}</td>
                    <td class="derecha">{{ $serie['vehiculos_unicos'][$i] }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="vacio">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Por trabajador (participaciones)</h2>
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th class="derecha">Lavados</th>
                <th class="derecha">Cambios</th>
                <th class="derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($porTrabajador as $fila)
                <tr>
                    <td>{{ $fila['nombre'] }}</td>
                    <td class="derecha">{{ $fila['lavados'] }}</td>
                    <td class="derecha">{{ $fila['cambios'] }}</td>
                    <td class="derecha resaltado">{{ $fila['operaciones'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="vacio">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Servicios más solicitados</h2>
    <table>
        <thead>
            <tr>
                <th>Servicio</th>
                <th class="derecha">Atenciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($porServicio as $fila)
                <tr>
                    <td>{{ $fila['nombre'] }}</td>
                    <td class="derecha resaltado">{{ $fila['operaciones'] }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="vacio">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Por tipo de vehículo</h2>
    <table>
        <thead>
            <tr>
                <th>Tipo</th>
                <th class="derecha">Lavados</th>
                <th class="derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($porTipoVehiculo as $fila)
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

    <h2>Placas más atendidas</h2>
    <table>
        <thead>
            <tr>
                <th>Placa</th>
                <th class="derecha">Atenciones</th>
                <th class="derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($topPlacas as $fila)
                <tr>
                    <td>{{ $fila['placa'] }}</td>
                    <td class="derecha">{{ $fila['atenciones'] }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($fila['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="vacio">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Distribución por hora</h2>
    <table>
        <thead>
            <tr>
                <th>Hora</th>
                <th class="derecha">Atenciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse(collect($porHora['horas'])->filter(fn ($t) => $t > 0) as $hora => $total)
                <tr>
                    <td>{{ sprintf('%02d:00', $hora) }}{{ $porHora['hora_pico'] === $hora ? ' · pico' : '' }}</td>
                    <td class="derecha resaltado">{{ $total }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="vacio">Sin atenciones en el rango.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
