<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clasifica los egresos de caja: categoría de gasto + trabajador opcional.
 *
 * La clasificación es solo informativa: no altera el impacto financiero del
 * egreso. Los egresos históricos (pagos de compra, devoluciones, notas de
 * crédito) quedan con nulos y se muestran como "Sin clasificar".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('egresos_caja', function (Blueprint $table) {
            $table->string('categoria', 30)->nullable()->after('tipo_pago');
            $table->foreignId('trabajador_id')->nullable()->after('categoria')->constrained('trabajadores')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('egresos_caja', function (Blueprint $table) {
            $table->dropForeign(['trabajador_id']);
            $table->dropColumn(['categoria', 'trabajador_id']);
        });
    }
};
