<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normalizar valores nulos existentes para evitar errores al hacer NOT NULL
        DB::table('compras')->whereNull('tipo_documento')->update(['tipo_documento' => 'Otros']);
        DB::table('compras')->whereNull('numero_documento')->update(['numero_documento' => 'S/N']);

        Schema::table('compras', function (Blueprint $table) {
            $table->string('tipo_documento', 20)->nullable(false)->change();
            $table->string('numero_documento', 50)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->string('tipo_documento', 20)->nullable()->change();
            $table->string('numero_documento', 50)->nullable()->change();
        });
    }
};
