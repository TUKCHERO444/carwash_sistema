<?php

namespace App\Services;

use App\Models\Automotor;
use App\Models\Cliente;
use Illuminate\Http\Request;

/**
 * Lógica compartida del flujo de tickets (lavado / cambio de aceite):
 * creación del cliente "solo con nombre" y gestión/upsert del automotor.
 *
 * Centraliza el código que antes duplicaba Cliente::updateOrCreate(['placa' => ...])
 * en LavadoController y CambioAceiteController.
 */
class ClienteAutomotorService
{
    public function __construct(private AutomotorApiService $automotorApiService) {}

    /**
     * Obtiene o crea el cliente a partir de los datos del ticket.
     *
     * - Si viene DNI: busca por DNI y actualiza/completa datos; si no existe, lo crea.
     * - Si no viene DNI: crea/actualiza solo con nombre (DNI y apellidos opcionales).
     */
    public function obtenerCliente(Request $request): Cliente
    {
        $datos = array_filter([
            'nombre' => $request->input('nombre'),
            'telefono' => $request->input('telefono'),
            'dni' => $request->input('dni'),
            'apellido_paterno' => $request->input('apellido_paterno'),
            'apellido_materno' => $request->input('apellido_materno'),
        ], fn ($valor) => $valor !== null && $valor !== '');

        $dni = $request->input('dni');

        if (! empty($dni)) {
            $cliente = Cliente::where('dni', $dni)->first();
            if ($cliente) {
                $cliente->update($datos);

                return $cliente;
            }
        }

        return Cliente::create($datos);
    }

    /**
     * Obtiene (upsert) el automotor por placa y lo vincula al cliente.
     *
     * @param  bool  $conApi  Si es true, enriquece con datos de la API vehicular (confirmación).
     */
    public function obtenerAutomotor(string $placa, int $clienteId, bool $conApi = false): Automotor
    {
        $placa = Automotor::normalizarPlaca($placa);

        $datos = ['cliente_id' => $clienteId];

        if ($conApi) {
            $apiDatos = $this->automotorApiService->buscarPorPlaca($placa);
            foreach (['marca', 'modelo', 'serie', 'color', 'motor', 'vin'] as $campo) {
                if (! empty($apiDatos[$campo])) {
                    $datos[$campo] = $apiDatos[$campo];
                }
            }
        }

        $automotor = Automotor::where('placa', $placa)->first();

        if ($automotor) {
            $automotor->update($datos);

            return $automotor;
        }

        return Automotor::create(array_merge(['placa' => $placa], $datos));
    }
}
