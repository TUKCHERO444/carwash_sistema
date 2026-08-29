<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de integración con la API REST de datos de personas (api.json.pe).
 *
 * Endpoint: POST https://api.json.pe/api/dni
 * Auth:     header `Authorization: Bearer <token>`
 * Body:     { "dni": "27427864" }
 * Res:      { success: bool, message: string, data: { numero, codigo_verificacion,
 *            nombres, apellido_paterno, apellido_materno, nombre_completo,
 *            direccion, direccion_completa, ubigeo_reniec, ubigeo_sunat } }
 *
 * Configuración: DNI_API_URL / VEHICLE_API_TOKEN en el .env (leídos por
 * config/services.php). Reutiliza el token de la API de placa porque el
 * proveedor y la autenticación son los mismos.
 *
 * Comportamiento resiliente (RNF-02): si la API no responde, no encuentra la
 * persona, el DNI no es válido o aún no está configurada, se devuelve [] y se
 * loguea el incidente, de modo que el formulario de clientes/trabajadores
 * nunca se bloquee.
 */
class DniApiService
{
    /**
     * Busca los datos personales asociados a un DNI.
     *
     * @return array<string, string> Campos ['numero','codigo_verificacion','nombres',
     *                               'apellido_paterno','apellido_materno','nombre_completo',
     *                               'direccion','direccion_completa','ubigeo_reniec','ubigeo_sunat']
     *                               o [] si no hay datos disponibles (fallback).
     */
    public function buscarPorDni(string $dni): array
    {
        $dni = trim($dni);

        $url = config('services.dni_api.url');
        $token = config('services.dni_api.token');

        if (empty($url) || empty($token)) {
            // API aún no configurada: registrar y devolver fallback.
            Log::info('DniApiService: API DNI no configurada, se omite consulta.', ['dni' => $dni]);

            return [];
        }

        if (! preg_match('/^[0-9]{8}$/', $dni)) {
            Log::info('DniApiService: DNI inválido, se omite consulta.', ['dni' => $dni]);

            return [];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(5)
                ->post($url, ['dni' => $dni]);

            if (! $response->ok()) {
                Log::warning('DniApiService: respuesta no exitosa de la API.', [
                    'dni' => $dni,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $payload = $response->json();

            if (($payload['success'] ?? false) !== true || empty($payload['data'])) {
                Log::info('DniApiService: persona no encontrada en la API.', ['dni' => $dni]);

                return [];
            }

            $dato = $payload['data'];

            return array_filter([
                'numero' => $dato['numero'] ?? null,
                'codigo_verificacion' => $dato['codigo_verificacion'] ?? null,
                'nombres' => $dato['nombres'] ?? null,
                'apellido_paterno' => $dato['apellido_paterno'] ?? null,
                'apellido_materno' => $dato['apellido_materno'] ?? null,
                'nombre_completo' => $dato['nombre_completo'] ?? null,
                'direccion' => $dato['direccion'] ?? null,
                'direccion_completa' => $dato['direccion_completa'] ?? null,
                'ubigeo_reniec' => $dato['ubigeo_reniec'] ?? null,
                'ubigeo_sunat' => $dato['ubigeo_sunat'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
        } catch (\Throwable $e) {
            Log::error('DniApiService: no se pudo obtener datos de la persona.', [
                'dni' => $dni,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
