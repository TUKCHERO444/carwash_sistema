<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Vehiculo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Restricciones UNIQUE a nivel de BD en documentos y VIN.
 */
class UnicidadDocumentosTest extends TestCase
{
    use RefreshDatabase;

    public function test_clientes_dni_duplicado_es_rechazado_por_bd(): void
    {
        Cliente::create(['dni' => '12345678', 'nombre' => 'Juan']);

        $this->expectException(QueryException::class);

        Cliente::create(['dni' => '12345678', 'nombre' => 'Otro']);
    }

    public function test_clientes_dni_nulo_permite_varios_registros(): void
    {
        Cliente::create(['dni' => null, 'nombre' => 'Juan']);
        Cliente::create(['dni' => null, 'nombre' => 'Pedro']);

        $this->assertDatabaseCount('clientes', 2);
    }

    public function test_vehiculos_vin_duplicado_es_rechazado_por_bd(): void
    {
        $cliente = Cliente::factory()->create();

        Vehiculo::create(['placa' => 'ABC123', 'cliente_id' => $cliente->id, 'vin' => 'VIN12345']);

        $this->expectException(QueryException::class);

        Vehiculo::create(['placa' => 'XYZ999', 'cliente_id' => $cliente->id, 'vin' => 'VIN12345']);
    }

    public function test_vehiculos_vin_nulo_permite_varios_registros(): void
    {
        $cliente = Cliente::factory()->create();

        Vehiculo::create(['placa' => 'ABC123', 'cliente_id' => $cliente->id, 'vin' => null]);
        Vehiculo::create(['placa' => 'XYZ999', 'cliente_id' => $cliente->id, 'vin' => null]);

        $this->assertDatabaseCount('vehiculos', 2);
    }
}
