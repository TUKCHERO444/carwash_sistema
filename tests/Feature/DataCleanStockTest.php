<?php

namespace Tests\Feature;

use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión: la edición antigua de tickets de cambio de aceite descontaba
 * inventario dos veces y dejaba stock negativo (p. ej. producto 1 = -4).
 * `php artisan data:clean` recorta stock/inventario negativos a 0 de forma
 * idempotente y no destructiva.
 */
class DataCleanStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_clean_recorta_stock_negativo_a_cero(): void
    {
        $negativo = Producto::factory()->create([
            'stock' => -4,
            'inventario' => -2,
            'activo' => true,
        ]);
        $sano = Producto::factory()->create([
            'stock' => 7,
            'inventario' => 20,
            'activo' => true,
        ]);

        $this->artisan('data:clean')->assertSuccessful();

        $negativo->refresh();
        $this->assertSame(0, $negativo->stock);
        $this->assertSame(0, $negativo->inventario);

        $sano->refresh();
        $this->assertSame(7, $sano->stock);
        $this->assertSame(20, $sano->inventario);
    }

    public function test_data_clean_es_idempotente(): void
    {
        $producto = Producto::factory()->create(['stock' => -10, 'inventario' => -10]);

        $this->artisan('data:clean')->assertSuccessful();
        $this->artisan('data:clean')->assertSuccessful();

        $producto->refresh();
        $this->assertSame(0, $producto->stock);
        $this->assertSame(0, $producto->inventario);
    }
}
