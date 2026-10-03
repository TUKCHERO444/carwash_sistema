<?php

namespace Tests\Feature\OrdenAtencion;

use App\Services\TiempoAtencionService;
use Carbon\Carbon;
use Tests\TestCase;

// Feature: orden-atencion, Property 1: minutosEntre equivale a los minutos
// completos entre inicio y fin y nunca es negativo (100 iteraciones).
// Feature: orden-atencion, Property 2: etiqueta() solo usa minutos/horas
// y de ella se recuperan los mismos minutos (100 iteraciones).
class TiempoAtencionPropertyTest extends TestCase
{
    private TiempoAtencionService $tiempos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tiempos = app(TiempoAtencionService::class);
    }

    public function test_minutos_entre_nunca_negativo_y_es_diferencia_completa(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $base = Carbon::now()->subDays(random_int(0, 30));
            $inicio = $base->copy()->addMinutes(random_int(0, 5000));
            // Fin aleatorio antes, igual o después del inicio.
            $fin = $base->copy()->addMinutes(random_int(0, 5000));

            $esperado = $fin->lessThan($inicio) ? 0 : (int) $inicio->diffInMinutes($fin);

            $this->assertSame($esperado, $this->tiempos->minutosEntre($inicio, $fin));
            $this->assertGreaterThanOrEqual(0, $this->tiempos->minutosEntre($inicio, $fin));
        }
    }

    public function test_etiqueta_solo_minutos_horas_y_recupera_minutos(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $minutos = random_int(0, 10000);

            $etiqueta = $this->tiempos->etiqueta($minutos);

            // Solo minutos/horas, sin segundos ni decimales.
            $this->assertMatchesRegularExpression('/^(\d+ h( \d+ min)?|\d+ min)$/', $etiqueta);
            $this->assertStringNotContainsString('seg', $etiqueta);

            // Round-trip: de la etiqueta se recuperan los mismos minutos.
            $this->assertSame($minutos, $this->minutosDesdeEtiqueta($etiqueta));
        }

        // Casos borde exactos.
        $this->assertSame('0 min', $this->tiempos->etiqueta(0));
        $this->assertSame('59 min', $this->tiempos->etiqueta(59));
        $this->assertSame('1 h', $this->tiempos->etiqueta(60));
        $this->assertSame('1 h 1 min', $this->tiempos->etiqueta(61));
        $this->assertNull($this->tiempos->etiqueta(null));
    }

    private function minutosDesdeEtiqueta(string $etiqueta): int
    {
        if (preg_match('/^(\d+) h (\d+) min$/', $etiqueta, $m)) {
            return ((int) $m[1]) * 60 + ((int) $m[2]);
        }

        if (preg_match('/^(\d+) h$/', $etiqueta, $m)) {
            return ((int) $m[1]) * 60;
        }

        preg_match('/^(\d+) min$/', $etiqueta, $m);

        return (int) $m[1];
    }
}
