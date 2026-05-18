<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->text('foto')->nullable()->change();
        });
        Schema::table('cambio_aceites', function (Blueprint $table) {
            $table->text('foto')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->string('foto', 255)->nullable()->change();
        });
        Schema::table('cambio_aceites', function (Blueprint $table) {
            $table->string('foto', 255)->nullable()->change();
        });
    }
};
