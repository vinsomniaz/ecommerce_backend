<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Permissions\AssignPermissionsRequest;
use App\Http\Resources\Permissions\PermissionResource;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct(
        private PermissionService $permissionService
    ) {
        // Solo super-admin puede gestionar permisos
        $this->middleware('role:super-admin');
    }

    /**
     * Listar todos los permisos disponibles
     *
     * @group Permisos
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::all()->groupBy(function ($permission) {
            // Agrupar por módulo (primera parte antes del punto)
            return explode('.', $permission->name)[0];
        });

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Obtener permisos de un usuario específico
     *
     * @group Permisos
     * @urlParam userId integer required ID del usuario. Example: 1
     */
    public function getUserPermissions(int $userId): JsonResponse
    {
        $permissions = $this->permissionService->getUserPermissions($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'role_permissions' => $permissions['from_role'],
                'direct_permissions' => $permissions['direct'],
                'all_permissions' => $permissions['all'],
            ],
        ]);
    }

    /**
     * Asignar permisos personalizados a un usuario
     *
     * @group Permisos
     * @urlParam userId integer required ID del usuario. Example: 1
     * @bodyParam permissions array required Lista de permisos. Example: ["inventory.view.all-warehouses", "sales.create.all-warehouses"]
     */
    public function assignToUser(AssignPermissionsRequest $request, int $userId): JsonResponse
    {
        $user = $this->permissionService->assignPermissionsToUser(
            $userId,
            $request->validated('permissions')
        );

        return response()->json([
            'success' => true,
            'message' => 'Permisos asignados exitosamente',
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->full_name,
                'all_permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * Remover permisos directos de un usuario
     *
     * @group Permisos
     * @urlParam userId integer required ID del usuario. Example: 1
     * @bodyParam permissions array required Lista de permisos a remover. Example: ["inventory.view.all-warehouses"]
     */
    public function revokeFromUser(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required', 'string', 'exists:permissions,name'],
        ]);

        $user = $this->permissionService->revokePermissionsFromUser(
            $userId,
            $request->permissions
        );

        return response()->json([
            'success' => true,
            'message' => 'Permisos revocados exitosamente',
            'data' => [
                'user_id' => $user->id,
                'remaining_permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * Sincronizar permisos de un usuario (reemplaza todos los directos)
     *
     * @group Permisos
     * @urlParam userId integer required ID del usuario. Example: 1
     * @bodyParam permissions array required Lista completa de permisos. Example: ["sales.view.all-warehouses"]
     */
    public function syncUserPermissions(AssignPermissionsRequest $request, int $userId): JsonResponse
    {
        $user = $this->permissionService->syncPermissionsForUser(
            $userId,
            $request->validated('permissions')
        );

        return response()->json([
            'success' => true,
            'message' => 'Permisos sincronizados exitosamente',
            'data' => [
                'user_id' => $user->id,
                'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
                'all_permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * Obtener permisos sugeridos para un rol
     *
     * @group Permisos
     * @urlParam role string required Nombre del rol. Example: vendor
     */
    public function getSuggestedPermissions(string $role): JsonResponse
    {
        $suggestions = $this->permissionService->getSuggestedPermissionsForRole($role);

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'suggested_permissions' => $suggestions,
            ],
        ]);
    }
}
