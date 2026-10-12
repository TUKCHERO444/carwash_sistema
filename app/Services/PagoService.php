<?php

namespace App\Services;

use App\Models\MetodoPago;
use App\Models\Pago;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lógica central de líneas de pago (ventas, lavados, cambios de aceite).
 *
 * Un ticket tiene 1..N líneas en `pagos` cuya suma iguala su `total`.
 * Los formularios envían `metodo_pago` (código o 'mixto') más los
 * campos `monto_<codigo>` por método; este servicio valida y persiste.
 */
class PagoService
{
    /**
     * @return Collection<int, MetodoPago>
     */
    public function metodosActivos(): Collection
    {
        return MetodoPago::where('activo', true)->orderBy('orden')->orderBy('id')->get();
    }

    /**
     * @return list<string>
     */
    public function codigosActivos(): array
    {
        return $this->metodosActivos()->pluck('codigo')->all();
    }

    /**
     * Reglas de validación del bloque de pago (se fusionan con las del ticket).
     */
    public function reglas(): array
    {
        $reglas = [
            'metodo_pago' => ['required', 'string', Rule::in(array_merge($this->codigosActivos(), ['mixto']))],
        ];

        foreach ($this->codigosActivos() as $codigo) {
            $reglas["monto_{$codigo}"] = ['nullable', 'numeric', 'min:0'];
        }

        return $reglas;
    }

    /**
     * Valida el bloque de pago y devuelve las líneas a persistir.
     *
     * @return list<array{metodo_pago_id:int, codigo:string, monto:float}>
     *
     * @throws ValidationException Si es mixto sin montos o la suma difiere del total.
     */
    public function validar(Request $request, float $total): array
    {
        $metodos = $this->metodosActivos()->keyBy('codigo');
        $elegido = (string) $request->input('metodo_pago');

        if ($elegido !== 'mixto') {
            $metodo = $metodos->get($elegido);
            if (! $metodo) {
                throw ValidationException::withMessages(['metodo_pago' => 'Método de pago no válido.']);
            }

            return [['metodo_pago_id' => $metodo->id, 'codigo' => $metodo->codigo, 'monto' => round($total, 2)]];
        }

        $lineas = [];
        foreach ($metodos as $codigo => $metodo) {
            $monto = round((float) ($request->input("monto_{$codigo}") ?? 0), 2);
            if ($monto > 0) {
                $lineas[] = ['metodo_pago_id' => $metodo->id, 'codigo' => $codigo, 'monto' => $monto];
            }
        }

        if ($lineas === []) {
            throw ValidationException::withMessages(['pagos' => 'Debe ingresar al menos un monto para el pago mixto.']);
        }

        $suma = round(array_sum(array_column($lineas, 'monto')), 2);
        if (abs($suma - round($total, 2)) > 0.01) {
            throw ValidationException::withMessages(['pagos' => 'La suma de los montos no coincide con el total.']);
        }

        return $lineas;
    }

    /**
     * Reemplaza las líneas de pago del ticket (dentro de la transacción del llamador).
     */
    public function guardarLineas(Model $pagable, array $lineas, ?int $userId = null, ?int $cajaId = null): void
    {
        $pagable->pagos()->delete();

        foreach ($lineas as $linea) {
            $pagable->pagos()->create([
                'metodo_pago_id' => $linea['metodo_pago_id'],
                'monto' => $linea['monto'],
                'user_id' => $userId,
                'caja_id' => $cajaId,
            ]);
        }
    }

    /**
     * @return array{codigo: ?string, mixto: bool, montos: array<string, float>, lineas: list<array{codigo:string, nombre:string, monto:float}>, etiqueta: string}
     */
    public function resumen(Model $pagable): array
    {
        $pagos = $pagable->pagos()->with('metodoPago')->get();

        $lineas = $pagos->map(fn (Pago $p) => [
            'codigo' => $p->metodoPago?->codigo ?? '—',
            'nombre' => $p->metodoPago?->nombre ?? '—',
            'monto' => round((float) $p->monto, 2),
        ])->values()->all();

        $montos = [];
        foreach ($lineas as $linea) {
            $montos[$linea['codigo']] = $linea['monto'];
        }

        if (count($lineas) > 1) {
            $nombres = implode(' + ', array_column($lineas, 'nombre'));

            return ['codigo' => null, 'mixto' => true, 'montos' => $montos, 'lineas' => $lineas, 'etiqueta' => "Mixto ({$nombres})"];
        }

        $unica = $lineas[0] ?? null;

        return [
            'codigo' => $unica['codigo'] ?? null,
            'mixto' => false,
            'montos' => $montos,
            'lineas' => $lineas,
            'etiqueta' => $unica['nombre'] ?? '—',
        ];
    }

    /**
     * Mapea un código de método al tipo_pago de egresos_caja
     * (efectivo, yape, transferencia, tarjeta).
     */
    public function tipoEgresoPara(string $codigo): string
    {
        return match ($codigo) {
            'efectivo' => 'efectivo',
            'yape' => 'yape',
            'izipay', 'tarjeta' => 'tarjeta',
            'transferencia' => 'transferencia',
            default => 'tarjeta',
        };
    }
}
