<?php

namespace Tests\Unit;

use App\Services\AutomotorApiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutomotorApiServiceTest extends TestCase
{
    private AutomotorApiService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AutomotorApiService;

        config([
            'services.vehicle_api.url' => 'https://api.json.pe/api/placa',
            'services.vehicle_api.token' => 'token-de-prueba',
        ]);
    }

    private function desconfigurarApi(): void
    {
        config([
            'services.vehicle_api.url' => null,
            'services.vehicle_api.token' => null,
        ]);
    }

    public function test_maps_vehicle_data_from_successful_response(): void
    {
        Http::fake([
            'https://api.json.pe/api/placa' => Http::response([
                'success' => true,
                'message' => 'ok',
                'data' => [
                    'placa' => 'F3H792',
                    'marca' => 'Toyota',
                    'modelo' => 'Corolla',
                    'serie' => 'SERIE123',
                    'color' => 'Rojo',
                    'motor' => 'MOTOR1',
                    'vin' => 'VIN987654',
                ],
            ]),
        ]);

        $result = $this->service->buscarPorPlaca('f3h792');

        $this->assertSame([
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'serie' => 'SERIE123',
            'color' => 'Rojo',
            'motor' => 'MOTOR1',
            'vin' => 'VIN987654',
        ], $result);
    }

    public function test_sends_post_with_bearer_token_and_placa(): void
    {
        Http::fake([
            'https://api.json.pe/api/placa' => Http::response([
                'success' => true,
                'data' => ['placa' => 'F3H792', 'marca' => 'Toyota'],
            ]),
        ]);

        $this->service->buscarPorPlaca('F3H792');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.json.pe/api/placa'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer token-de-prueba')
                && ($request->data()['placa'] ?? null) === 'F3H792';
        });
    }

    public function test_returns_empty_when_success_is_false(): void
    {
        Http::fake([
            'https://api.json.pe/api/placa' => Http::response([
                'success' => false,
                'message' => 'No encontrado',
                'data' => null,
            ]),
        ]);

        $this->assertSame([], $this->service->buscarPorPlaca('F3H792'));
    }

    public function test_returns_empty_when_data_is_empty(): void
    {
        Http::fake([
            'https://api.json.pe/api/placa' => Http::response(['success' => true, 'data' => []]),
        ]);

        $this->assertSame([], $this->service->buscarPorPlaca('F3H792'));
    }

    public function test_returns_empty_on_non_ok_http_status(): void
    {
        Http::fake([
            'https://api.json.pe/api/placa' => Http::response(['success' => false], 404),
        ]);

        $this->assertSame([], $this->service->buscarPorPlaca('F3H792'));
    }

    public function test_returns_empty_when_api_not_configured(): void
    {
        $this->desconfigurarApi();

        $this->assertSame([], $this->service->buscarPorPlaca('F3H792'));
    }

    public function test_returns_empty_on_connection_exception(): void
    {
        Http::fake([
            'https://api.json.pe/api/placa' => fn () => throw new ConnectionException('Timeout'),
        ]);

        $this->assertSame([], $this->service->buscarPorPlaca('F3H792'));
    }

    public function test_filters_empty_fields_from_response(): void
    {
        Http::fake([
            'https://api.json.pe/api/placa' => Http::response([
                'success' => true,
                'data' => [
                    'placa' => 'F3H792',
                    'marca' => '',
                    'modelo' => null,
                    'serie' => 'SERIE123',
                    'color' => '',
                    'motor' => 'MOTOR1',
                    'vin' => '',
                ],
            ]),
        ]);

        $this->assertSame([
            'serie' => 'SERIE123',
            'motor' => 'MOTOR1',
        ], $this->service->buscarPorPlaca('F3H792'));
    }
}
