<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Amplía los medios de pago con los que se cancela una compra a proveedor.
 *
 * El enum original solo contemplaba efectivo y yape, pensados para el cobro al
 * cliente final. El pago a un proveedor Lima se hace por transferencia bancaria
 * casi siempre, y el módulo de compras (spec compras-ingreso-mercaderia, requisito 46)
 * necesita registrarlo como egreso de caja.
 *
 * Nota: PostgreSQL no permite ALTER COLUMN ... TYPE con CHECK inline.
 * Usamos string + constraint separada para compatibilidad.
 * SQLite no soporta ADD CONSTRAINT; la validación se hace en la capa de aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        $isPostgres = DB::getDriverName() === 'pgsql';

        Schema::table('egresos_caja', function (Blueprint $table) {
            $table->string('tipo_pago_new', 20)->nullable()->after('tipo_pago');
        });

        DB::statement("UPDATE egresos_caja SET tipo_pago_new = tipo_pago");

        Schema::table('egresos_caja', function (Blueprint $table) {
            $table->dropColumn('tipo_pago');
            $table->renameColumn('tipo_pago_new', 'tipo_pago');
            $table->string('tipo_pago', 20)->nullable(false)->change();
        });

        if ($isPostgres) {
            DB::statement("ALTER TABLE egresos_caja ADD CONSTRAINT egresos_caja_tipo_pago_check CHECK (tipo_pago IN ('efectivo', 'yape', 'transferencia', 'tarjeta'))");
        }
    }

    public function down(): void
    {
        $isPostgres = DB::getDriverName() === 'pgsql';

        if ($isPostgres) {
            DB::statement("ALTER TABLE egresos_caja DROP CONSTRAINT IF EXISTS egresos_caja_tipo_pago_check");
        }

        Schema::table('egresos_caja', function (Blueprint $table) {
            $table->string('tipo_pago_old', 20)->nullable()->after('tipo_pago');
        });

        DB::statement("UPDATE egresos_caja SET tipo_pago_old = tipo_pago");

        Schema::table('egresos_caja', function (Blueprint $table) {
            $table->dropColumn('tipo_pago');
            $table->renameColumn('tipo_pago_old', 'tipo_pago');
            $table->string('tipo_pago', 20)->nullable(false)->change();
        });

        if ($isPostgres) {
            DB::statement("ALTER TABLE egresos_caja ADD CONSTRAINT egresos_caja_tipo_pago_check CHECK (tipo_pago IN ('efectivo', 'yape'))");
        }
    }
};
