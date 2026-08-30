<?php

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class AuditModelObserver
{
    /**
     * Columnas auto-administradas que no aportan valor al diff.
     */
    protected const IGNORADAS = [
        'created_at',
        'updated_at',
    ];

    /**
     * Un modelo fue creado.
     */
    public function created(Model $modelo): void
    {
        if (! auth()->check()) {
            return;
        }

        $service = app(AuditService::class);

        if (! $service->esAuditable($modelo)) {
            return;
        }

        $accion = $service->consumirAccionAnotada() ?? 'crear';
        $despues = array_diff_key($modelo->getAttributes(), array_flip(self::IGNORADAS));

        $service->registrar($modelo, $accion, null, $despues);
    }

    /**
     * Un modelo fue actualizado.
     */
    public function updated(Model $modelo): void
    {
        if (! auth()->check()) {
            return;
        }

        $service = app(AuditService::class);

        if (! $service->esAuditable($modelo)) {
            return;
        }

        $accion = $service->consumirAccionAnotada() ?? 'actualizar';

        $antes = [];
        $despues = [];

        foreach ($modelo->getChanges() as $columna => $nuevo) {
            if (in_array($columna, self::IGNORADAS, true)) {
                continue;
            }
            $antes[$columna] = $modelo->getOriginal($columna);
            $despues[$columna] = $nuevo;
        }

        if (empty($despues)) {
            return;
        }

        $service->registrar($modelo, $accion, $antes, $despues);
    }

    /**
     * Un modelo fue eliminado.
     */
    public function deleted(Model $modelo): void
    {
        if (! auth()->check()) {
            return;
        }

        $service = app(AuditService::class);

        if (! $service->esAuditable($modelo)) {
            return;
        }

        $accion = $service->consumirAccionAnotada() ?? 'eliminar';

        $antes = array_diff_key($modelo->getAttributes(), array_flip(self::IGNORADAS));

        $service->registrar($modelo, $accion, $antes, null);
    }
}
