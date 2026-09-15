<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Añade el campo foto (string, nullable) a la tabla marcas.
     * Almacena la URL segura devuelta por Cloudinary (o ruta local),
     * igual que el campo foto de productos.
     */
    public function up(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
