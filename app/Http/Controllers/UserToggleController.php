<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserToggleController extends Controller
{
    /**
     * Toggle the activation status of the given user.
     *
     * Returns HTTP 403 if the authenticated user attempts to toggle their own account.
     * Returns HTTP 200 with the new activo value and a confirmation message on success.
     * HTTP 404 is handled automatically by Laravel Route Model Binding.
     */
    public function toggle(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'No puedes modificar tu propio estado de activación.',
            ], 403);
        }

        $user->activo = !$user->activo;
        $user->save();

        $message = $user->activo
            ? 'Usuario activado correctamente.'
            : 'Usuario inactivado correctamente.';

        return response()->json([
            'activo'  => (int) $user->activo,
            'message' => $message,
        ], 200);
    }
}
