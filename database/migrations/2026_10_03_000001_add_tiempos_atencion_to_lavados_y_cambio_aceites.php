<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orden de atención: tiempos de atención en lavados y cambios de aceite.
 *
 * - atencion_inicio_at: timestamp de registro del ticket (inicio de la orden).
 * - atencion_fin_at: momento en que el ticket se confirma/paga (fin de la atención).
 * - tiempo_atencion_minutos: duración en minutos/horas, solo entero en BD.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['lavados', 'cambio_aceites'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dateTime('atencion_inicio_at')->nullable();
                $table->dateTime('atencion_fin_at')->nullable();
                $table->unsignedInteger('tiempo_atencion_minutos')->nullable();
            });
        }

        // Backfill de filas históricas: inicio = created_at; si ya está
        // confirmado, fin = updated_at y duración = diferencia en minutos.
        foreach (['lavados', 'cambio_aceites'] as $tabla) {
            DB::table($tabla)->whereNull('atencion_inicio_at')
                ->update(['atencion_inicio_at' => DB::raw('created_at')]);

            DB::table($tabla)->where('estado', 'confirmado')
                ->whereNull('atencion_fin_at')
                ->orderBy('id')
                ->chunkById(200, function ($filas) use ($tabla) {
                    foreach ($filas as $fila) {
                        $inicio = Carbon::parse($fila->atencion_inicio_at ?? $fila->created_at);
                        $fin = Carbon::parse($fila->updated_at ?? $fila->created_at);
                        if ($fin->lessThan($inicio)) {
                            $fin = $inicio;
                        }

                        DB::table($tabla)->where('id', $fila->id)->update([
                            'atencion_fin_at' => $fin->toDateTimeString(),
                            'tiempo_atencion_minutos' => (int) $inicio->diffInMinutes($fin),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach (['cambio_aceites', 'lavados'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn(['atencion_inicio_at', 'atencion_fin_at', 'tiempo_atencion_minutos']);
            });
        }
    }
};
