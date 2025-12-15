<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entity\StoreEntityRequest;
use App\Http\Requests\Entity\UpdateEntityRequest;
use App\Http\Resources\EntityResource;
use App\Http\Resources\EntityCollection;
use App\Models\Entity;
use App\Services\EntityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntityController extends Controller
{
    public function __construct(
        protected EntityService $entityService
    ) {}

    /**
     * Display a listing of entities with search integrated.
     *
     * @group Entities
     * @queryParam per_page int Cantidad por página. Default: 50
     * @queryParam search string Buscar por nombre, documento, email
     * @queryParam type string Filtrar por tipo (customer, supplier, both)
     * @queryParam tipo_documento string Filtrar por tipo de documento
     * @queryParam is_active boolean Filtrar por estado activo/inactivo
     * @queryParam registered_from date Filtrar desde fecha de registro
     * @queryParam registered_to date Filtrar hasta fecha de registro
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'type' => $request->get('type'),
            'search' => $request->get('search'), // 🔥 BÚSQUEDA INTEGRADA
            'tipo_documento' => $request->get('tipo_documento'),
            'is_active' => $request->get('is_active'),
            'registered_from' => $request->get('registered_from'),
            'registered_to' => $request->get('registered_to'),
            'per_page' => $request->get('per_page', 50),
            'with' => ['primaryAddress.ubigeoData', 'primaryContact', 'user', 'documentType'],
        ];

        // Remove null values
        $filters = array_filter($filters, fn($value) => $value !== null);

        $entities = $this->entityService->getAll($filters);

        // Instanciar EntityCollection
        $collection = new EntityCollection($entities);

        if ($entities->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Aún no se ha creado ninguna entidad',
                'data' => [],
                'meta' => $collection->with($request)['meta'],
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Entidades obtenidas correctamente',
            'data' => $collection->toArray($request)['data'],
            'meta' => $collection->with($request)['meta'],
        ], 200);
    }

    /**
     * Store a newly created entity.
     *
     * @group Entities
     */
    public function store(StoreEntityRequest $request): JsonResponse
    {
        $entity = $this->entityService->create($request->validated());

        // Mensaje dinámico según el tipo de entidad
        $message = match($entity->type) {
            'customer' => 'Cliente creado exitosamente',
            'supplier' => 'Proveedor creado exitosamente',
            'both' => 'Cliente y Proveedor creado exitosamente',
            default => 'Entidad creada exitosamente',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new EntityResource($entity)
        ], 201);
    }

    /**
     * Display the specified entity.
     *
     * @group Entities
     */
    public function show(int $id): JsonResponse
    {
        $entity = $this->entityService->findById($id, [
            'user',
            'primaryAddress.ubigeoData',
            'primaryContact',
            'documentType',
            'addresses',
            'contacts'
        ]);

        if (!$entity) {
            return response()->json([
                'success' => false,
                'message' => 'Entidad no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Entidad obtenida correctamente',
            'data' => new EntityResource($entity)
        ], 200);
    }

    /**
     * Update the specified entity.
     *
     * @group Entities
     */
    public function update(UpdateEntityRequest $request, Entity $entity): JsonResponse
    {
        $entity = $this->entityService->update($entity, $request->validated());

        // Mensaje dinámico según el tipo de entidad
        $message = match($entity->type) {
            'customer' => 'Cliente actualizado exitosamente',
            'supplier' => 'Proveedor actualizado exitosamente',
            'both' => 'Cliente y Proveedor actualizado exitosamente',
            default => 'Entidad actualizada exitosamente',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new EntityResource($entity)
        ], 200);
    }

    /**
     * Remove the specified entity (soft delete).
     *
     * @group Entities
     */
    public function destroy(Entity $entity): JsonResponse
    {
        $type = $entity->type;
        $this->entityService->delete($entity);

        // Mensaje dinámico según el tipo de entidad
        $message = match($type) {
            'customer' => 'Cliente eliminado exitosamente',
            'supplier' => 'Proveedor eliminado exitosamente',
            'both' => 'Cliente y Proveedor eliminado exitosamente',
            default => 'Entidad eliminada exitosamente',
        };

        return response()->json([
            'success' => true,
            'message' => $message
        ], 200);
    }

    /**
     * Restore a soft-deleted entity.
     *
     * @group Entities
     */
    public function restore(int $id): JsonResponse
    {
        $entity = $this->entityService->restore($id);

        if (!$entity) {
            return response()->json([
                'success' => false,
                'message' => 'Entidad no encontrada o no está eliminada'
            ], 404);
        }

        // Mensaje dinámico según el tipo de entidad
        $message = match($entity->type) {
            'customer' => 'Cliente restaurado exitosamente',
            'supplier' => 'Proveedor restaurado exitosamente',
            'both' => 'Cliente y Proveedor restaurado exitosamente',
            default => 'Entidad restaurada exitosamente',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new EntityResource($entity)
        ], 200);
    }

    /**
     * Deactivate the specified entity.
     *
     * @group Entities
     */
    public function deactivate(Entity $entity): JsonResponse
    {
        $entity = $this->entityService->deactivate($entity);

        // Mensaje dinámico según el tipo de entidad
        $message = match($entity->type) {
            'customer' => 'Cliente desactivado exitosamente',
            'supplier' => 'Proveedor desactivado exitosamente',
            'both' => 'Cliente y Proveedor desactivado exitosamente',
            default => 'Entidad desactivada exitosamente',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new EntityResource($entity)
        ], 200);
    }

    /**
     * Reactivate the specified entity.
     *
     * @group Entities
     */
    public function activate(Entity $entity): JsonResponse
    {
        $entity = $this->entityService->activate($entity);

        // Mensaje dinámico según el tipo de entidad
        $message = match($entity->type) {
            'customer' => 'Cliente activado exitosamente',
            'supplier' => 'Proveedor activado exitosamente',
            'both' => 'Cliente y Proveedor activado exitosamente',
            default => 'Entidad activada exitosamente',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new EntityResource($entity)
        ], 200);
    }

    /**
     * Find entity by document number
     * GET /api/entities/find-by-document?tipo_documento=01&numero_documento=12345678
     *
     * @group Entities
     */
    public function findByDocument(Request $request): JsonResponse
    {
        $tipoDocumento = $request->get('tipo_documento');
        $numeroDocumento = $request->get('numero_documento');

        if (empty($tipoDocumento) || empty($numeroDocumento)) {
            return response()->json([
                'success' => false,
                'message' => 'Debe proporcionar tipo_documento y numero_documento',
            ], 400);
        }

        $entity = Entity::where('tipo_documento', $tipoDocumento)
            ->where('numero_documento', $numeroDocumento)
            ->with(['primaryAddress.ubigeoData', 'primaryContact', 'documentType'])
            ->first();

        if (!$entity) {
            return response()->json([
                'success' => false,
                'message' => 'Entidad no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Entidad encontrada',
            'data' => new EntityResource($entity),
        ], 200);
    }

    /**
     * Get global statistics for entities
     * GET /api/entities/statistics/global
     *
     * @group Entities
     */
    public function globalStatistics(): JsonResponse
    {
        $stats = $this->entityService->getGlobalStatistics();

        return response()->json([
            'success' => true,
            'message' => 'Estadísticas obtenidas correctamente',
            'data' => $stats,
        ], 200);
    }
}
