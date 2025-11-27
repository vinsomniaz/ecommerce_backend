<?php

namespace App\Http\Controllers\Auth;

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
     * Listar todos los permisos disponibles agrupados por módulo
     *
     * @group Permisos
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::all()->groupBy(function ($permission) {
            // Agrupar por módulo (primera parte antes del punto)
            return explode('.', $permission->name)[0];
        });

        // 🔥 Marcar permisos peligrosos
        $dangerousPermissions = $this->permissionService->getDangerousPermissions();

        $permissionsWithMeta = Permission::all()->map(function ($permission) use ($dangerousPermissions) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name,
                'description' => $permission->description,
                'module' => $permission->module,
                'is_dangerous' => in_array($permission->name, $dangerousPermissions),
            ];
        })->groupBy('module');

        return response()->json([
            'success' => true,
            'data' => $permissionsWithMeta,
            'meta' => [
                'total_permissions' => Permission::count(),
                'total_modules' => $permissionsWithMeta->count(),
            ],
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
                'user_id' => $userId,
                'role_permissions' => $permissions['from_role'],
                'direct_permissions' => $permissions['direct'],
                'all_permissions' => $permissions['all'],
                'total_role' => count($permissions['from_role']),
                'total_direct' => count($permissions['direct']),
                'total_all' => count($permissions['all']),
            ],
        ]);
    }

    /**
     * Asignar permisos personalizados a un usuario
     *
     * @group Permisos
     * @urlParam userId integer required ID del usuario. Example: 1
     * @bodyParam permissions array required Lista de permisos. Example: ["inventory.store", "products.update"]
     */
    public function assignToUser(AssignPermissionsRequest $request, int $userId): JsonResponse
    {
        $permissions = $request->validated('permissions');

        // 🔥 Validar que no se asignen permisos peligrosos sin ser super-admin
        try {
            $this->permissionService->validateSafePermissionAssignment(
                $request->user(),
                $permissions
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }

        $user = $this->permissionService->assignPermissionsToUser($userId, $permissions);

        return response()->json([
            'success' => true,
            'message' => 'Permisos asignados exitosamente',
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->full_name,
                'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
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
                'remaining_direct_permissions' => $user->getDirectPermissions()->pluck('name'),
                'remaining_all_permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * Sincronizar permisos de un usuario (reemplaza todos los directos)
     *
     * @group Permisos
     * @urlParam userId integer required ID del usuario. Example: 1
     * @bodyParam permissions array required Lista completa de permisos. Example: ["inventory.store", "products.show"]
     */
    public function syncUserPermissions(AssignPermissionsRequest $request, int $userId): JsonResponse
    {
        $permissions = $request->validated('permissions');

        // 🔥 Validar permisos peligrosos
        try {
            $this->permissionService->validateSafePermissionAssignment(
                $request->user(),
                $permissions
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }

        $user = $this->permissionService->syncPermissionsForUser($userId, $permissions);

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
        $dangerousPermissions = $this->permissionService->getDangerousPermissions();

        // 🔥 Marcar cuáles son peligrosos
        $suggestionsWithMeta = collect($suggestions)->map(function ($permission) use ($dangerousPermissions) {
            $permissionData = Permission::where('name', $permission)->first();

            return [
                'name' => $permission,
                'display_name' => $permissionData?->display_name,
                'description' => $permissionData?->description,
                'is_dangerous' => in_array($permission, $dangerousPermissions),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'suggested_permissions' => $suggestionsWithMeta,
                'total_suggestions' => count($suggestions),
                'warning' => 'Estos son permisos sugeridos para escalar privilegios. Algunos pueden ser críticos.',
            ],
        ]);
    }

    /**
     * 🔥 NUEVO: Obtener lista de permisos peligrosos
     *
     * @group Permisos
     */
    public function getDangerousPermissions(): JsonResponse
    {
        $dangerousPermissions = $this->permissionService->getDangerousPermissions();

        $permissionsWithDetails = Permission::whereIn('name', $dangerousPermissions)
            ->get()
            ->map(function ($permission) {
                return [
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                    'description' => $permission->description,
                    'module' => $permission->module,
                    'risk_level' => $this->getRiskLevel($permission->name),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $permissionsWithDetails,
            'meta' => [
                'total' => count($dangerousPermissions),
                'warning' => 'Estos permisos solo deben ser asignados por super-admin con extrema precaución.',
            ],
        ]);
    }

    /**
     * Determinar nivel de riesgo de un permiso
     */
    private function getRiskLevel(string $permission): string
    {
        return match (true) {
            str_contains($permission, 'destroy') => 'critical',
            str_contains($permission, 'permissions.') => 'critical',
            str_contains($permission, 'all-warehouses') => 'high',
            str_contains($permission, 'sync') => 'high',
            str_contains($permission, 'change-role') => 'high',
            default => 'medium',
        };
    }
}
