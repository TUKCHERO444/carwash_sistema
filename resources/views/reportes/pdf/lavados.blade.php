@extends('reportes.pdf.base')

@section('titulo', 'Reporte de lavados')
@section('subtitulo', 'Rango aplicado: '.$etiqueta)

@section('contenido')
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Ingreso total</div>
                <div class="kpi-valor">S/ {{ number_format($kpis['total'], 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Lavados confirmados</div>
                <div class="kpi-valor">{{ number_format($kpis['operaciones']) }}</div>
            </td>
            <td>
                <div class="kpi-label">Ticket promedio</div>
                <div class="kpi-valor">S/ {{ number_format($kpis['ticket_promedio'], 2) }}</div>
            </td>
        </tr>
    </table>

    <h2>Por vehículo (placa)</h2>
    <table>
        <thead>
            <tr>
                <th>Placa</th>
                <th class="derecha">Lavados</th>
                <th class="derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($porVehiculo as $fila)
                <tr>
                    <td>{{ $fila['placa'] }}</td>
                    <td class="derecha">{{ $fila['operaciones'] }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($fila['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="vacio">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Por servicio</h2>
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th class="derecha">Lavados</th>
                <th class="derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($porServicio as $fila)
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

    <h2>Por trabajador</h2>
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th class="derecha">Lavados</th>
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
                <th>Vehículo</th>
                <th>Servicios</th>
                <th class="derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detalle as $lavado)
                <tr>
                    <td>{{ $lavado->id }}</td>
                    <td>{{ $lavado->fecha ? $lavado->fecha->format('d/m/Y') : '—' }}</td>
                    <td>{{ $lavado->cliente?->nombre_completo ?? '—' }}</td>
                    <td>{{ $lavado->automotor?->placa ?? '—' }}</td>
                    <td>{{ $lavado->vehiculo?->nombre ?? '—' }}</td>
                    <td>{{ $lavado->servicios->pluck('nombre')->implode(' + ') }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($lavado->total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="vacio">No hay lavados confirmados en el período.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection