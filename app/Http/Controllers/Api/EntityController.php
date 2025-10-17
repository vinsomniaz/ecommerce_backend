<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entity\StoreEntityRequest;
use App\Http\Requests\Entity\UpdateEntityRequest;
use App\Http\Resources\EntityResource;
use App\Models\Entity;
use App\Http\Services\EntityService;
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
            'tipo_persona' => $request->get('tipo_persona'),
            'tipo_documento' => $request->get('tipo_documento'),
            'is_active' => $request->get('is_active'),
            'estado_sunat' => $request->get('estado_sunat'),
            'condicion_sunat' => $request->get('condicion_sunat'),
            'ubigeo' => $request->get('ubigeo'),
            'order_by' => $request->get('order_by'),
            'order_direction' => $request->get('order_direction', 'asc'),
            'per_page' => $request->get('per_page', 15),
            'with' => $request->get('with') ? explode(',', $request->get('with')) : [],
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
        $entity = $this->entityService->findById($id, ['user', 'ubigeoData']);

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
            'message' => 'Cliente eliminado exitosamente'
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

    /**
     * Search entities.
     */
    public function search(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => 'required|string|min:2',
            'type' => 'nullable|in:customer,supplier,both',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = [
            'type' => $request->get('type'),
            'per_page' => $request->get('per_page', 15),
        ];

        // Remove null values
        $filters = array_filter($filters, fn($value) => $value !== null);

        $entities = $this->entityService->search($request->get('q'), $filters);

        return EntityResource::collection($entities);
    }

    /**
     * Find entity by document.
     */
    public function findByDocument(Request $request): JsonResponse
    {
        $request->validate([
            'tipo_documento' => 'required|in:01,06',
            'numero_documento' => 'required|string',
        ]);

        $entity = $this->entityService->findByDocument(
            $request->tipo_documento,
            $request->numero_documento
        );

        if (!$entity) {
            return response()->json([
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        return response()->json([
            'data' => new EntityResource($entity)
        ]);
    }
}