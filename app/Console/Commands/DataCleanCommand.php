<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Trabajador;
use App\Models\Vehiculo;
use App\Services\DataNormalizer;
use Illuminate\Console\Command;

/**
 * Corrige registros legados para que cumplan con los estándares de datos
 * definidos en App\Services\DataNormalizer y en los controladores.
 *
 * Ejecución: php artisan data:clean
 *
 * Las reglas aplicadas son idempotentes y no destructivas: los campos que
 * no puedan normalizarse de forma segura se ponen a null en lugar de eliminar
 * el registro completo.
 */
class DataCleanCommand extends Command
{
    protected $signature = 'data:clean';

    protected $description = 'Normaliza los registros existentes a los estándares de datos del sistema';

    public function handle(DataNormalizer $normalizer): int
    {
        $this->cleanClientes($normalizer);
        $this->cleanTrabajadores($normalizer);
        $this->cleanVehiculos($normalizer);

        $this->info('Limpieza de datos completada.');

        return self::SUCCESS;
    }

    private function cleanClientes(DataNormalizer $normalizer): void
    {
        $contador = 0;

        Cliente::all()->each(function (Cliente $cliente) use ($normalizer, &$contador) {
            $cambios = false;

            $dni = $normalizer->normalizarDni($cliente->dni);
            if ($dni !== $cliente->dni) {
                $cliente->dni = $dni;
                $cambios = true;
            }

            $telefono = $normalizer->normalizarTelefono($cliente->telefono);
            if ($telefono !== $cliente->telefono) {
                $cliente->telefono = $telefono;
                $cambios = true;
            }

            foreach (['nombre', 'apellido_paterno', 'apellido_materno'] as $campo) {
                $limpio = $normalizer->normalizarNombre($cliente->{$campo});
                if ($limpio !== $cliente->{$campo}) {
                    $cliente->{$campo} = $limpio;
                    $cambios = true;
                }
            }

            if ($cambios) {
                $cliente->save();
                $contador++;
            }
        });

        $this->info("Clientes normalizados: {$contador}");
    }

    private function cleanTrabajadores(DataNormalizer $normalizer): void
    {
        $contador = 0;

        Trabajador::all()->each(function (Trabajador $trabajador) use ($normalizer, &$contador) {
            $cambios = false;

            $dni = $normalizer->normalizarDni($trabajador->dni);
            if ($dni !== $trabajador->dni) {
                $trabajador->dni = $dni;
                $cambios = true;
            }

            foreach (['nombre', 'apellido_paterno', 'apellido_materno'] as $campo) {
                $limpio = $normalizer->normalizarNombre($trabajador->{$campo});
                if ($limpio !== $trabajador->{$campo}) {
                    $trabajador->{$campo} = $limpio;
                    $cambios = true;
                }
            }

            if ($cambios) {
                $trabajador->save();
                $contador++;
            }
        });

        $this->info("Trabajadores normalizados: {$contador}");
    }

    private function cleanVehiculos(DataNormalizer $normalizer): void
    {
        $contador = 0;

        Vehiculo::all()->each(function (Vehiculo $vehiculo) use ($normalizer, &$contador) {
            $cambios = false;

            $placa = $normalizer->normalizarPlaca($vehiculo->placa);
            if ($placa !== $vehiculo->placa) {
                $vehiculo->placa = $placa;
                $cambios = true;
            }

            foreach (['marca', 'modelo', 'serie', 'motor', 'vin'] as $campo) {
                $limpio = $normalizer->normalizarAlfanumerico($vehiculo->{$campo});
                if ($limpio !== $vehiculo->{$campo}) {
                    $vehiculo->{$campo} = $limpio;
                    $cambios = true;
                }
            }

            $color = $normalizer->normalizarNombre($vehiculo->color);
            if ($color !== $vehiculo->color) {
                $vehiculo->color = $color;
                $cambios = true;
            }

            if ($cambios) {
                $vehiculo->save();
                $contador++;
            }
        });

        $this->info("Vehículos normalizados: {$contador}");
    }
}
