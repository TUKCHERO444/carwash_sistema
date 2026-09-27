<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Cambia `movimientos_kardex.fuente` de ENUM a string(30) para admitir 'compra'.
 *
 * PostgreSQL no permite ALTER COLUMN ... TYPE con enum/string inline.
 * Usamos columna temporal + rename para compatibilidad.
 * SQLite: sin constraint CHECK extra (validación en app).
 */
return new class extends Migration
{
    public function up(): void
    {
        $isPostgres = DB::getDriverName() === 'pgsql';

        Schema::table('movimientos_kardex', function (Blueprint $table) {
            $table->string('fuente_new', 30)->nullable()->after('fuente');
        });

        DB::statement("UPDATE movimientos_kardex SET fuente_new = fuente");

        Schema::table('movimientos_kardex', function (Blueprint $table) {
            $table->dropColumn('fuente');
            $table->renameColumn('fuente_new', 'fuente');
            $table->string('fuente', 30)->nullable(false)->change();
        });

        if ($isPostgres) {
            DB::statement("ALTER TABLE movimientos_kardex ADD CONSTRAINT movimientos_kardex_fuente_check CHECK (fuente IN ('venta', 'cambio_aceite', 'inventario', 'compra', 'ajuste'))");
        }
    }

    public function down(): void
    {
        $isPostgres = DB::getDriverName() === 'pgsql';

        if ($isPostgres) {
            DB::statement("ALTER TABLE movimientos_kardex DROP CONSTRAINT IF EXISTS movimientos_kardex_fuente_check");
        }

        Schema::table('movimientos_kardex', function (Blueprint $table) {
            $table->string('fuente_old', 30)->nullable()->after('fuente');
        });

        DB::statement("UPDATE movimientos_kardex SET fuente_old = fuente");

        Schema::table('movimientos_kardex', function (Blueprint $table) {
            $table->dropColumn('fuente');
            $table->renameColumn('fuente_old', 'fuente');
            $table->string('fuente', 30)->nullable(false)->change();
        });

        if ($isPostgres) {
            DB::statement("ALTER TABLE movimientos_kardex ADD CONSTRAINT movimientos_kardex_fuente_check CHECK (fuente IN ('venta', 'cambio_aceite', 'inventario'))");
        }
    }
};
