<?php

namespace App\Services\Reportes;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Exportación PDF de reportes (barryvdh/laravel-dompdf).
 *
 * Renderiza las vistas bajo resources/views/reportes/pdf/ — tablas y
 * estadísticas, sin gráficos — con la información consolidada del rango
 * aplicado, y las devuelve como descarga A4 en orientación vertical.
 */
class ReportePdfService
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function descargar(string $vista, string $nombreArchivo, array $datos = []): Response
    {
        $pdf = Pdf::loadView($vista, $datos)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('isFontSubsettingEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($nombreArchivo);
    }
}
