<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cambio_aceites', function (Blueprint $table) {
            $table->decimal('precio', 10, 2)->default(0)->after('fecha');
            $table->decimal('total', 10, 2)->default(0)->after('precio');
            $table->text('descripcion')->nullable()->after('total');
            // Add as nullable first to handle existing rows, then add FK constraint
            $table->unsignedBigInteger('user_id')->nullable()->after('descripcion');
        });

        // Backfill existing rows with the first user
        \DB::table('cambio_aceites')->whereNull('user_id')->update(['user_id' => 1]);

        Schema::table('cambio_aceites', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('cambio_aceites', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['precio', 'total', 'descripcion', 'user_id']);
        });
    }
};
