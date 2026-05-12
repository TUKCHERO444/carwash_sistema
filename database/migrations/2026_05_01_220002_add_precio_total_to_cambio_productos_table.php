<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cambio_productos', function (Blueprint $table) {
            $table->decimal('precio', 10, 2)->after('cantidad');
            $table->decimal('total', 10, 2)->after('precio');
        });
    }

    public function down(): void
    {
        Schema::table('cambio_productos', function (Blueprint $table) {
            $table->dropColumn(['precio', 'total']);
        });
    }
};
