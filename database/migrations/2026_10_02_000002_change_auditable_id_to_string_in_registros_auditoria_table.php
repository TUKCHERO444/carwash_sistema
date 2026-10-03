<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cambia `auditable_id` de entero a cadena.
     *
     * La auditoría es polimórfica y algunos modelos usan clave primaria
     * no entera (ej. Automotor usa la placa como PK string). Con el tipo
     * entero, MySQL en modo estricto rechaza esos registros y revierte
     * la transacción que los origina (ej. crear un ticket de lavado con
     * placa no registrada). Los IDs enteros existentes se conservan tal
     * cual al leerse como texto.
     */
    public function up(): void
    {
        Schema::table('registros_auditoria', function (Blueprint $table) {
            $table->dropIndex(['auditable_type', 'auditable_id']);
        });

        Schema::table('registros_auditoria', function (Blueprint $table) {
            $table->string('auditable_id', 50)->change();
        });

        Schema::table('registros_auditoria', function (Blueprint $table) {
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registros_auditoria', function (Blueprint $table) {
            $table->dropIndex(['auditable_type', 'auditable_id']);
        });

        Schema::table('registros_auditoria', function (Blueprint $table) {
            $table->unsignedBigInteger('auditable_id')->change();
        });

        Schema::table('registros_auditoria', function (Blueprint $table) {
            $table->index(['auditable_type', 'auditable_id']);
        });
    }
};
