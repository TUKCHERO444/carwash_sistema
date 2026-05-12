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
            $table->decimal('precio', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users');
        });

        // Assign existing rows to the first available user
        $firstUserId = DB::table('users')->value('id');
        if ($firstUserId) {
            DB::table('ingresos')->whereNull('user_id')->update(['user_id' => $firstUserId]);
        }

        Schema::table('ingresos', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['precio', 'total', 'user_id']);
        });
    }
};
