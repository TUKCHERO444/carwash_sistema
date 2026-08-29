<?php

namespace Tests\Unit;

use App\Services\DniApiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DniApiServiceTest extends TestCase
{
    private DniApiService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DniApiService;

        config([
            'services.dni_api.url' => 'https://api.json.pe/api/dni',
            'services.dni_api.token' => 'token-de-prueba',
        ]);
    }

    private function desconfigurarApi(): void
    {
        config([
            'services.dni_api.url' => null,
            'services.dni_api.token' => null,
        ]);
    }

    public function test_maps_person_data_from_successful_response(): void
    {
        Http::fake([
            'https://api.json.pe/api/dni' => Http::response([
                'success' => true,
                'message' => 'exito',
                'data' => [
                    'numero' => '27427864',
                    'codigo_verificacion' => '7',
                    'nombres' => 'JOSE PEDRO',
                    'apellido_paterno' => 'CASTILLO',
                    'apellido_materno' => 'TERRONES',
                    'nombre_completo' => 'CASTILLO TERRONES, JOSE PEDRO',
                    'direccion' => 'AV LOS ALAMOS 123',
                    'direccion_completa' => 'AV LOS ALAMOS 123 - LIMA',
                    'ubigeo_reniec' => '150101',
                    'ubigeo_sunat' => '150101',
                ],
            ]),
        ]);

        $result = $this->service->buscarPorDni('27427864');

        $this->assertSame([
            'numero' => '27427864',
            'codigo_verificacion' => '7',
            'nombres' => 'JOSE PEDRO',
            'apellido_paterno' => 'CASTILLO',
            'apellido_materno' => 'TERRONES',
            'nombre_completo' => 'CASTILLO TERRONES, JOSE PEDRO',
            'direccion' => 'AV LOS ALAMOS 123',
            'direccion_completa' => 'AV LOS ALAMOS 123 - LIMA',
            'ubigeo_reniec' => '150101',
            'ubigeo_sunat' => '150101',
        ], $result);
    }

    public function test_sends_post_with_bearer_token_and_dni(): void
    {
        Http::fake([
            'https://api.json.pe/api/dni' => Http::response([
                'success' => true,
                'data' => ['numero' => '27427864', 'nombres' => 'JOSE PEDRO'],
            ]),
        ]);

        $this->service->buscarPorDni('27427864');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.json.pe/api/dni'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer token-de-prueba')
                && ($request->data()['dni'] ?? null) === '27427864';
        });
    }

    public function test_returns_empty_when_success_is_false(): void
    {
        Http::fake([
            'https://api.json.pe/api/dni' => Http::response([
                'success' => false,
                'message' => 'No encontrado',
                'data' => null,
            ]),
        ]);

        $this->assertSame([], $this->service->buscarPorDni('27427864'));
    }

    public function test_returns_empty_when_data_is_empty(): void
    {
        Http::fake([
            'https://api.json.pe/api/dni' => Http::response(['success' => true, 'data' => []]),
        ]);

        $this->assertSame([], $this->service->buscarPorDni('27427864'));
    }

    public function test_returns_empty_on_non_ok_http_status(): void
    {
        Http::fake([
            'https://api.json.pe/api/dni' => Http::response(['success' => false], 404),
        ]);

        $this->assertSame([], $this->service->buscarPorDni('27427864'));
    }

    public function test_returns_empty_when_api_not_configured(): void
    {
        $this->desconfigurarApi();

        $this->assertSame([], $this->service->buscarPorDni('27427864'));
    }

    public function test_returns_empty_on_connection_exception(): void
    {
        Http::fake([
            'https://api.json.pe/api/dni' => fn () => throw new ConnectionException('Timeout'),
        ]);

        $this->assertSame([], $this->service->buscarPorDni('27427864'));
    }

    public function test_returns_empty_on_invalid_dni(): void
    {
        Http::fake([
            'https://api.json.pe/api/dni' => Http::response(['success' => true]),
        ]);

        $this->assertSame([], $this->service->buscarPorDni('123'));

        Http::assertNothingSent();
    }

    public function test_filters_empty_fields_from_response(): void
    {
        Http::fake([
            'https://api.json.pe/api/dni' => Http::response([
                'success' => true,
                'data' => [
                    'numero' => '27427864',
                    'codigo_verificacion' => '',
                    'nombres' => 'JOSE PEDRO',
                    'apellido_paterno' => null,
                    'apellido_materno' => '',
                    'nombre_completo' => 'JOSE PEDRO CASTILLO',
                    'direccion' => '',
                    'direccion_completa' => null,
                    'ubigeo_reniec' => '',
                    'ubigeo_sunat' => null,
                ],
            ]),
        ]);

        $this->assertSame([
            'numero' => '27427864',
            'nombres' => 'JOSE PEDRO',
            'nombre_completo' => 'JOSE PEDRO CASTILLO',
        ], $this->service->buscarPorDni('27427864'));
    }
}
