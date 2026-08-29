<?php

namespace App\Services;

use App\Models\Automotor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de integración con la API REST de datos vehiculares (api.json.pe).
 *
 * Endpoint: POST https://api.json.pe/api/placa
 * Auth:     header `Authorization: Bearer <token>`
 * Body:     { "placa": "F3H792" }
 * Res:      { success: bool, message: string, data: { placa, marca, modelo, serie, color, motor, vin } }
 *
 * Configuración: VEHICLE_API_URL / VEHICLE_API_TOKEN en el .env (leídos por
 * config/services.php).
 *
 * Comportamiento resiliente (RNF-02): si la API no responde, no encuentra el
 * vehículo o aún no está configurada, se devuelve [] y se loguea el incidente,
 * de modo que el flujo de confirmación del ticket nunca se bloquee.
 */
class AutomotorApiService
{
    /**
     * Busca los datos vehiculares asociados a una placa.
     *
     * @return array<string, string> Campos ['marca','modelo','serie','color','motor','vin']
     *                               o [] si no hay datos disponibles (fallback).
     */
    public function buscarPorPlaca(string $placa): array
    {
        $placa = Automotor::normalizarPlaca($placa);

        $url = config('services.vehicle_api.url');
        $token = config('services.vehicle_api.token');

        if (empty($url) || empty($token)) {
            // API aún no configurada: registrar y devolver fallback.
            Log::info('AutomotorApiService: API vehicular no configurada, se omite consulta.', ['placa' => $placa]);

            return [];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(5)
                ->post($url, ['placa' => $placa]);

            if (! $response->ok()) {
                Log::warning('AutomotorApiService: respuesta no exitosa de la API.', [
                    'placa' => $placa,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $payload = $response->json();

            if (($payload['success'] ?? false) !== true || empty($payload['data'])) {
                Log::info('AutomotorApiService: vehículo no encontrado en la API.', ['placa' => $placa]);

                return [];
            }

            $dato = $payload['data'];

            return array_filter([
                'marca' => $dato['marca'] ?? null,
                'modelo' => $dato['modelo'] ?? null,
                'serie' => $dato['serie'] ?? null,
                'color' => $dato['color'] ?? null,
                'motor' => $dato['motor'] ?? null,
                'vin' => $dato['vin'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
        } catch (\Throwable $e) {
            Log::error('AutomotorApiService: no se pudo obtener datos del vehículo.', [
                'placa' => $placa,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
