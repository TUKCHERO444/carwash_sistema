<?php

namespace App\Services\Reportes;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportación CSV sin dependencias externas.
 *
 * Formato para Excel es-PE: BOM UTF-8 y separador ';'. Las celdas con ';',
 * comillas o saltos de línea se citan; los decimales conservan su punto.
 */
class ReporteCsvService
{
    /** @var string BOM UTF-8 requerido por Excel para leer acentos. */
    public const BOM = "\xEF\xBB\xBF";

    /**
     * @param  array<int, string>  $cabeceras
     * @param  array<int, mixed>|Collection<int, mixed>  $filas
     */
    public function descargar(string $nombreArchivo, array $cabeceras, array|Collection $filas): StreamedResponse
    {
        $stream = fopen('php://output', 'w');

        $response = new StreamedResponse(function () use ($stream, $cabeceras, $filas) {
            fwrite($stream, self::BOM);
            fputcsv($stream, $cabeceras, ';');

            foreach ($filas as $fila) {
                if ($fila instanceof \ArrayAccess || is_array($fila)) {
                    fputcsv($stream, array_values($this->serializarFila($fila)), ';');
                }
            }

            fclose($stream);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$nombreArchivo.'"');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /**
     * @param  array<int|string, mixed>  $fila
     * @return array<int, string>
     */
    private function serializarFila(array $fila): array
    {
        $valores = [];
        foreach ($fila as $valor) {
            if (is_bool($valor)) {
                $valores[] = $valor ? '1' : '0';
            } elseif ($valor instanceof \DateTimeInterface) {
                $valores[] = $valor->format('d/m/Y H:i');
            } elseif (is_object($valor) && method_exists($valor, '__toString')) {
                $valores[] = (string) $valor;
            } elseif ($valor === null) {
                $valores[] = '';
            } else {
                $valores[] = (string) $valor;
            }
        }

        return $valores;
    }
}
