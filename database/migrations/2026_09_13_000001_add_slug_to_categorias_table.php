<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Añade el slug (identificador URL) a las categorías para las vistas
     * públicas filtradas y lo backfillea con Str::slug sobre el nombre.
     */
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->string('slug', 160)->nullable()->after('nombre');
        });

        foreach (DB::table('categorias')->get() as $categoria) {
            DB::table('categorias')
                ->where('id', $categoria->id)
                ->update(['slug' => Str::slug($categoria->nombre)]);
        }

        Schema::table('categorias', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
