<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea las tablas de devoluciones de venta y amplía el CHECK de
 * `movimientos_kardex.fuente` con la fuente 'devolucion'.
 *
 * La venta original nunca se modifica: la disponibilidad por línea se deriva
 * como (vendida − ya devuelta) a partir de `detalle_devoluciones_venta`.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const FUENTES_PERMITIDAS = [
        'venta',
        'compra',
        'cambio_aceite',
        'inventario',
        'ajuste_positivo',
        'ajuste_negativo',
        'merma',
        'daño',
        'conteo_fisico',
        'devolucion',
    ];

    public function up(): void
    {
        Schema::create('devoluciones_venta', function (Blueprint $table) {
            $table->id();
            $table->string('correlativo')->unique();
            $table->foreignId('venta_id')->constrained('ventas')->onDelete('restrict');
            $table->foreignId('venta_nueva_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete();
            $table->enum('tipo', ['devolucion', 'cambio'])->default('devolucion');
            $table->string('motivo', 500);
            $table->decimal('total_devuelto', 10, 2)->default(0);
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });

        Schema::create('detalle_devoluciones_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones_venta')->onDelete('cascade');
            $table->foreignId('detalle_venta_id')->constrained('detalle_ventas')->onDelete('restrict');
            $table->foreignId('producto_id')->constrained('productos')->onDelete('restrict');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE movimientos_kardex DROP CONSTRAINT IF EXISTS movimientos_kardex_fuente_check');

            $in = implode(', ', array_map(fn (string $f) => "'".str_replace("'", "''", $f)."'", self::FUENTES_PERMITIDAS));
            DB::statement("ALTER TABLE movimientos_kardex ADD CONSTRAINT movimientos_kardex_fuente_check CHECK (fuente IN ({$in}))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE movimientos_kardex DROP CONSTRAINT IF EXISTS movimientos_kardex_fuente_check');
        }

        Schema::dropIfExists('detalle_devoluciones_venta');
        Schema::dropIfExists('devoluciones_venta');
    }
};
