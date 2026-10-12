<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->string('vehiculo_id', 7)->nullable()->after('cliente_id');
            $table->foreign('vehiculo_id')->references('placa')->on('vehiculos')->onDelete('set null');
        });

        Schema::table('cambio_aceites', function (Blueprint $table) {
            $table->string('vehiculo_id', 7)->nullable()->after('cliente_id');
            $table->foreign('vehiculo_id')->references('placa')->on('vehiculos')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropForeign(['vehiculo_id']);
            $table->dropColumn('vehiculo_id');
        });

        Schema::table('cambio_aceites', function (Blueprint $table) {
            $table->dropForeign(['vehiculo_id']);
            $table->dropColumn('vehiculo_id');
        });
    }
};
