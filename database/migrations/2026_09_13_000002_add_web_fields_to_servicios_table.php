<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Añade los campos de publicación web al servicio (activo para el
     * toggle del CRUD, orden e ícono para la página pública). Feature: servicios-web.
     */
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('precio');
            $table->unsignedInteger('orden')->default(0)->after('activo');
            $table->string('icono', 30)->nullable()->default('sparkles')->after('orden');
            $table->string('imagen')->nullable()->after('icono');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn(['imagen', 'icono', 'orden', 'activo']);
        });
    }
};