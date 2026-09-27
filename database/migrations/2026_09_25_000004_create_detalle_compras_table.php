<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de detalle de una compra a proveedor.
 *
 * `costo_unitario` es el costo que se registró al comprar. Al recibir la compra pasa a
 * `productos.precio_compra`, salvo si el producto tiene costo 0 (costo desconocido), en
 * cuyo caso conserva el que tuviera para no perder información.
 *
 * El subtotal se recalcula siempre desde las líneas y nunca se toma del formulario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->onDelete('cascade');
            $table->foreignId('producto_id')->constrained('productos')->onDelete('restrict');
            $table->integer('cantidad')->default(1);
            $table->decimal('costo_unitario', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->timestamps();

            // Una misma compra no puede repetir el producto: evita doble conteo de
            // existencias al recibir y mantiene unívoco el costo por línea.
            $table->unique(['compra_id', 'producto_id']);
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_compras');
    }
};
