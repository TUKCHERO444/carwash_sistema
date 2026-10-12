@extends('reportes.pdf.base')

@section('titulo', 'Reporte de clientes')
@section('subtitulo', 'Rango aplicado: '.$etiqueta)

@section('contenido')
    @php
        $totalGasto = round($topClientes->sum('gasto'), 2);
        $totalVisitas = $topClientes->sum('visitas');
        $totalVehiculos = $topClientes->sum('automotores');
    @endphp

    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Gasto total (top clientes)</div>
                <div class="kpi-valor">S/ {{ number_format($totalGasto, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Visitas (top clientes)</div>
                <div class="kpi-valor">{{ number_format($totalVisitas) }}</div>
            </td>
            <td>
                <div class="kpi-label">Vehículos atendidos</div>
                <div class="kpi-valor">{{ number_format($totalVehiculos) }}</div>
            </td>
        </tr>
    </table>

    <h2>Top clientes por gasto</h2>
    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th class="derecha">Gasto</th>
                <th class="derecha">Visitas</th>
                <th class="derecha">Vehículos</th>
                <th class="derecha">Lavados</th>
                <th class="derecha">Cambio de aceite</th>
                <th class="derecha">Visitas / mes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($topClientes as $fila)
                <tr>
                    <td>{{ $fila['nombre'] }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($fila['gasto'], 2) }}</td>
                    <td class="derecha">{{ $fila['visitas'] }}</td>
                    <td class="derecha">{{ $fila['vehiculos'] }}</td>
                    <td class="derecha">{{ $fila['lavados'] }}</td>
                    <td class="derecha">{{ $fila['cambio_aceite'] }}</td>
                    <td class="derecha">{{ number_format($fila['visitas_por_mes'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="vacio">Sin datos en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Vehículos más atendidos</h2>
    <table>
        <thead>
            <tr>
                <th>Placa</th>
                <th>Vehículo</th>
                <th>Cliente</th>
                <th class="derecha">Visitas</th>
                <th class="derecha">Ingresos</th>
            </tr>
        </thead>
        <tbody>
            @forelse($topVehiculos as $fila)
                <tr>
                    <td>{{ $fila['placa'] }}</td>
                    <td>{{ trim(implode(' ', array_filter([$fila['marca'], $fila['modelo']]))); }}</td>
                    <td>{{ $fila['cliente'] ?: '—' }}</td>
                    <td class="derecha">{{ $fila['visitas'] }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($fila['ingresos'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="vacio">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Frecuencia por cliente</h2>
    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th class="derecha">Gasto</th>
                <th class="derecha">Visitas</th>
                <th class="derecha">Vehículos registrados</th>
                <th class="derecha">Visitas / mes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($frecuencia as $fila)
                <tr>
                    <td>{{ $fila['nombre'] }}</td>
                    <td class="derecha">S/ {{ number_format($fila['gasto'], 2) }}</td>
                    <td class="derecha">{{ $fila['visitas'] }}</td>
                    <td class="derecha">{{ $fila['vehiculos'] }}</td>
                    <td class="derecha">{{ number_format($fila['visitas_por_mes'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="vacio">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection