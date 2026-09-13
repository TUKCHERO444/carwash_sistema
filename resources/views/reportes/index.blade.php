@extends('layouts.app')

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-primary">Reportes</h1>
            <p class="mt-1 text-sm text-secondary">Consulta y exportación de la información operativa.</p>
        </div>
    </div>

    {{-- Tarjetas --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        @php
            $reportes = [
                [
                    'ruta' => 'reportes.ingresos',
                    'titulo' => 'Ingresos',
                    'descripcion' => 'Consolidado por día, método de pago y días de mayor ingreso.',
                    'icono' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                ],
                [
                    'ruta' => 'reportes.ventas',
                    'titulo' => 'Ventas',
                    'descripcion' => 'KPIs, agregados por usuario y método de pago, detalle con filtros.',
                    'icono' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
                ],
                [
                    'ruta' => 'reportes.lavados',
                    'titulo' => 'Lavados',
                    'descripcion' => 'Lavados confirmados por vehículo, servicio y trabajador.',
                    'icono' => 'M4 7h16M4 7a2 2 0 012-2h12a2 2 0 012 2m0 0v10a2 2 0 01-2 2H6a2 2 0 01-2-2V7zm6 4h8m-8 4h5',
                ],
                [
                    'ruta' => 'reportes.cambioAceite',
                    'titulo' => 'Cambio de aceite',
                    'descripcion' => 'Cambios confirmados agregados por producto y trabajador.',
                    'icono' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z',
                ],
                [
                    'ruta' => 'reportes.inventario',
                    'titulo' => 'Inventario',
                    'descripcion' => 'Top de productos, stock actual y resúmenes por categoría/marca.',
                    'icono' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                ],
                [
                    'ruta' => 'reportes.clientes',
                    'titulo' => 'Clientes y automotores',
                    'descripcion' => 'Gasto por cliente, vehículos más atendidos y frecuencia de visita.',
                    'icono' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
                ],
                [
                    'ruta' => 'reportes.caja',
                    'titulo' => 'Caja',
                    'descripcion' => 'Cajas cerradas, balance por jornada y egresos del período.',
                    'icono' => 'M3 10h18M7 15h2m4 0h4M5 4h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z',
                ],
                [
                    'ruta' => 'reportes.personal',
                    'titulo' => 'Personal',
                    'descripcion' => 'Asistencias del mes, porcentaje y resumen de pago por jornal.',
                    'icono' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                ],
                [
                    'ruta' => 'reportes.kardex',
                    'titulo' => 'Kardex',
                    'descripcion' => 'Movimientos de stock por producto: entradas, salidas y saldo neto.',
                    'icono' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
                ],
            ];
        @endphp

        @foreach($reportes as $reporte)
            <a href="{{ route($reporte['ruta']) }}"
               class="bg-surface border border-main rounded-xl p-5 hover:border-blue-500 dark:hover:border-blue-500 transition-colors group">
                <div class="flex items-start gap-4">
                    <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $reporte['icono'] }}"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-primary group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $reporte['titulo'] }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ $reporte['descripcion'] }}</p>
                    </div>
                </div>
            </a>
        @endforeach

    </div>

</div>
@endsection