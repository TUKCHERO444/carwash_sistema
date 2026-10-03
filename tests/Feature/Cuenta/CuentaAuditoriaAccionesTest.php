<?php

namespace Tests\Feature\Cuenta;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La columna registros_auditoria.accion es varchar(30) en MySQL
 * (SQLite no valida longitud y enmascara el error con un 500).
 * Este guardián falla si algún literal de acción la excede.
 */
class CuentaAuditoriaAccionesTest extends TestCase
{
    public function test_acciones_caben_en_la_columna(): void
    {
        $longitud = $this->longitudAccion();

        $archivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        $violaciones = [];

        foreach ($archivos as $archivo) {
            if ($archivo->getExtension() !== 'php') {
                continue;
            }

            $contenido = file_get_contents($archivo->getPathname());

            if (preg_match_all("/registrar(?:Sesion)?\\(\\$[a-zA-Z_]+,\\s*'([^']+)'/", $contenido, $coincidencias)) {
                foreach ($coincidencias[1] as $accion) {
                    if (mb_strlen($accion) > $longitud) {
                        $violaciones[] = $archivo->getPathname().': '.$accion;
                    }
                }
            }
        }

        $this->assertSame([], $violaciones, 'Acciones que exceden registros_auditoria.accion');
    }

    private function longitudAccion(): int
    {
        try {
            $tipo = Schema::getColumnType('registros_auditoria', 'accion');
        } catch (\Throwable) {
            return 30;
        }

        if (preg_match('/\((\d+)\)/', (string) $tipo, $m)) {
            return (int) $m[1];
        }

        return 30;
    }
}
