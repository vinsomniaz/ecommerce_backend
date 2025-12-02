<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Entity;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

    /**
     * Registro exclusivo para Clientes Ecommerce
     */
    public function storeCustomer(Request $request)
    {
        // 1. Validaciones
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'   => ['required', 'confirmed', 'min:8'],
            'phone'      => ['nullable', 'string', 'min:9', 'max:20'],

            // Validación de documento
            'document_type_id' => ['required', 'in:01,04'], // 1=DNI, 2=CE
            'document_number' => [
                'required',
                'string',
                Rule::unique('entities', 'numero_documento'),
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->document_type_id == 01 && !preg_match('/^\d{8}$/', $value)) {
                        $fail('El DNI debe tener 8 dígitos numéricos.');
                    }
                    if ($request->document_type_id == 04 && !preg_match('/^[a-zA-Z0-9]{9,12}$/', $value)) {
                        $fail('El Carnet de Extranjería no tiene un formato válido.');
                    }
                },
            ],
        ]);

        // 2. Transacción
        $user = DB::transaction(function () use ($request) {

            // A. PRIMERO creamos el Usuario (Obtenemos el ID aquí)
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'email'      => $request->email,
                'cellphone'  => $request->phone,
                'password'   => Hash::make($request->password),
                'is_active'  => true,
            ]);

            $user->assignRole('customer');


            // B. LUEGO creamos la Entidad vinculada al Usuario
            $entity = Entity::create([
                'user_id'          => $user->id,
                'type'             => 'customer',
                'tipo_documento'   => $request->document_type_id,
                'numero_documento' => $request->document_number,
                'first_name'       => $request->first_name,
                'last_name'        => $request->last_name,
                'email'            => $request->email,
                'phone'            => $request->phone,
                'is_active'        => true,
                'country_code'     => 'PE'
            ]);

            return $user;
        });

        // 3. Login y Token
        Auth::login($user);
        $token = $user->createToken('ecommerce_auth')->plainTextToken;

        return response()->json([
            'message' => 'Cuenta creada correctamente.',
            'user' => $user->load('entity'), // Esto funcionará si tu modelo User tiene la relación 'entity' definida
            'token' => $token
        ]);
    }
}
