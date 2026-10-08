@extends('reportes.pdf.base')

@section('titulo', 'Historial de atención por vehículo')
@section('subtitulo', 'Placa '.$automotor->placa.' · Rango aplicado: '.$etiqueta)

@section('contenido')
    <h2>Vehículo</h2>
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Placa</div>
                <div class="kpi-valor">{{ $automotor->placa }}</div>
            </td>
            <td>
                <div class="kpi-label">Cliente</div>
                <div class="kpi-valor">{{ $automotor->cliente?->nombre_completo ?? '—' }}</div>
            </td>
            <td>
                <div class="kpi-label">Marca / Modelo</div>
                <div class="kpi-valor">{{ trim(($automotor->marca ?? '').' '.($automotor->modelo ?? '')) ?: '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Operaciones</div>
                <div class="kpi-valor">{{ number_format($kpis['operaciones']) }}</div>
            </td>
            <td>
                <div class="kpi-label">Total gastado</div>
                <div class="kpi-valor">S/ {{ number_format($kpis['total'], 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Última atención</div>
                <div class="kpi-valor">{{ $kpis['ultima_atencion'] ?? '—' }}</div>
            </td>
            <td>
                <div class="kpi-label">Último cambio</div>
                <div class="kpi-valor">{{ $kpis['ultimo_cambio']['fecha'] ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <h2>Servicios utilizados</h2>
    <table>
        <thead>
            <tr>
                <th>Servicio</th>
                <th class="derecha">Veces</th>
            </tr>
        </thead>
        <tbody>
            @forelse($porServicio as $fila)
                <tr>
                    <td>{{ $fila['nombre'] }}</td>
                    <td class="derecha">{{ $fila['operaciones'] }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="vacio">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Productos utilizados (cambios)</h2>
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

    <h2>Historial cronológico</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Cliente</th>
                <th>Servicios / Productos</th>
                <th>Trabajadores</th>
                <th class="derecha">Importe</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($historial as $atencion)
                <tr>
                    <td>{{ $atencion['fecha'] }}</td>
                    <td>{{ $atencion['tipo_etiqueta'] }}</td>
                    <td>{{ $atencion['cliente'] }}</td>
                    <td>{{ $atencion['detalle'] }}</td>
                    <td>{{ $atencion['trabajadores'] }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($atencion['total'], 2) }}</td>
                    <td>{{ ucfirst($atencion['estado']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="vacio">El vehículo no tiene atenciones confirmadas en el período.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
