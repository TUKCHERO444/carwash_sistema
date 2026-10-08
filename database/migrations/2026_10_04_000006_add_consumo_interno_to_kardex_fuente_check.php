<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía el CHECK de `movimientos_kardex.fuente` con `consumo_interno`.
 *
 * La lista congelada de `2026_09_27_090000` se actualiza en su archivo para
 * que el test de paridad con `KardexService::FUENTES` siga pasando; esta
 * migración aplica el cambio en bases donde aquella ya corrió.
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
        'devolucion',
        'consumo_interno',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE movimientos_kardex DROP CONSTRAINT IF EXISTS movimientos_kardex_fuente_check');

            $in = implode(', ', array_map(fn (string $f) => "'".str_replace("'", "''", $f)."'", self::FUENTES_PERMITIDAS));
            DB::statement("ALTER TABLE movimientos_kardex ADD CONSTRAINT movimientos_kardex_fuente_check CHECK (fuente IN ({$in}))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE movimientos_kardex DROP CONSTRAINT IF EXISTS movimientos_kardex_fuente_check');
        }
    }
};
