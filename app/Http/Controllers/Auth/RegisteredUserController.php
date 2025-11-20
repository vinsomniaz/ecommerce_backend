<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Entity;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Log;

class RegisteredUserController extends Controller
{
    /**
     * Handle registration request.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'cellphone'  => ['nullable', 'string', 'max:20'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
            // Opcional: campos de documento si los pides en el registro
            'tipo_documento'   => ['nullable', 'string', 'max:2'],
            'numero_documento' => ['nullable', 'string', 'max:20'],
        ]);

        // Crear usuario
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'cellphone'  => $validated['cellphone'] ?? null,
            'password'   => Hash::make($validated['password']),
            'is_active'  => true,
        ]);

        // Asignar rol de cliente e-commerce
        $user->assignRole('customer');

        // Crear entity SOLO para customer (usa el helper)
        $entityData = [];

        if (!empty($validated['tipo_documento'])) {
            $entityData['tipo_documento'] = $validated['tipo_documento'];
        }

        if (!empty($validated['numero_documento'])) {
            $entityData['numero_documento'] = $validated['numero_documento'];
        }

        $entity = $user->getOrCreateEntity($entityData);

        // Crear token de acceso
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registro exitoso',
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
            'entity' => $entity,
            'token'  => $token,
            'token_type' => 'Bearer',
        ], 201);
    }
}
