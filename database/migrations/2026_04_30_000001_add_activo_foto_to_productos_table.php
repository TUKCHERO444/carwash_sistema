<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Añade los campos activo (tinyint, default 1) y foto (string, nullable)
     * a la tabla productos existente sin modificar los registros actuales.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->tinyInteger('activo')->default(1)->after('inventario');
            $table->string('foto')->nullable()->after('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['activo', 'foto']);
        });
    }
};
