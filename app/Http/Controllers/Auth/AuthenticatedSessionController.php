<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = $request->user();

        // Verificar si el usuario está activo
        if (!$user->is_active) {
            Auth::logout();

            return response()->json([
                'message' => 'Tu cuenta ha sido desactivada. Contacta con soporte.'
            ], 403);
        }

        // Eliminar tokens antiguos (opcional - mantener solo 1 sesión activa)
        $user->tokens()->delete();

        // Crear nuevo token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'cellphone' => $user->cellphone,
                'is_active' => $user->is_active,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'has_entity' => $user->hasEntity(),
            ],
            'entity' => $user->entity ? [
                'id' => $user->entity->id,
                'tipo_documento' => $user->entity->tipo_documento,
                'numero_documento' => $user->entity->numero_documento,
                'full_name' => $user->entity->full_name,
            ] : null,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    /**
     * Get authenticated user info
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'cellphone' => $user->cellphone,
                'email_verified_at' => $user->email_verified_at,
                'is_active' => $user->is_active,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'has_entity' => $user->hasEntity(),
            ],
            'entity' => $user->entity,
            'addresses' => $user->addresses,
            'current_cart' => $user->current_cart,
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): JsonResponse
    {
        // Eliminar el token actual
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout exitoso'
        ], 200);
    }
}
