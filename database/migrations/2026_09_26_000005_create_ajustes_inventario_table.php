<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cabecera de ajustes de inventario.
     *
     * Un ajuste es una operación formal de corrección de existencias (reposición,
     * merma, daño, conteo físico) que genera movimiento en Kardex y actualiza
     * stock/inventario del producto.
     */
    public function up(): void
    {
        Schema::create('ajustes_inventario', function (Blueprint $table) {
            $table->id();
            $table->string('correlativo', 20)->unique(); // AJU-0001
            $table->enum('tipo', [
                'positivo', 'negativo', 'merma', 'daño', 'conteo_fisico',
            ]);
            $table->text('motivo')->nullable(); // obligatorio para merma/daño/conteo
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index('tipo');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_inventario');
    }
};
