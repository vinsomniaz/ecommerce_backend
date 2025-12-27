<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\Users\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Listar usuarios con filtros
     *
     * @group Usuarios
     * @queryParam search string Buscar por nombre, email o teléfono. Example: Juan
     * @queryParam role string Filtrar por rol. Example: admin
     * @queryParam is_active boolean Filtrar por estado activo. Example: 1
     * @queryParam warehouse_id integer Filtrar por almacén. Example: 1
     * @queryParam sort_by string Campo de ordenamiento. Example: created_at
     * @queryParam sort_order string Dirección de orden (asc/desc). Example: desc
     * @queryParam per_page integer Cantidad por página. Example: 15
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'role',
            'is_active',
            'warehouse_id',
            'sort_by',
            'sort_order',
        ]);

        $perPage = $request->input('per_page', 15);

        $users = $this->userService->getFiltered($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Get user statistics
     *
     * @group Usuarios
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->userService->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Crear nuevo usuario
     *
     * @group Usuarios
     * @bodyParam first_name string required Nombre. Example: Juan
     * @bodyParam last_name string required Apellido. Example: Pérez
     * @bodyParam email string optional Email del usuario. Example: juan@example.com
     * @bodyParam cellphone string optional Teléfono celular. Example: 987654321
     * @bodyParam password string required Contraseña (mínimo 8 caracteres). Example: password123
     * @bodyParam role string required Rol del usuario. Example: admin
     * @bodyParam is_active boolean optional Estado activo. Example: true
     * @bodyParam warehouse_id integer optional ID del almacén asignado. Example: 1
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Obtener detalle de usuario
     *
     * @group Usuarios
     * @urlParam id integer required ID del usuario. Example: 1
     */
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->getById($id);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Actualizar usuario
     *
     * @group Usuarios
     * @urlParam id integer required ID del usuario. Example: 1
     * @bodyParam first_name string optional Nombre. Example: Juan Carlos
     * @bodyParam last_name string optional Apellido. Example: Pérez García
     * @bodyParam email string optional Email del usuario. Example: juancarlos@example.com
     * @bodyParam cellphone string optional Teléfono celular. Example: 987654321
     * @bodyParam password string optional Nueva contraseña. Example: newpassword123
     * @bodyParam role string optional Rol del usuario. Example: vendor
     * @bodyParam is_active boolean optional Estado activo. Example: false
     * @bodyParam warehouse_id integer optional ID del almacén asignado. Example: 2
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->userService->updateUser($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Eliminar usuario (soft delete)
     *
     * @group Usuarios
     * @urlParam id integer required ID del usuario. Example: 1
     */
    public function destroy(int $id): JsonResponse
    {
        $this->userService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente',
        ]);
    }

    /**
     * Restaurar usuario eliminado
     *
     * @group Usuarios
     * @urlParam id integer required ID del usuario. Example: 1
     */
    public function restore(int $id): JsonResponse
    {
        $user = $this->userService->restore($id);

        return response()->json([
            'success' => true,
            'message' => 'Usuario restaurado exitosamente',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Cambiar estado activo/inactivo
     *
     * @group Usuarios
     * @urlParam id integer required ID del usuario. Example: 1
     * @bodyParam is_active boolean required Nuevo estado. Example: false
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user = $this->userService->toggleActive($id, $request->is_active);

        return response()->json([
            'success' => true,
            'message' => 'Estado del usuario actualizado',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Cambiar rol del usuario
     *
     * @group Usuarios
     * @urlParam id integer required ID del usuario. Example: 1
     * @bodyParam role string required Nuevo rol. Example: admin
     */
    public function changeRole(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'role' => ['required', 'string', 'in:super-admin,admin,vendor,customer'],
        ]);

        $user = $this->userService->changeRole($id, $request->role);

        return response()->json([
            'success' => true,
            'message' => 'Rol actualizado exitosamente',
            'data' => new UserResource($user),
        ]);
    }
}
