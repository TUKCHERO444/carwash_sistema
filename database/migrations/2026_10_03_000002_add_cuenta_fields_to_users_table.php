<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos de cuenta pública sobre users (aditivo, sin romper panel operativo).
     * Decisión D1: vínculo 1:1 vía customer_user_links (UNIQUE user_id).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 30)->default('ACTIVE')->after('activo');
            $table->timestamp('password_changed_at')->nullable()->after('password');
            $table->unsignedInteger('failed_attempts')->default(0)->after('password_changed_at');
            $table->timestamp('locked_until')->nullable()->after('failed_attempts');
            $table->timestamp('last_login_at')->nullable()->after('locked_until');
            $table->index('status');
        });

        // Backfill: cuentas desactivadas (activo=false) quedan SUSPENDED.
        DB::table('users')->where('activo', false)->update(['status' => 'SUSPENDED']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'password_changed_at',
                'failed_attempts',
                'locked_until',
                'last_login_at',
            ]);
        });
    }
};
