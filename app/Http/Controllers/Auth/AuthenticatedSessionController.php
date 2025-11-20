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

        // 🔹 SOLO CLIENTES: asegurar que tengan entity (lazy)
        $entity = null;
        if ($user->hasRole('customer')) {
            $entity = $user->getOrCreateEntity(); // esto ya respeta la regla de roles
        }

        // Eliminar tokens antiguos (opcional)
        $user->tokens()->delete();

        // Crear nuevo token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user' => [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'full_name'  => $user->full_name,
                'email'      => $user->email,
                'cellphone'  => $user->cellphone,
                'is_active'  => $user->is_active,
                'roles'      => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'has_entity' => $user->hasEntity(),
            ],
            'entity' => $entity ? [
                'id'              => $entity->id,
                'tipo_documento'  => $entity->tipo_documento,
                'numero_documento' => $entity->numero_documento,
                'full_name'       => $entity->full_name,
            ] : null,
            'token'      => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    /**
     * Get authenticated user info
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        // Igual: solo clientes tienen entity
        $entity = null;
        if ($user->hasRole('customer')) {
            $entity = $user->entity; // aquí NO creamos, solo leemos
        }

        return response()->json([
            'user' => [
                'id'               => $user->id,
                'first_name'       => $user->first_name,
                'last_name'        => $user->last_name,
                'full_name'        => $user->full_name,
                'email'            => $user->email,
                'cellphone'        => $user->cellphone,
                'email_verified_at' => $user->email_verified_at,
                'is_active'        => $user->is_active,
                'roles'            => $user->getRoleNames(),
                'permissions'      => $user->getAllPermissions()->pluck('name'),
                'has_entity'       => $user->hasEntity(),
            ],
            'entity'      => $entity,
            'addresses'   => $user->hasRole('customer') ? $user->addresses : [],
            'current_cart' => $user->hasRole('customer') ? $user->current_cart : null,
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
