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
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('caja_id')->nullable()->after('user_id')
                  ->constrained('cajas')->onDelete('set null');
        });

        Schema::table('cambio_aceites', function (Blueprint $table) {
            $table->foreignId('caja_id')->nullable()->after('user_id')
                  ->constrained('cajas')->onDelete('set null');
        });

        Schema::table('ingresos', function (Blueprint $table) {
            $table->foreignId('caja_id')->nullable()->after('user_id')
                  ->constrained('cajas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['caja_id']);
            $table->dropColumn('caja_id');
        });

        Schema::table('cambio_aceites', function (Blueprint $table) {
            $table->dropForeign(['caja_id']);
            $table->dropColumn('caja_id');
        });

        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropForeign(['caja_id']);
            $table->dropColumn('caja_id');
        });
    }
};
