<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa el conjunto de fuentes de `movimientos_kardex` en la base de datos.
 *
 * La migración que amplió `fuente` de enum a string dejó en PostgreSQL un CHECK
 * con una lista incompleta ('venta', 'cambio_aceite', 'inventario', 'compra',
 * 'ajuste'): include un 'ajuste' que ningún flujo escribe y excluye las cinco
 * fuentes reales de ajuste de inventario, entre ellas 'ajuste_negativo', que sí
 * produce `anular compra recibida`. En PostgreSQL eso se traduce en un error de
 * constraint al registrar movimientos legítimos.
 *
 * Aquí el CHECK pasa a la lista completa de fuentes y se indexa `fuente`, que se
 * filtra en el listado y hasta ahora no lo tenía (RNF-02 de
 * docs/feature-kardex-auditoria.md).
 *
 * La lista está congelada a propósito y no se lee de KardexService: una migración
 * ya aplicada no debe cambiar de significado si la app evoluciona. Al agregar
 * una fuente hay que actualizar las dos, y el test
 * `test_check_de_postgres_lista_las_mismas_fuentes_que_el_catalogo` de
 * KardexTest falla si se olvidan.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const FUENTES_PERMITIDAS = [
        'venta',
        'compra',
        'cambio_aceite',
        'inventario',
        'ajuste_positivo',
        'ajuste_negativo',
        'merma',
        'daño',
        'conteo_fisico',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $permitidas = self::FUENTES_PERMITIDAS;

            $huerfanas = DB::table('movimientos_kardex')
                ->whereNotIn('fuente', $permitidas)
                ->distinct()
                ->pluck('fuente');

            if ($huerfanas->isNotEmpty()) {
                throw new RuntimeException(
                    'Hay movimientos de Kardex con fuentes fuera del catálogo conocido: '
                    .$huerfanas->implode(', ').'. Corríglos antes de aplicar esta migración.'
                );
            }

            DB::statement('ALTER TABLE movimientos_kardex DROP CONSTRAINT IF EXISTS movimientos_kardex_fuente_check');

            $in = implode(', ', array_map(fn (string $f) => "'".str_replace("'", "''", $f)."'", $permitidas));
            DB::statement("ALTER TABLE movimientos_kardex ADD CONSTRAINT movimientos_kardex_fuente_check CHECK (fuente IN ({$in}))");
        }

        Schema::table('movimientos_kardex', function (Blueprint $table) {
            $table->index('fuente');
        });
    }

    /**
     * Deja la columna sin CHECK: la lista anterior estaba incompleta y
     * reintroducirla volvería a bloquear 'ajuste_negativo' y las demás fuentes
     * de ajuste. El índice sí se revierte.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE movimientos_kardex DROP CONSTRAINT IF EXISTS movimientos_kardex_fuente_check');
        }

        Schema::table('movimientos_kardex', function (Blueprint $table) {
            $table->dropIndex(['fuente']);
        });
    }
};
