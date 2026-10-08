<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte `ajustes_inventario.tipo` de ENUM nativo a string(20).
 *
 * El ENUM congelaba los tipos en DDL (agregar `consumo_interno` exigiría
 * ALTER TYPE por driver). Con string, los tipos válidos viven en
 * `AjusteInventario::TIPOS` y la validación del controlador, igual que las
 * fuentes de Kardex viven en `KardexService::FUENTES`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ajustes_inventario', function (Blueprint $table) {
            $table->dropIndex(['tipo']);
            $table->string('tipo_new', 20)->nullable()->after('correlativo');
        });

        DB::statement('UPDATE ajustes_inventario SET tipo_new = tipo');

        Schema::table('ajustes_inventario', function (Blueprint $table) {
            $table->dropColumn('tipo');
            $table->renameColumn('tipo_new', 'tipo');
        });

        Schema::table('ajustes_inventario', function (Blueprint $table) {
            $table->string('tipo', 20)->nullable(false)->change();
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('ajustes_inventario', function (Blueprint $table) {
            $table->string('tipo_old', 20)->nullable()->after('correlativo');
        });

        DB::statement('UPDATE ajustes_inventario SET tipo_old = tipo');

        Schema::table('ajustes_inventario', function (Blueprint $table) {
            $table->dropColumn('tipo');
            $table->renameColumn('tipo_old', 'tipo');
        });
    }
};
