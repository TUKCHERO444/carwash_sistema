<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla proveedores: las empresas que venden productos al sistema.
     * El ruc es la clave natural de 11 dígitos (char) y único.
     * estado es char(1) y solo almacena un número (1 activo, 0 inactivo).
     */
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->char('ruc', 11)->unique();
            $table->string('razon_social', 150);
            $table->string('direccion', 200)->nullable();
            $table->string('estado_tributario', 100)->nullable();
            $table->string('condicion', 100)->nullable();
            $table->char('estado', 1)->default('1');
            $table->timestamps();

            $table->index('razon_social');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
