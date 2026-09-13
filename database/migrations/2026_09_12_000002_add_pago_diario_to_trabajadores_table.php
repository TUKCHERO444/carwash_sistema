<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el jornal diario (pago_diario) a los trabajadores.
     * Guardado en soles, default 50 (ver AuthSeeder/trabajadores de demo).
     */
    public function up(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->decimal('pago_diario', 8, 2)->nullable()->default(50)->after('foto');
        });
    }

    /**
     * Revierte el cambio.
     */
    public function down(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->dropColumn('pago_diario');
        });
    }
};
