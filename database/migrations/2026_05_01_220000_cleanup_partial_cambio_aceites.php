<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Temporary cleanup migration - removes partially-added columns from a failed migration attempt
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cambio_aceites', 'precio')) {
            // Check if the FK constraint exists before trying to drop it
            $fkExists = collect(\DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'cambio_aceites'
                  AND CONSTRAINT_NAME = 'cambio_aceites_user_id_foreign'
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            "))->isNotEmpty();

            Schema::table('cambio_aceites', function (Blueprint $table) use ($fkExists) {
                if ($fkExists) {
                    $table->dropForeign(['user_id']);
                }
                $columns = [];
                foreach (['precio', 'total', 'descripcion', 'user_id'] as $col) {
                    if (Schema::hasColumn('cambio_aceites', $col)) {
                        $columns[] = $col;
                    }
                }
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }

    public function down(): void
    {
        // Nothing to reverse
    }
};
