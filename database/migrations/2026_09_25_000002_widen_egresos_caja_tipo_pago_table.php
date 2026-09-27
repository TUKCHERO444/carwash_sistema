<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía los medios de pago con los que se cancela una compra a proveedor.
 *
 * El enum original solo contemplaba efectivo y yape, pensados para el cobro al
 * cliente final. El pago a un proveedor Lima se hace por transferencia bancaria
 * casi siempre, y el módulo de compras (spec compras-ingreso-mercaderia, requisito 46)
 * necesita registrarlo como egreso de caja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('egresos_caja', function (Blueprint $table) {
            $table->enum('tipo_pago', ['efectivo', 'yape', 'transferencia', 'tarjeta'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('egresos_caja', function (Blueprint $table) {
            $table->enum('tipo_pago', ['efectivo', 'yape'])->change();
        });
    }
};
