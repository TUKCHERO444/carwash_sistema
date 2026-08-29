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
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->char('dni', 8)->nullable()->unique()->after('id');
            $table->string('nombre', 50)->change();
            $table->string('apellido_paterno', 50)->nullable()->after('nombre');
            $table->string('apellido_materno', 50)->nullable()->after('apellido_paterno');
            $table->text('foto')->nullable()->after('apellido_materno');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->dropUnique(['dni']);
            $table->dropColumn(['dni', 'apellido_paterno', 'apellido_materno', 'foto']);
            $table->string('nombre', 100)->change();
        });
    }
};
