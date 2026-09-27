<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cambia `movimientos_kardex.fuente` de ENUM a string(30) para admitir 'compra'.
 *
 * El spike en SQLite in-memory confirmó que `->change()` funciona y permite insertar
 * el nuevo valor. En MySQL el cambio también es seguro (ALTER TABLE MODIFY COLUMN).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_kardex', function (Blueprint $table) {
            $table->string('fuente', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_kardex', function (Blueprint $table) {
            $table->enum('fuente', ['venta', 'cambio_aceite', 'inventario'])->change();
        });
    }
};
