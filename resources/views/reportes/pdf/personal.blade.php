@extends('reportes.pdf.base')

@section('titulo', 'Reporte de personal')
@section('subtitulo', 'Mes consultado: '.$resumen['mes'].' ('.$resumen['desde'].' al '.$resumen['hasta'].')')

@section('contenido')
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Trabajadores con marca</div>
                <div class="kpi-valor">{{ count($resumen['trabajadores']) }}</div>
            </td>
            <td>
                <div class="kpi-label">Días con marcación</div>
                <div class="kpi-valor">{{ $resumen['total_dias_con_marca'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Total a pagar (jornales)</div>
                <div class="kpi-valor">S/ {{ number_format($resumen['total_pago_general'], 2) }}</div>
            </td>
        </tr>
    </table>

    <h2>Detalle por trabajador</h2>
    <table>
        <thead>
            <tr>
                <th>Trabajador</th>
                <th class="derecha">Jornal</th>
                <th class="derecha">Asistencias</th>
                <th class="derecha">% asistencia</th>
                <th class="centro">Hora promedio</th>
                <th class="derecha">Total a pagar</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($resumen['trabajadores'] as $fila)
                <tr>
                    <td>{{ $fila['nombre'] }}</td>
                    <td class="derecha">
                        @if($fila['sin_jornal'])
                            Sin jornal
                        @else
                            S/ {{ number_format($fila['pago_diario'], 2) }}
                        @endif
                    </td>
                    <td class="derecha">{{ $fila['asistencias'] }}</td>
                    <td class="derecha">{{ number_format($fila['porcentaje_asistencia'], 2) }}%</td>
                    <td class="centro">{{ $fila['hora_promedio'] ?: '—' }}</td>
                    <td class="derecha resaltado">{{ $fila['sin_jornal'] ? 'S/ 0.00' : 'S/ '.number_format($fila['total_pago'], 2) }}</td>
                    <td>{{ $fila['activo'] ? 'Activo' : 'Inactivo' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="vacio">No hay marcaciones en el mes seleccionado.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection