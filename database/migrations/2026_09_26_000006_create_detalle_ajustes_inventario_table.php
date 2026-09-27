<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Líneas de detalle de un ajuste de inventario.
     *
     * Cada línea registra el producto, la cantidad (firmada: + entrada, - salida),
     * y el stock antes/después para trazabilidad completa.
     */
    public function up(): void
    {
        Schema::create('detalle_ajustes_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ajuste_id')
                ->constrained('ajustes_inventario')->onDelete('cascade');
            $table->foreignId('producto_id')
                ->constrained('productos')->onDelete('restrict');
            $table->integer('cantidad'); // firmado: + entrada, - salida
            $table->integer('stock_antes');
            $table->integer('stock_despues');
            $table->timestamps();

            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_ajustes_inventario');
    }
};
