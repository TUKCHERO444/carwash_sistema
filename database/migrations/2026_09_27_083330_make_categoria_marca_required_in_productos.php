<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Categoría y marca dejan de ser opcionales en productos.
 *
 * Antes solo se exigían en el formulario; nada impedía insertar un producto sin
 * ellos (ni por SQL ni por una petición que los omitiera). Aquí la integridad
 * pasa a garantizarla la base de datos: ambas columnas pasan a NOT NULL y sus
 * FKs cambian de `SET NULL` a `RESTRICT`, de modo que tampoco se puede borrar
 * una categoría o una marca que todavía tiene productos asociados.
 *
 * Datos previos: los productos legados sin categoría/marca reciben la primera
 * categoría y la primera marca del catálogo. No se inventa un registro nuevo
 * porque el alta de productos ya es obligatoria; el detalle se corrige desde el
 * panel. Si hay productos incompletos y el catálogo está vacío, la migración se
 * detiene con un error explícito en lugar de dejar datos inconsistentes.
 *
 * @see app/Http/Controllers/ProductoController.php (validación del formulario)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->completarCategoria();
        $this->completarMarca();

        // Las FKs existentes son SET NULL: hay que soltarlas para poder pasar las
        // columnas a NOT NULL y luego recrearlas con la nueva regla de borrado.
        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropForeign(['marca_id']);
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->unsignedBigInteger('categoria_id')->nullable(false)->change();
            $table->unsignedBigInteger('marca_id')->nullable(false)->change();
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->foreign('categoria_id')->references('id')->on('categorias')->onDelete('restrict');
            $table->foreign('marca_id')->references('id')->on('marcas')->onDelete('restrict');
        });
    }

    /**
     * Rellena la categoría de los productos que quedaron sin ella.
     *
     * En una base recién creada no hay productos, así que no hay nada que
     * completar y no hace falta catálogo todavía.
     */
    private function completarCategoria(): void
    {
        if (! DB::table('productos')->whereNull('categoria_id')->exists()) {
            return;
        }

        $categoriaId = DB::table('categorias')->orderBy('id')->value('id');

        if ($categoriaId === null) {
            throw new RuntimeException(
                'Hay productos sin categoría y el catálogo de categorías está vacío: '
                .'crea al menos una categoría antes de aplicar esta migración.'
            );
        }

        DB::table('productos')->whereNull('categoria_id')->update(['categoria_id' => $categoriaId]);
    }

    /**
     * Rellena la marca de los productos que quedaron sin ella.
     */
    private function completarMarca(): void
    {
        if (! DB::table('productos')->whereNull('marca_id')->exists()) {
            return;
        }

        $marcaId = DB::table('marcas')->orderBy('id')->value('id');

        if ($marcaId === null) {
            throw new RuntimeException(
                'Hay productos sin marca y el catálogo de marcas está vacío: '
                .'crea al menos una marca antes de aplicar esta migración.'
            );
        }

        DB::table('productos')->whereNull('marca_id')->update(['marca_id' => $marcaId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropForeign(['marca_id']);
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->unsignedBigInteger('categoria_id')->nullable(true)->change();
            $table->unsignedBigInteger('marca_id')->nullable(true)->change();
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->foreign('categoria_id')->references('id')->on('categorias')->onDelete('set null');
            $table->foreign('marca_id')->references('id')->on('marcas')->onDelete('set null');
        });
    }
};
