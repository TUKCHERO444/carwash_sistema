@extends('reportes.pdf.base')

@section('titulo', 'Reporte de inventario')
@section('subtitulo', 'Rango aplicado: '.$etiqueta)

@section('contenido')
    @php
        $totalProductos = $stockActual->count();
        $totalStock = $stockActual->sum(fn ($p) => (int) $p->stock);
        $totalValorizado = round($stockActual->reduce(
            fn (float $acc, $p) => $acc + ((float) $p->stock * (float) $p->precio_compra),
            0.0
        ), 2);
        $totalIngresoTop = round($top->sum('ingreso'), 2);
    @endphp

    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Productos</div>
                <div class="kpi-valor">{{ number_format($totalProductos) }}</div>
            </td>
            <td>
                <div class="kpi-label">Stock total (unidades)</div>
                <div class="kpi-valor">{{ number_format($totalStock) }}</div>
            </td>
            <td>
                <div class="kpi-label">Stock valorizado</div>
                <div class="kpi-valor">S/ {{ number_format($totalValorizado, 2) }}</div>
            </td>
            <td>
                <div class="kpi-label">Ingresos del rango (top)</div>
                <div class="kpi-valor">S/ {{ number_format($totalIngresoTop, 2) }}</div>
            </td>
        </tr>
    </table>

    <h2>Top de productos vendidos (por cantidad)</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Marca</th>
                <th class="derecha">Cantidad</th>
                <th class="derecha">Ingreso</th>
            </tr>
        </thead>
        <tbody>
            @forelse($top as $fila)
                <tr>
                    <td>{{ $fila['nombre'] }}</td>
                    <td>{{ $fila['categoria'] ?: '—' }}</td>
                    <td>{{ $fila['marca'] ?: '—' }}</td>
                    <td class="derecha">{{ $fila['cantidad'] }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($fila['ingreso'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="vacio">Sin datos en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Stock actual valorizado</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="derecha">Stock</th>
                <th class="derecha">Valorizado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($stockActual as $producto)
                <tr>
                    <td>{{ $producto->nombre }}</td>
                    <td class="derecha">{{ $producto->stock }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($producto->stock * $producto->precio_compra, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="vacio">Sin productos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Resumen por categoría</h2>
    <table>
        <thead>
            <tr>
                <th>Categoría</th>
                <th class="derecha">Productos</th>
                <th class="derecha">Stock</th>
                <th class="derecha">Ingresos</th>
            </tr>
        </thead>
        <tbody>
            @forelse($resumenCategorias as $fila)
                <tr>
                    <td>{{ $fila['nombre'] }}</td>
                    <td class="derecha">{{ $fila['productos'] }}</td>
                    <td class="derecha">{{ $fila['stock_total'] }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($fila['ingresos'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="vacio">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Resumen por marca</h2>
    <table>
        <thead>
            <tr>
                <th>Marca</th>
                <th class="derecha">Productos</th>
                <th class="derecha">Stock</th>
                <th class="derecha">Ingresos</th>
            </tr>
        </thead>
        <tbody>
            @forelse($resumenMarcas as $fila)
                <tr>
                    <td>{{ $fila['nombre'] }}</td>
                    <td class="derecha">{{ $fila['productos'] }}</td>
                    <td class="derecha">{{ $fila['stock_total'] }}</td>
                    <td class="derecha resaltado">S/ {{ number_format($fila['ingresos'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="vacio">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection