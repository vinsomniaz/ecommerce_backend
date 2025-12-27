<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Http\Resources\Roles\RoleResource;
use App\Http\Resources\Roles\RoleCollection;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(
        private RoleService $roleService
    ) {}

    /**
     * List roles with filters
     *
     * @group Roles
     * @queryParam search string Search by role name. Example: admin
     * @queryParam type string Filter by type (system/custom). Example: system
     * @queryParam per_page int Pagination size. Example: 15
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'type',
            'sort_by',
            'sort_order',
        ]);

        $perPage = $request->input('per_page', 15);
        $roles = $this->roleService->getFiltered($filters, $perPage);

        $collection = new RoleCollection($roles);

        return response()->json([
            'success' => true,
            'message' => 'Roles obtenidos correctamente',
            'data' => $collection->toArray($request)['data'],
            'meta' => $collection->with($request)['meta'],
        ]);
    }

    /**
     * Get role details
     *
     * @group Roles
     * @urlParam id int required Role ID. Example: 1
     */
    public function show(int $id): JsonResponse
    {
        try {
            $role = $this->roleService->getById($id);

            return response()->json([
                'success' => true,
                'data' => new RoleResource($role),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Create new role
     *
     * @group Roles
     * @bodyParam name string required Role name. Example: manager
     * @bodyParam permissions array List of permission names. Example: ["products.index", "products.show"]
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            $role = $this->roleService->createRole($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Rol creado exitosamente',
                'data' => new RoleResource($role),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update existing role
     *
     * @group Roles
     * @urlParam id int required Role ID. Example: 1
     * @bodyParam name string Role name. Example: supervisor
     * @bodyParam permissions array List of permission names. Example: ["products.index"]
     */
    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        try {
            $role = $this->roleService->updateRole($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Rol actualizado exitosamente',
                'data' => new RoleResource($role),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a role
     *
     * @group Roles
     * @urlParam id int required Role ID. Example: 5
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->roleService->deleteRole($id);

            return response()->json([
                'success' => true,
                'message' => 'Rol eliminado exitosamente',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get role statistics
     *
     * @group Roles
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->roleService->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get available permissions grouped by module
     *
     * @group Roles
     */
    public function availablePermissions(): JsonResponse
    {
        $permissions = $this->roleService->getAvailablePermissions();

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }
}
