<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Carrito — Carwash El Chinito</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #0f172a; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .muted { color: #475569; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background: #f1f5f9; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: bold; }
        .leyenda { margin-top: 12px; font-size: 10px; color: #475569; }
    </style>
</head>
<body>
    <h1>Carrito de compra — Carwash El Chinito</h1>
    <p class="muted">Cliente: {{ $user->name }} ({{ $user->email }})</p>
    <p class="muted">Fecha: {{ $fecha->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="num">Cantidad</th>
                <th class="num">Precio unit.</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item->producto->nombre ?? 'Producto no disponible' }}</td>
                    <td class="num">{{ $item->cantidad }}</td>
                    <td class="num">S/ {{ number_format((float) $item->precio_unitario, 2, '.', '') }}</td>
                    <td class="num">S/ {{ number_format((float) $item->subtotal, 2, '.', '') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Total</td>
                <td class="num">S/ {{ number_format((float) $total, 2, '.', '') }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="leyenda">Cotización referencial: no reserva stock ni genera venta. Precios y stock se confirman en el local.</p>
</body>
</html>
