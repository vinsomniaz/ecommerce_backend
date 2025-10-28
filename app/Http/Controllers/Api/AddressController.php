<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Address\StoreAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Models\Entity;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;

class AddressController extends Controller
{
    public function __construct(
        protected AddressService $addressService
    ) {}

    /**
     * Listar direcciones de una entidad
     */
    public function index(Entity $entity): JsonResponse
    {
        $addresses = $this->addressService->listForEntity($entity->id);
        return response()->json([
            'data' => AddressResource::collection($addresses)
        ]);
    }

    /**
     * Crear una nueva dirección para una entidad
     */
    public function store(StoreAddressRequest $request, Entity $entity): JsonResponse
    {
        $address = $this->addressService->createForEntity($entity, $request->validated());

        return response()->json([
            'message' => 'Dirección creada exitosamente',
            'data' => new AddressResource($address)
        ], 201);
    }

    /**
     * Mostrar una dirección específica
     */
    public function show(Address $address): JsonResponse
    {
        return response()->json([
            'data' => new AddressResource($address->load('ubigeoData'))
        ]);
    }

    /**
     * Actualizar una dirección
     */
    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        $address = $this->addressService->update($address, $request->validated());

        return response()->json([
            'message' => 'Dirección actualizada exitosamente',
            'data' => new AddressResource($address)
        ]);
    }

    /**
     * Eliminar una dirección
     */
    public function destroy(Address $address): JsonResponse
    {
        $this->addressService->delete($address);

        return response()->json([
            'message' => 'Dirección eliminada exitosamente'
        ], 200);
    }

    /**
     * Marcar una dirección como predeterminada
     */
    public function setDefault(Address $address): JsonResponse
    {
        $address = $this->addressService->setDefault($address);

        return response()->json([
            'message' => 'Dirección marcada como predeterminada',
            'data' => new AddressResource($address)
        ]);
    }
}