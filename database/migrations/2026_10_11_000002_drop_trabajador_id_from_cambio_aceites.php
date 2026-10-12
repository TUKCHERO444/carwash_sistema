<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina el remanente N:1 trabajador_id de cambio_aceites.
     * La asignación vive únicamente en cambio_aceite_trabajadores (N:N).
     * Idempotente: en BD frescas (base ya editada) no hace nada.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cambio_aceites') || ! Schema::hasColumn('cambio_aceites', 'trabajador_id')) {
            return;
        }

        Schema::table('cambio_aceites', function (Blueprint $table) {
            try {
                $table->dropForeign(['trabajador_id']);
            } catch (Throwable) {
            }
            $table->dropColumn('trabajador_id');
        });
    }

    /**
     * Restaura la estructura (nullable; los valores históricos no se recuperan).
     */
    public function down(): void
    {
        if (! Schema::hasTable('cambio_aceites') || Schema::hasColumn('cambio_aceites', 'trabajador_id')) {
            return;
        }

        Schema::table('cambio_aceites', function (Blueprint $table) {
            $table->foreignId('trabajador_id')->nullable()->after('cliente_id')->constrained('trabajadores')->nullOnDelete();
        });
    }
};
