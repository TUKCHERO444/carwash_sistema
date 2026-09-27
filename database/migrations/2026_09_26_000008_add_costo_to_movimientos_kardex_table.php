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
        Schema::table('movimientos_kardex', function (Blueprint $table) {
            $table->decimal('costo_unitario', 10, 2)->nullable()->after('stock_despues');
            $table->decimal('costo_total', 12, 2)->nullable()->after('costo_unitario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos_kardex', function (Blueprint $table) {
            $table->dropColumn(['costo_unitario', 'costo_total']);
        });
    }
};