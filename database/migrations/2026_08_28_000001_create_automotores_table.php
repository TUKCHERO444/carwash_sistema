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
        Schema::create('automotores', function (Blueprint $table) {
            $table->string('placa', 7)->primary();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->string('marca', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('serie', 100)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('motor', 100)->nullable();
            $table->timestamps();

            $table->index('cliente_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automotores');
    }
};
