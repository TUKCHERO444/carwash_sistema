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
        Schema::table('ingresos', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'confirmado'])
                  ->default('pendiente')
                  ->after('fecha');
        });

        // Todos los ingresos existentes se marcan como confirmados para preservar datos históricos
        DB::table('ingresos')->update(['estado' => 'confirmado']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
