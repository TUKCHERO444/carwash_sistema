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

        /* ── Workers list ── */
        .workers-list {
            list-style: none;
            padding: 0;
        }

        .workers-list li {
            padding: 2px 0;
        }

        .workers-list li::before {
            content: "• ";
            color: #888;
        }

        /* ── Services table ── */
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

        /* ── Photo ── */
        .vehicle-photo {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
            margin-top: 6px;
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
                <span class="value">{{ $ingreso->fecha->format('d/m/Y') }}</span>
            </div>

            <div class="data-row">
                <span class="label">Placa:</span>
                <span class="value">{{ $ingreso->cliente->placa }}</span>
            </div>

            @if($ingreso->cliente->nombre)
                <div class="data-row">
                    <span class="label">Cliente:</span>
                    <span class="value">{{ $ingreso->cliente->nombre }}</span>
                </div>
            @endif

            @if($ingreso->cliente->dni)
                <div class="data-row">
                    <span class="label">DNI:</span>
                    <span class="value">{{ $ingreso->cliente->dni }}</span>
                </div>
            @endif
        </div>

        <hr>

        {{-- Vehicle --}}
        <div class="ticket-section">
            <h2>Vehículo</h2>

            <div class="data-row">
                <span class="label">Tipo:</span>
                <span class="value">{{ $ingreso->vehiculo->nombre }}</span>
            </div>

            <div class="data-row">
                <span class="label">Precio base:</span>
                <span class="value">S/ {{ number_format($ingreso->vehiculo->precio, 2) }}</span>
            </div>
        </div>

        {{-- Vehicle photo --}}
        @if($ingreso->foto)
            <hr>
            <div class="ticket-section">
                <h2>Foto del vehículo</h2>
                <img
                    src="{{ Storage::url($ingreso->foto) }}"
                    alt="Foto del vehículo"
                    class="vehicle-photo"
                >
            </div>
        @endif

        <hr>

        {{-- Workers --}}
        <div class="ticket-section">
            <h2>Trabajadores asignados</h2>

            @if($ingreso->trabajadores->isEmpty())
                <p style="color:#888;">Sin trabajadores asignados.</p>
            @else
                <ul class="workers-list">
                    @foreach($ingreso->trabajadores as $trabajador)
                        <li>{{ $trabajador->nombre }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <hr>

        {{-- Services --}}
        <div class="ticket-section">
            <h2>Servicios</h2>

            @if($ingreso->servicios->isEmpty())
                <p style="color:#888;">Sin servicios asignados.</p>
            @else
                <table class="services-table">
                    <thead>
                        <tr>
                            <th>Servicio</th>
                            <th>Precio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ingreso->servicios as $servicio)
                            <tr>
                                <td>{{ $servicio->nombre }}</td>
                                <td>S/ {{ number_format($servicio->precio, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <hr>

        {{-- Totals --}}
        <div class="ticket-section">
            <h2>Totales</h2>

            <div class="totals">
                {{-- Precio base del vehículo --}}
                <div class="total-row">
                    <span>Precio base vehículo:</span>
                    <span>S/ {{ number_format($ingreso->vehiculo->precio, 2) }}</span>
                </div>

                {{-- Suma de servicios --}}
                @php
                    $sumaServicios = $ingreso->servicios->sum('precio');
                @endphp
                <div class="total-row">
                    <span>Suma de servicios:</span>
                    <span>S/ {{ number_format($sumaServicios, 2) }}</span>
                </div>

                {{-- Precio total (inalterable) --}}
                <div class="total-row">
                    <span>Precio total:</span>
                    <span>S/ {{ number_format($ingreso->precio, 2) }}</span>
                </div>

                @if($ingreso->total < $ingreso->precio)
                    {{-- Descuento --}}
                    <div class="total-row discount">
                        <span>Descuento:</span>
                        <span>− S/ {{ number_format($ingreso->precio - $ingreso->total, 2) }}</span>
                    </div>

                    {{-- Total final --}}
                    <div class="total-row final">
                        <span>Total final:</span>
                        <span>S/ {{ number_format($ingreso->total, 2) }}</span>
                    </div>
                @else
                    {{-- No discount — show only total --}}
                    <div class="total-row final">
                        <span>Total:</span>
                        <span>S/ {{ number_format($ingreso->total, 2) }}</span>
                    </div>
                @endif

                {{-- Método de pago --}}
                @php $metodosLabel = ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'izipay' => 'Izipay', 'mixto' => 'Mixto']; @endphp
                <div class="total-row">
                    <span>Pago:</span>
                    <span>{{ $metodosLabel[$ingreso->metodo_pago] ?? ucfirst($ingreso->metodo_pago) }}</span>
                </div>
                @if($ingreso->metodo_pago === 'mixto')
                    @if($ingreso->monto_efectivo)
                        <div class="total-row" style="font-size:11px; color:#555; padding-left:8px;">
                            <span>Efectivo:</span>
                            <span>S/ {{ number_format($ingreso->monto_efectivo, 2) }}</span>
                        </div>
                    @endif
                    @if($ingreso->monto_yape)
                        <div class="total-row" style="font-size:11px; color:#555; padding-left:8px;">
                            <span>Yape:</span>
                            <span>S/ {{ number_format($ingreso->monto_yape, 2) }}</span>
                        </div>
                    @endif
                    @if($ingreso->monto_izipay)
                        <div class="total-row" style="font-size:11px; color:#555; padding-left:8px;">
                            <span>Izipay:</span>
                            <span>S/ {{ number_format($ingreso->monto_izipay, 2) }}</span>
                        </div>
                    @endif
                @endif
            </div>
        </div>

    </div>{{-- /.ticket --}}

    {{-- Action buttons (hidden on print) --}}
    <div class="ticket-actions">
        <a href="{{ route('ingresos.show', $ingreso) }}" class="btn btn-back">
            ← Volver
        </a>
        <button type="button" class="btn btn-print" onclick="window.print()">
            🖨 Imprimir
        </button>
    </div>

</body>
</html>
