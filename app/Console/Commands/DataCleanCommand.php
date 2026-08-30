<?php

namespace App\Console\Commands;

use App\Models\Automotor;
use App\Models\Cliente;
use App\Models\Trabajador;
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
        $this->cleanAutomotores($normalizer);

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

    private function cleanAutomotores(DataNormalizer $normalizer): void
    {
        $contador = 0;

        Automotor::all()->each(function (Automotor $automotor) use ($normalizer, &$contador) {
            $cambios = false;

            $placa = $normalizer->normalizarPlaca($automotor->placa);
            if ($placa !== $automotor->placa) {
                $automotor->placa = $placa;
                $cambios = true;
            }

            foreach (['marca', 'modelo', 'serie', 'motor', 'vin'] as $campo) {
                $limpio = $normalizer->normalizarAlfanumerico($automotor->{$campo});
                if ($limpio !== $automotor->{$campo}) {
                    $automotor->{$campo} = $limpio;
                    $cambios = true;
                }
            }

            $color = $normalizer->normalizarNombre($automotor->color);
            if ($color !== $automotor->color) {
                $automotor->color = $color;
                $cambios = true;
            }

            if ($cambios) {
                $automotor->save();
                $contador++;
            }
        });

        $this->info("Automotores normalizados: {$contador}");
    }
}
