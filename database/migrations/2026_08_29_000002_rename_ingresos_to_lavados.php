<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renombra la entidad "Ingreso" a "Lavado" a nivel de base de datos:
     *  - ingresos            → lavados
     *  - ingreso_trabajadores → lavado_trabajadores
     *  - columna ingreso_id   → lavado_id (en lavado_trabajadores y detalle_servicios)
     *
     * No se modifican migraciones históricas; este rename al final es seguro tanto
     * para un migrate:fresh como para una actualización sobre una BD existente.
     */
    public function up(): void
    {
        // 1) Soltar las FK de las tablas puente que apuntan a `ingresos`,
        //    necesarias antes de renombrar la columna ingreso_id en MySQL.
        Schema::table('ingreso_trabajadores', function (Blueprint $table) {
            $table->dropForeign(['ingreso_id']);
        });

        Schema::table('detalle_servicios', function (Blueprint $table) {
            $table->dropForeign(['ingreso_id']);
        });

        // 2) Renombrar la columna puente en ambas tablas (aún con su nombre original).
        Schema::table('ingreso_trabajadores', function (Blueprint $table) {
            $table->renameColumn('ingreso_id', 'lavado_id');
        });

        Schema::table('detalle_servicios', function (Blueprint $table) {
            $table->renameColumn('ingreso_id', 'lavado_id');
        });

        // 3) Renombrar las tablas.
        Schema::rename('ingreso_trabajadores', 'lavado_trabajadores');
        Schema::rename('ingresos', 'lavados');

        // 4) Re-crear las FK apuntando a la tabla lavados.
        Schema::table('lavado_trabajadores', function (Blueprint $table) {
            $table->foreign('lavado_id')->references('id')->on('lavados')->onDelete('cascade');
        });

        Schema::table('detalle_servicios', function (Blueprint $table) {
            $table->foreign('lavado_id')->references('id')->on('lavados')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Inverso: soltar FK, renombrar columnas/tablas y restaurar FK.
        Schema::table('lavado_trabajadores', function (Blueprint $table) {
            $table->dropForeign(['lavado_id']);
        });

        Schema::table('detalle_servicios', function (Blueprint $table) {
            $table->dropForeign(['lavado_id']);
        });

        Schema::table('lavado_trabajadores', function (Blueprint $table) {
            $table->renameColumn('lavado_id', 'ingreso_id');
        });

        Schema::table('detalle_servicios', function (Blueprint $table) {
            $table->renameColumn('lavado_id', 'ingreso_id');
        });

        Schema::rename('lavado_trabajadores', 'ingreso_trabajadores');
        Schema::rename('lavados', 'ingresos');

        Schema::table('ingreso_trabajadores', function (Blueprint $table) {
            $table->foreign('ingreso_id')->references('id')->on('ingresos')->onDelete('cascade');
        });

        Schema::table('detalle_servicios', function (Blueprint $table) {
            $table->foreign('ingreso_id')->references('id')->on('ingresos')->onDelete('cascade');
        });
    }
};
