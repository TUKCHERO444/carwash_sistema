<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdministratorProtectionService;
use App\Services\AuditService;
use App\Support\RoleNames;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UserToggleController extends Controller
{
    public function __construct(
        private AuditService $auditService,
        private AdministratorProtectionService $administratorProtection,
    ) {}

    /**
     * Toggle the activation status of the given user.
     *
     * Returns HTTP 403 if the authenticated user attempts to toggle their own account.
     * Returns HTTP 200 with the new activo value and a confirmation message on success.
     * HTTP 404 is handled automatically by Laravel Route Model Binding.
     */
    public function toggle(User $user): JsonResponse
    {
        // 1. Evitar que el usuario se inactive a sí mismo
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'No puedes modificar tu propio estado de activación por seguridad.',
            ], 403);
        }

        // 2. Solo administradores pueden inactivar a otros administradores
        if ($user->hasRole(RoleNames::ADMINISTRADOR) && ! auth()->user()->hasRole(RoleNames::ADMINISTRADOR)) {
            return response()->json([
                'message' => 'No tienes permisos suficientes para cambiar el estado de un Administrador.',
            ], 403);
        }

        Gate::authorize('toggle', $user);

        // 3. Evitar dejar el sistema sin administradores utilizables
        if ($user->activo && $this->administratorProtection->esAdministradorCritico($user)) {
            return response()->json([
                'message' => 'No puedes desactivar al último Administrador.',
            ], 403);
        }

        $this->auditService->anotarAccion('toggle activo');

        $user->activo = ! $user->activo;
        $user->save();

        $message = $user->activo
            ? 'Usuario activado correctamente.'
            : 'Usuario inactivado correctamente.';

        return response()->json([
            'activo' => (bool) $user->activo,
            'message' => $message,
        ], 200);
    }
}
