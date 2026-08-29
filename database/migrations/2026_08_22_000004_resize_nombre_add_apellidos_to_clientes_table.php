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
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('nombre', 50)->nullable()->change();
            $table->string('apellido_paterno', 50)->nullable()->after('nombre');
            $table->string('apellido_materno', 50)->nullable()->after('apellido_paterno');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['apellido_paterno', 'apellido_materno']);
            $table->string('nombre', 100)->nullable()->change();
        });
    }
};
