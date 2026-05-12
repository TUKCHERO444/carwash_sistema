<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket — {{ config('app.name') }}</title>
    <style>
        /* ── Base ── */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: sans-serif;
            font-size: 13px;
            color: #111;
            background: #f5f5f5;
            padding: 24px 16px;
        }

        /* ── Ticket wrapper ── */
        .ticket {
            max-width: 400px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
        }

        /* ── Header ── */
        .ticket-header {
            text-align: center;
            margin-bottom: 12px;
        }

        .ticket-header h1 {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .ticket-header p {
            font-size: 11px;
            color: #555;
            margin-top: 2px;
        }

        /* ── Sections ── */
        .ticket-section {
            margin: 12px 0;
        }

        .ticket-section h2 {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #555;
            margin-bottom: 6px;
        }

        hr {
            border: none;
            border-top: 1px solid #ddd;
            margin: 12px 0;
        }

        /* ── Data rows ── */
        .data-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 4px;
        }

        .data-row .label {
            color: #555;
            flex-shrink: 0;
        }

        .data-row .value {
            font-weight: 500;
            text-align: right;
        }

        /* ── Products table ── */
        .services-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .services-table thead tr {
            border-bottom: 1px solid #ddd;
        }

        .services-table th {
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #555;
            padding: 4px 0;
        }

        .services-table th:last-child,
        .services-table td:last-child {
            text-align: right;
        }

        .services-table td {
            padding: 5px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .services-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* ── Totals ── */
        .totals {
            font-size: 12px;
        }

        .totals .total-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
        }

        .totals .total-row.final {
            font-size: 14px;
            font-weight: 700;
            border-top: 1px solid #ddd;
            margin-top: 4px;
            padding-top: 6px;
        }

        .totals .total-row.discount {
            color: #c00;
        }

        /* ── Action buttons (screen only) ── */
        .ticket-actions {
            max-width: 400px;
            margin: 16px auto 0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            border: none;
        }

        .btn-print {
            background: #2563eb;
            color: #fff;
        }

        .btn-print:hover {
            background: #1d4ed8;
        }

        .btn-back {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-back:hover {
            background: #e5e7eb;
        }

        /* ── Print styles ── */
        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .ticket {
                border: none;
                border-radius: 0;
                padding: 8px;
                max-width: 100%;
            }

            .ticket-actions {
                display: none !important;
            }

            /* Hide any browser navigation chrome */
            nav,
            header,
            footer,
            aside {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    {{-- ── Ticket ── --}}
    <div class="ticket">

        {{-- Header --}}
        <div class="ticket-header">
            <h1>{{ config('app.name') }}</h1>
            <p>Orden de Trabajo</p>
        </div>

        <hr>

        {{-- Date & Client --}}
        <div class="ticket-section">
            <h2>Datos del cliente</h2>

            <div class="data-row">
                <span class="label">Fecha:</span>
                <span class="value">{{ $cambioAceite->fecha->format('d/m/Y') }}</span>
            </div>

            <div class="data-row">
                <span class="label">Placa:</span>
                <span class="value">{{ $cambioAceite->cliente->placa }}</span>
            </div>

            @if($cambioAceite->cliente->nombre)
                <div class="data-row">
                    <span class="label">Nombre:</span>
                    <span class="value">{{ $cambioAceite->cliente->nombre }}</span>
                </div>
            @endif

            @if($cambioAceite->cliente->dni)
                <div class="data-row">
                    <span class="label">DNI:</span>
                    <span class="value">{{ $cambioAceite->cliente->dni }}</span>
                </div>
            @endif
        </div>

        <hr>

        {{-- Worker --}}
        <div class="ticket-section">
            <h2>Trabajadores</h2>

            <div class="data-row">
                <span class="label">Responsable(s):</span>
                <span class="value">
                    @if($cambioAceite->trabajadores->isNotEmpty())
                        {{ $cambioAceite->trabajadores->pluck('nombre')->join(', ') }}
                    @else
                        N/A
                    @endif
                </span>
            </div>
        </div>

        <hr>

        {{-- Products --}}
        <div class="ticket-section">
            <h2>Productos</h2>

            @if($cambioAceite->productos->isEmpty())
                <p style="color:#888;">Sin productos registrados.</p>
            @else
                <table class="services-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Cant.</th>
                            <th>P.Unit.</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cambioAceite->productos as $producto)
                            <tr>
                                <td>{{ $producto->nombre }}</td>
                                <td>{{ $producto->pivot->cantidad }}</td>
                                <td>S/ {{ number_format($producto->pivot->precio, 2) }}</td>
                                <td>S/ {{ number_format($producto->pivot->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @if($cambioAceite->descripcion)
            <hr>

            {{-- Description --}}
            <div class="ticket-section">
                <h2>Descripción</h2>
                <p style="color:#333; line-height:1.5;">{{ $cambioAceite->descripcion }}</p>
            </div>
        @endif

        <hr>

        {{-- Totals --}}
        <div class="ticket-section">
            <h2>Totales</h2>

            <div class="totals">
                {{-- Precio (inalterable) --}}
                <div class="total-row">
                    <span>Precio:</span>
                    <span>S/ {{ number_format($cambioAceite->precio, 2) }}</span>
                </div>

                @if($cambioAceite->total < $cambioAceite->precio)
                    {{-- Descuento --}}
                    <div class="total-row discount">
                        <span>Descuento:</span>
                        <span>− S/ {{ number_format($cambioAceite->precio - $cambioAceite->total, 2) }}</span>
                    </div>

                    {{-- Total final --}}
                    <div class="total-row final">
                        <span>Total final:</span>
                        <span>S/ {{ number_format($cambioAceite->total, 2) }}</span>
                    </div>
                @else
                    {{-- No discount — show only total --}}
                    <div class="total-row final">
                        <span>Total:</span>
                        <span>S/ {{ number_format($cambioAceite->total, 2) }}</span>
                    </div>
                @endif

                {{-- Método de pago --}}
                @php $metodosLabel = ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'izipay' => 'Izipay', 'mixto' => 'Mixto']; @endphp
                <div class="total-row">
                    <span>Pago:</span>
                    <span>{{ $metodosLabel[$cambioAceite->metodo_pago] ?? ucfirst($cambioAceite->metodo_pago) }}</span>
                </div>
                @if($cambioAceite->metodo_pago === 'mixto')
                    @if($cambioAceite->monto_efectivo)
                        <div class="total-row" style="font-size:11px; color:#555; padding-left:8px;">
                            <span>Efectivo:</span>
                            <span>S/ {{ number_format($cambioAceite->monto_efectivo, 2) }}</span>
                        </div>
                    @endif
                    @if($cambioAceite->monto_yape)
                        <div class="total-row" style="font-size:11px; color:#555; padding-left:8px;">
                            <span>Yape:</span>
                            <span>S/ {{ number_format($cambioAceite->monto_yape, 2) }}</span>
                        </div>
                    @endif
                    @if($cambioAceite->monto_izipay)
                        <div class="total-row" style="font-size:11px; color:#555; padding-left:8px;">
                            <span>Izipay:</span>
                            <span>S/ {{ number_format($cambioAceite->monto_izipay, 2) }}</span>
                        </div>
                    @endif
                @endif
            </div>
        </div>

    </div>{{-- /.ticket --}}

    {{-- Action buttons (hidden on print) --}}
    <div class="ticket-actions">
        <a href="{{ route('cambio-aceite.show', $cambioAceite) }}" class="btn btn-back">
            ← Volver
        </a>
        <button type="button" class="btn btn-print" onclick="window.print()">
            🖨 Imprimir
        </button>
    </div>

</body>
</html>
