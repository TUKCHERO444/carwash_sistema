@extends('reportes.pdf.base')

@section('titulo', 'Reporte de caja')
@section('subtitulo', 'Cajas cerradas con fecha de cierre en: '.$etiqueta)

@section('contenido')
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Cajas cerradas</div>
                <div class="kpi-valor">{{ number_format($kpis['cajas']) }}</div>
            </td>
            <td>
                <div class="kpi-label">Ingresos</div>
                <div class="kpi-valor">S/ {{ number_format($kpis['ingresos'], 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Egresos</div>
                <div class="kpi-valor">S/ {{ number_format($kpis['egresos'], 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Saldo neto</div>
                <div class="kpi-valor">S/ {{ number_format($kpis['saldo_neto'], 2) }}</div>
            </td>
        </tr>
    </table>

    <h2>Balance por jornada</h2>
    <table>
        <thead>
            <tr>
                <th>Caja</th>
                <th>Usuario</th>
                <th>Apertura</th>
                <th>Cierre</th>
                <th class="derecha">Monto inicial</th>
                <th class="derecha">Ingresos</th>
                <th class="derecha">Egresos</th>
                <th class="derecha">Balance final</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detalle as $caja)
                <tr>
                    <td>#{{ $caja['id'] }}</td>
                    <td>{{ $caja['usuario'] }}</td>
                    <td>{{ $caja['fecha_apertura'] }}</td>
                    <td>{{ $caja['fecha_cierre'] }}</td>
                    <td class="derecha">S/ {{ number_format($caja['monto_inicial'], 2) }}</td>
                    <td class="derecha">S/ {{ number_format($caja['total_ingresos'], 2) }}</td>
                    <td class="derecha">S/ {{ number_format($caja['total_egresos'], 2) }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($caja['balance_final'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="vacio">No hay cajas cerradas en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Egresos por descripción</h2>
    <table>
        <thead>
            <tr>
                <th>Descripción</th>
                <th>Tipo de pago</th>
                <th class="derecha">Cantidad</th>
                <th class="derecha">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($egresosPorDescripcion as $fila)
                <tr>
                    <td>{{ $fila['descripcion'] }}</td>
                    <td>{{ $fila['tipo_pago'] ?: '—' }}</td>
                    <td class="derecha">{{ $fila['cantidad'] }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($fila['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="vacio">Sin egresos en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Detalle de egresos</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Caja</th>
                <th>Descripción</th>
                <th>Usuario</th>
                <th class="derecha">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($egresos as $egreso)
                <tr>
                    <td>{{ $egreso->created_at->format('d/m/Y H:i') }}</td>
                    <td>#{{ $egreso->caja_id }}</td>
                    <td>{{ $egreso->descripcion }}</td>
                    <td>{{ $egreso->user?->name ?? '—' }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($egreso->monto, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="vacio">Sin egresos en el período.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection