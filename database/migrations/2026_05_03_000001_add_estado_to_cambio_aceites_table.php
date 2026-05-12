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
        Schema::table('cambio_aceites', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'confirmado'])
                  ->default('pendiente')
                  ->after('fecha');
        });

        // Todos los cambios de aceite existentes se marcan como confirmados
        // para preservar la integridad de datos históricos
        DB::table('cambio_aceites')->update(['estado' => 'confirmado']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cambio_aceites', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
