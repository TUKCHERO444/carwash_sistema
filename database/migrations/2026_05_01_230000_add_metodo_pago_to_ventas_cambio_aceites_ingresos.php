<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = function (Blueprint $table) {
            $table->enum('metodo_pago', ['efectivo', 'yape', 'izipay', 'mixto'])
                  ->default('efectivo')
                  ->after('total');
            $table->decimal('monto_efectivo', 10, 2)->nullable()->after('metodo_pago');
            $table->decimal('monto_yape',     10, 2)->nullable()->after('monto_efectivo');
            $table->decimal('monto_izipay',   10, 2)->nullable()->after('monto_yape');
        };

        Schema::table('ventas',         $columns);
        Schema::table('cambio_aceites', $columns);
        Schema::table('ingresos',       $columns);
    }

    public function down(): void
    {
        $drop = function (Blueprint $table) {
            $table->dropColumn(['metodo_pago', 'monto_efectivo', 'monto_yape', 'monto_izipay']);
        };

        Schema::table('ventas',         $drop);
        Schema::table('cambio_aceites', $drop);
        Schema::table('ingresos',       $drop);
    }
};
