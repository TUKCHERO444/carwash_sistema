<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restituye el unique de clientes.dni (eliminado al hacerlo nullable)
     * y agrega el unique de vehiculos.vin. Falla con mensaje explícito
     * si hay duplicados legados (deben depurarse antes, nunca se
     * destruyen datos automáticamente).
     */
    public function up(): void
    {
        $this->exigirSinDuplicados('clientes', 'dni');
        $this->exigirSinDuplicados('vehiculos', 'vin');

        if (! $this->tieneUnique('clientes', 'dni')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->unique('dni');
            });
        }

        if (Schema::hasColumn('vehiculos', 'vin') && ! $this->tieneUnique('vehiculos', 'vin')) {
            Schema::table('vehiculos', function (Blueprint $table) {
                $table->unique('vin');
            });
        }
    }

    public function down(): void
    {
        try {
            Schema::table('vehiculos', function (Blueprint $table) {
                $table->dropUnique(['vin']);
            });
        } catch (Throwable) {
        }

        try {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropUnique(['dni']);
            });
        } catch (Throwable) {
        }
    }

    private function exigirSinDuplicados(string $tabla, string $columna): void
    {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna)) {
            return;
        }

        $duplicados = DB::table($tabla)
            ->select($columna, DB::raw('COUNT(*) as total'))
            ->whereNotNull($columna)
            ->groupBy($columna)
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->get();

        if ($duplicados->isNotEmpty()) {
            $ejemplos = $duplicados->map(fn ($f) => "{$f->$columna} (×{$f->total})")->implode(', ');

            throw new RuntimeException(
                "No se puede crear el índice único en {$tabla}.{$columna}: hay valores duplicados ({$ejemplos}). "
                .'Depure los registros duplicados y vuelva a ejecutar la migración.'
            );
        }
    }

    private function tieneUnique(string $tabla, string $columna): bool
    {
        try {
            foreach (Schema::getIndexes($tabla) as $indice) {
                $columnas = array_values((array) ($indice['columns'] ?? []));
                if ($columnas === [$columna] && ($indice['unique'] ?? false)) {
                    return true;
                }
            }
        } catch (Throwable) {
        }

        return false;
    }
};
