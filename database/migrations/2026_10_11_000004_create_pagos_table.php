<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla polimórfica de líneas de pago, migra los datos
     * existentes (1 línea por pago simple, hasta 3 si era mixto) y
     * elimina las columnas repetidas de los 3 tickets.
     * Idempotente: si las columnas antiguas ya no existen, solo
     * asegura la existencia de la tabla.
     */
    public function up(): void
    {
        if (! Schema::hasTable('pagos')) {
            Schema::create('pagos', function (Blueprint $table) {
                $table->id();
                $table->string('pagable_type', 100);
                $table->unsignedBigInteger('pagable_id');
                $table->foreignId('metodo_pago_id')->constrained('metodos_pago')->onDelete('restrict');
                $table->decimal('monto', 10, 2);
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('caja_id')->nullable()->constrained('cajas')->onDelete('set null');
                $table->timestamps();

                $table->index(['pagable_type', 'pagable_id']);
            });
        }

        $this->asegurarMetodosBase();
        $this->migrarDatos();

        foreach (['ventas', 'lavados', 'cambio_aceites'] as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'metodo_pago')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                try {
                    $table->dropColumn(['metodo_pago', 'monto_efectivo', 'monto_yape', 'monto_izipay']);
                } catch (Throwable) {
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['ventas', 'lavados', 'cambio_aceites'] as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'metodo_pago')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('metodo_pago', 20)->nullable()->after('total');
                $table->decimal('monto_efectivo', 10, 2)->nullable()->after('metodo_pago');
                $table->decimal('monto_yape', 10, 2)->nullable()->after('monto_efectivo');
                $table->decimal('monto_izipay', 10, 2)->nullable()->after('monto_yape');
            });
        }

        Schema::dropIfExists('pagos');
    }

    /**
     * @return array<string, int> codigo => id
     */
    private function asegurarMetodosBase(): array
    {
        $base = [
            'efectivo' => ['Efectivo', true, 1],
            'yape' => ['Yape', false, 2],
            'izipay' => ['Izipay', false, 3],
        ];

        $ids = [];
        foreach ($base as $codigo => [$nombre, $esEfectivo, $orden]) {
            $ids[$codigo] = DB::table('metodos_pago')->where('codigo', $codigo)->value('id')
                ?? DB::table('metodos_pago')->insertGetId([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'activo' => true,
                    'es_efectivo' => $esEfectivo,
                    'orden' => $orden,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return $ids;
    }

    private function migrarDatos(): void
    {
        $metodos = $this->asegurarMetodosBase();

        $origenes = [
            'ventas' => 'App\\Models\\Venta',
            'lavados' => 'App\\Models\\Lavado',
            'cambio_aceites' => 'App\\Models\\CambioAceite',
        ];

        foreach ($origenes as $tabla => $type) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'metodo_pago')) {
                continue;
            }

            DB::table($tabla)->orderBy('id')->chunkById(500, function ($filas) use ($type, $metodos) {
                foreach ($filas as $fila) {
                    if (DB::table('pagos')->where('pagable_type', $type)->where('pagable_id', $fila->id)->exists()) {
                        continue;
                    }

                    $lineas = $this->lineasDe($fila, $metodos);
                    foreach ($lineas as $linea) {
                        DB::table('pagos')->insert([
                            'pagable_type' => $type,
                            'pagable_id' => $fila->id,
                            'metodo_pago_id' => $linea[0],
                            'monto' => $linea[1],
                            'user_id' => $fila->user_id ?? null,
                            'caja_id' => $fila->caja_id ?? null,
                            'created_at' => $fila->created_at ?? now(),
                            'updated_at' => $fila->updated_at ?? now(),
                        ]);
                    }
                }
            });
        }
    }

    /**
     * @return list<array{0:int,1:float}> [metodo_id, monto]
     */
    private function lineasDe(object $fila, array $metodos): array
    {
        $total = round((float) ($fila->total ?? 0), 2);

        if (($fila->metodo_pago ?? null) === 'mixto') {
            $partes = [
                [$metodos['efectivo'], (float) ($fila->monto_efectivo ?? 0)],
                [$metodos['yape'], (float) ($fila->monto_yape ?? 0)],
                [$metodos['izipay'], (float) ($fila->monto_izipay ?? 0)],
            ];
            $lineas = array_values(array_filter(
                array_map(fn ($p) => [0 => $p[0], 1 => round($p[1], 2)], $partes),
                fn ($p) => $p[1] > 0
            ));
            if ($lineas !== []) {
                return $lineas;
            }
        }

        $codigo = in_array($fila->metodo_pago ?? null, ['efectivo', 'yape', 'izipay'], true)
            ? $fila->metodo_pago
            : 'efectivo';

        return [[$metodos[$codigo], $total]];
    }
};
