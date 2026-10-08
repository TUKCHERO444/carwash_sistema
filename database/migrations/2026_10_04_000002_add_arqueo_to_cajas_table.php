<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega los campos del arqueo de cierre a `cajas`.
 *
 * El arqueo es parte del cierre: efectivo esperado (calculado), efectivo
 * contado (ingresado), diferencia (calculada), responsable, fecha y
 * observación. Las cajas cerradas con el régimen anterior quedan con nulos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->decimal('efectivo_esperado', 10, 2)->nullable()->after('fecha_cierre');
            $table->decimal('efectivo_contado', 10, 2)->nullable()->after('efectivo_esperado');
            $table->decimal('diferencia', 10, 2)->nullable()->after('efectivo_contado');
            $table->foreignId('arqueo_user_id')->nullable()->after('diferencia')->constrained('users')->onDelete('restrict');
            $table->dateTime('fecha_arqueo')->nullable()->after('arqueo_user_id');
            $table->string('observacion_arqueo', 500)->nullable()->after('fecha_arqueo');
        });
    }

    public function down(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->dropForeign(['arqueo_user_id']);
            $table->dropColumn([
                'efectivo_esperado',
                'efectivo_contado',
                'diferencia',
                'arqueo_user_id',
                'fecha_arqueo',
                'observacion_arqueo',
            ]);
        });
    }
};
