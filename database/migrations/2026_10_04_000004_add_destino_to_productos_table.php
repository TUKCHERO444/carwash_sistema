<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clasifica el destino del producto: comercialización/operación facturable
 * (`comercial`, valor histórico) o insumo de consumo interno
 * (`consumo_interno`). La clasificación es informativa y de control: no
 * bloquea ventas ni cambios de aceite (un producto puede tener ambos usos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('destino', 20)->default('comercial')->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('destino');
        });
    }
};
