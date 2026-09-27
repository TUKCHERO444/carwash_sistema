<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cabecera de compras a proveedores.
 *
 * Una compra nace en estado `borrador` y no mueve inventario. Al recibirla pasa a
 * `recibida`, y es recién entonces cuando genera la entrada de Kardex, el egreso de
 * caja y la actualización del costo de los productos (spec compras-ingreso-mercaderia).
 *
 * `caja_id` y `egreso_caja_id` quedan nulos mientras la compra es borrador, y se
 * llenan en la recepción para dejar trazabilidad del cobro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            // Nullable mientras la compra es borrador: el correlativo se asigna al
            // recibirla, dentro de la transacción de recepción (requisito 31). El
            // índice único admite varios nulos en MySQL y SQLite, así que los
            // borradores coexistirán sin numerar.
            $table->string('correlativo', 20)->nullable()->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores')->onDelete('restrict');
            $table->date('fecha');
            $table->string('tipo_documento', 20)->nullable();
            $table->string('numero_documento', 50)->nullable();
            $table->enum('estado', ['borrador', 'recibida', 'anulada'])->default('borrador');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('caja_id')->nullable()->constrained('cajas')->onDelete('restrict');
            $table->foreignId('egreso_caja_id')->nullable()->constrained('egresos_caja')->onDelete('restrict');
            $table->timestamp('fecha_recepcion')->nullable();
            $table->timestamps();

            $table->index('estado');
            $table->index('fecha');
            $table->index('proveedor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
