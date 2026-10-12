<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renombrado con datos: vehiculos -> tipos_vehiculo,
     * automotores -> vehiculos, columnas automotor_id -> vehiculo_id,
     * vehiculo_id (catálogo) -> tipo_vehiculo_id, y permisos.
     * Idempotente: en BD frescas (migraciones base ya editadas) no hace nada.
     */
    public function up(): void
    {
        // BD fresca (migraciones base ya editadas): nada que renombrar.
        if (Schema::hasTable('tipos_vehiculo')
            && Schema::hasTable('vehiculos')
            && Schema::hasColumn('vehiculos', 'placa')
            && ! Schema::hasTable('automotores')) {
            $fresco = true;
            foreach (['lavados', 'ingresos'] as $tabla) {
                if (Schema::hasTable($tabla)
                    && (Schema::hasColumn($tabla, 'automotor_id')
                        || ! Schema::hasColumn($tabla, 'tipo_vehiculo_id'))) {
                    $fresco = false;
                    break;
                }
            }
            if ($fresco && Schema::hasTable('cambio_aceites') && Schema::hasColumn('cambio_aceites', 'automotor_id')) {
                $fresco = false;
            }
            if ($fresco) {
                $this->migrarPermisos();

                return;
            }
        }

        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('vehiculos') && ! Schema::hasTable('tipos_vehiculo')) {
            Schema::rename('vehiculos', 'tipos_vehiculo');
        }

        if (Schema::hasTable('automotores') && ! $this->esFisicoYaRenombrado()) {
            Schema::rename('automotores', 'vehiculos');
        }

        // Tabla de tickets: puede llamarse lavados (actual) o ingresos (rollback parcial).
        foreach (['lavados', 'ingresos'] as $tabla) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            // Catálogo: vehiculo_id -> tipo_vehiculo_id
            if (Schema::hasColumn($tabla, 'vehiculo_id')
                && $this->referenciaTabla($tabla, 'vehiculo_id', 'vehiculos')) {
                $this->renombrarFk($tabla, 'vehiculo_id', 'tipo_vehiculo_id', 'tipos_vehiculo', 'id', 'cascade');
            }
            // Físico: automotor_id -> vehiculo_id
            if (Schema::hasColumn($tabla, 'automotor_id')) {
                $this->renombrarColumnaSimple($tabla, 'automotor_id', 'vehiculo_id');
                $this->recrearFkPlaca($tabla, 'vehiculo_id');
            } elseif (Schema::hasColumn($tabla, 'vehiculo_id')
                && ! $this->referenciaTabla($tabla, 'vehiculo_id', 'vehiculos')) {
                // Caso intermedio: columna ya se llama vehiculo_id pero apunta a automotores.
                $this->recrearFkPlaca($tabla, 'vehiculo_id');
            }
        }

        if (Schema::hasTable('cambio_aceites') && Schema::hasColumn('cambio_aceites', 'automotor_id')) {
            $this->renombrarColumnaSimple('cambio_aceites', 'automotor_id', 'vehiculo_id');
            $this->recrearFkPlaca('cambio_aceites', 'vehiculo_id');
        }

        $this->migrarPermisos();

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('cambio_aceites') && Schema::hasColumn('cambio_aceites', 'vehiculo_id')) {
            $this->renombrarColumnaSimple('cambio_aceites', 'vehiculo_id', 'automotor_id');
        }

        foreach (['lavados', 'ingresos'] as $tabla) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            if (Schema::hasColumn($tabla, 'vehiculo_id')
                && $this->referenciaTabla($tabla, 'vehiculo_id', 'vehiculos')) {
                $this->renombrarColumnaSimple($tabla, 'vehiculo_id', 'automotor_id');
            }
            if (Schema::hasColumn($tabla, 'tipo_vehiculo_id')) {
                $this->renombrarColumnaSimple($tabla, 'tipo_vehiculo_id', 'vehiculo_id');
            }
        }

        if (Schema::hasTable('vehiculos') && Schema::hasColumn('vehiculos', 'placa')) {
            Schema::rename('vehiculos', 'automotores');
        }

        if (Schema::hasTable('tipos_vehiculo')) {
            Schema::rename('tipos_vehiculo', 'vehiculos');
        }

        $this->revertirPermisos();

        Schema::enableForeignKeyConstraints();
    }

    private function esFisicoYaRenombrado(): bool
    {
        return Schema::hasTable('vehiculos') && Schema::hasColumn('vehiculos', 'placa');
    }

    private function referenciaTabla(string $tabla, string $columna, string $esperada): bool
    {
        try {
            $filas = DB::select("PRAGMA foreign_key_list('{$tabla}')");
            foreach ($filas as $fila) {
                if (($fila->from ?? null) === $columna) {
                    return ($fila->table ?? null) === $esperada;
                }
            }
        } catch (Throwable) {
            // MySQL u otro driver: se asume por nombre de columna.
        }

        return $columna === 'vehiculo_id' && $esperada === 'vehiculos';
    }

    private function renombrarFk(string $tabla, string $desde, string $hasta, string $refTabla, string $refCol, string $onDelete): void
    {
        Schema::table($tabla, function (Blueprint $table) use ($desde) {
            try {
                $table->dropForeign([$desde]);
            } catch (Throwable) {
            }
        });
        Schema::table($tabla, function (Blueprint $table) use ($desde, $hasta) {
            $table->renameColumn($desde, $hasta);
        });
        Schema::table($tabla, function (Blueprint $table) use ($hasta, $refTabla, $refCol, $onDelete) {
            if ($onDelete === 'cascade') {
                $table->foreign($hasta)->references($refCol)->on($refTabla)->onDelete('cascade');
            } else {
                $table->foreign($hasta)->references($refCol)->on($refTabla)->onDelete('set null');
            }
        });
    }

    private function renombrarColumnaSimple(string $tabla, string $desde, string $hasta): void
    {
        Schema::table($tabla, function (Blueprint $table) use ($desde) {
            try {
                $table->dropForeign([$desde]);
            } catch (Throwable) {
            }
        });
        Schema::table($tabla, function (Blueprint $table) use ($desde, $hasta) {
            $table->renameColumn($desde, $hasta);
        });
    }

    private function recrearFkPlaca(string $tabla, string $columna): void
    {
        Schema::table($tabla, function (Blueprint $table) use ($columna) {
            try {
                $table->dropForeign([$columna]);
            } catch (Throwable) {
            }
        });
        Schema::table($tabla, function (Blueprint $table) use ($columna) {
            $table->foreign($columna)->references('placa')->on('vehiculos')->onDelete('set null');
        });
    }

    private function migrarPermisos(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }
        DB::table('permissions')->where('name', 'acceso-vehiculos')->update(['name' => 'acceso-tipos-vehiculo']);
        DB::table('permissions')->where('name', 'acceso-automotores')->update(['name' => 'acceso-vehiculos']);
        if (! DB::table('permissions')->where('name', 'acceso-tipos-vehiculo')->exists()) {
            DB::table('permissions')->insert(['name' => 'acceso-tipos-vehiculo', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }
        if (! DB::table('permissions')->where('name', 'acceso-vehiculos')->exists()) {
            DB::table('permissions')->insert(['name' => 'acceso-vehiculos', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function revertirPermisos(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }
        DB::table('permissions')->where('name', 'acceso-vehiculos')->update(['name' => 'acceso-automotores']);
        DB::table('permissions')->where('name', 'acceso-tipos-vehiculo')->update(['name' => 'acceso-vehiculos']);
    }
};
