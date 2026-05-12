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
        Schema::create('cambio_aceite_trabajadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cambio_aceite_id')->constrained('cambio_aceites')->onDelete('cascade');
            $table->foreignId('trabajador_id')->constrained('trabajadores')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['cambio_aceite_id', 'trabajador_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cambio_aceite_trabajadores');
    }
};
