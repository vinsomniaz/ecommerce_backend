<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entity\StoreEntityRequest;
use App\Http\Requests\Entity\UpdateEntityRequest;
use App\Http\Resources\EntityResource;
use App\Models\Entity;
use App\Services\EntityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EntityController extends Controller
{
    public function __construct(
        protected EntityService $entityService
    ) {}

    /**
     * Display a listing of entities.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'type' => $request->get('type'),
            'search' => $request->get('search'),
            'tipo_documento' => $request->get('tipo_documento'),
            'is_active' => $request->get('is_active'),
            'registered_from' => $request->get('registered_from'),
            'registered_to' => $request->get('registered_to'),
            'per_page' => $request->get('per_page', 50),
            'with' => ['defaultAddress.ubigeoData', 'user'], // Incluir relaciones anidadas
        ];

        // Remove null values
        $filters = array_filter($filters, fn($value) => $value !== null);

        $entities = $this->entityService->getAll($filters);

        return EntityResource::collection($entities);
    }

    /**
     * Store a newly created entity.
     */
    public function store(StoreEntityRequest $request): JsonResponse
    {
        $entity = $this->entityService->create($request->validated());

        return response()->json([
            'message' => 'Cliente creado exitosamente',
            'data' => new EntityResource($entity)
        ], 201);
    }

    /**
     * Display the specified entity.
     */
    public function show(int $id): JsonResponse
    {
        $entity = $this->entityService->findById($id, ['user', 'ubigeoData', 'defaultAddress.ubigeoData']);

        if (!$entity) {
            return response()->json([
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        return response()->json([
            'data' => new EntityResource($entity)
        ]);
    }

    /**
     * Update the specified entity.
     */
    public function update(UpdateEntityRequest $request, Entity $entity): JsonResponse
    {
        $entity = $this->entityService->update($entity, $request->validated());

        return response()->json([
            'message' => 'Cliente actualizado exitosamente',
            'data' => new EntityResource($entity)
        ]);
    }

    /**
     * Remove the specified entity.
     */
    public function destroy(Entity $entity): JsonResponse
    {
        $this->entityService->delete($entity);

        return response()->json([
            'message' => 'Cliente eliminado (desactivado) exitosamente'
        ], 200);
    }

    /**
     * Deactivate the specified entity.
     */
    public function deactivate(Entity $entity): JsonResponse
    {
        $entity = $this->entityService->deactivate($entity);

        return response()->json([
            'message' => 'Cliente desactivado exitosamente',
            'data' => new EntityResource($entity)
        ]);
    }

    /**
     * Reactivate the specified entity.
     */
    public function activate(Entity $entity): JsonResponse
    {
        $entity = $this->entityService->activate($entity);

        return response()->json([
            'message' => 'Cliente activado exitosamente',
            'data' => new EntityResource($entity)
        ]);
    }
}