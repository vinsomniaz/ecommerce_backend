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
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:100', 'unique:users,email'],
            'cellphone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],

            // Entity fields
            'tipo_documento' => ['required', 'in:DNI,RUC,CE'],
            'numero_documento' => ['required', 'string', 'max:20'],
        ]);

        try {
            return DB::transaction(function () use ($validated) {

                // ========================================
                // 1. Revisar si ya existe una entity con ese documento
                // ========================================
                $existingEntity = Entity::where('numero_documento', $validated['numero_documento'])->first();

                if ($existingEntity && $existingEntity->user_id) {
                    return response()->json([
                        'message' => 'Este número de documento ya está asociado a otra cuenta.',
                        'errors' => [
                            'numero_documento' => ['Este documento ya tiene una cuenta vinculada.']
                        ]
                    ], 422);
                }

                // ========================================
                // 2. Crear el usuario
                // ========================================
                $user = User::create([
                    'first_name' => $validated['first_name'],
                    'last_name'  => $validated['last_name'],
                    'email'      => $validated['email'],
                    'cellphone'  => $validated['cellphone'] ?? null,
                    'password'   => Hash::make($validated['password']),
                    'is_active'  => true,
                ]);

                // Asignar rol
                $user->assignRole('customer');

                // ========================================
                // 3. Crear o vincular entity sin datos de SUNAT/RENIEC
                // ========================================
                if ($existingEntity) {

                    // Vincular entity a user
                    $existingEntity->update([
                        'user_id' => $user->id,
                        'email'   => $user->email,
                        'phone'   => $user->cellphone,
                    ]);

                    $entity  = $existingEntity;
                    $message = 'Cuenta creada. Se vinculó tu historial previo.';

                } else {

                    // Crear entity mínima (el POS lo actualizará después)
                    $entity = Entity::create([
                        'user_id'        => $user->id,
                        'type'           => 'customer',
                        'tipo_documento' => $validated['tipo_documento'],
                        'numero_documento' => $validated['numero_documento'],
                        'tipo_persona'   => $validated['tipo_documento'] === 'RUC' ? 'juridica' : 'natural',

                        // Solo los datos ingresados por usuario
                        'first_name' => $validated['first_name'],
                        'last_name'  => $validated['last_name'],
                        'email'      => $user->email,
                        'phone'      => $user->cellphone,
                        'country_code' => 'PE',
                        'is_active'    => true,

                        // Campos vacíos hasta que POS consulte SUNAT/RENIEC
                        'business_name' => null,
                        'trade_name'    => null,
                        'address'       => null,
                        'ubigeo'        => null,
                        'estado_sunat'  => null,
                        'condicion_sunat' => null,
                    ]);

                    $message = 'Cuenta creada exitosamente. Bienvenido a la tienda.';
                }

                // ========================================
                // 4. Evento de verificación
                // ========================================
                event(new Registered($user));

                // ========================================
                // 5. Token
                // ========================================
                $token = $user->createToken('auth_token')->plainTextToken;

                return response()->json([
                    'message' => $message,
                    'user' => [
                        'id'         => $user->id,
                        'first_name' => $user->first_name,
                        'last_name'  => $user->last_name,
                        'full_name'  => $user->full_name,
                        'email'      => $user->email,
                        'cellphone'  => $user->cellphone,
                        'is_active'  => $user->is_active,
                        'roles'      => $user->getRoleNames(),
                    ],
                    'entity' => [
                        'id'             => $entity->id,
                        'type'           => $entity->type,
                        'tipo_documento' => $entity->tipo_documento,
                        'numero_documento' => $entity->numero_documento,
                        'full_name'      => $entity->full_name,
                        'email'          => $entity->email,
                        'phone'          => $entity->phone,
                    ],
                    'token'      => $token,
                    'token_type' => 'Bearer',
                ], 201);
            });

        } catch (\Exception $e) {
            Log::error('Error en registro: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error al crear la cuenta.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
