<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo explícito identidad (users) <-> negocio (clientes).
     * Decisión D1: cardinalidad 1:1 → UNIQUE(user_id).
     */
    public function up(): void
    {
        Schema::create('customer_user_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('estado', 20)->default('ACTIVO');
            $table->string('rol', 30)->nullable();
            $table->timestamps();

            $table->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_user_links');
    }
};
