<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Models\Ubigeo;
use App\Exceptions\Warehouses\WarehouseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class WarehouseService
{
    /**
     * Listar todos los almacenes con filtros opcionales
     */
    public function list(array $filters = []): Collection
    {
        $query = Warehouse::with('ubigeoData');

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['visible_online'])) {
            $query->where('visible_online', $filters['visible_online']);
        }

        if (isset($filters['is_main'])) {
            $query->where('is_main', $filters['is_main']);
        }

        return $query->orderedByPriority()->get();
    }

    /**
     * Obtener almacén por ID
     */
    public function getById(int $id): Warehouse
    {
        $warehouse = Warehouse::with('ubigeoData')->find($id);

        if (!$warehouse) {
            throw WarehouseException::notFound($id);
        }

        return $warehouse;
    }

    /**
     * Crear nuevo almacén
     */
    public function create(array $data): Warehouse
    {
        return DB::transaction(function () use ($data) {
            // Validar que el ubigeo exista
            $this->validateUbigeo($data['ubigeo']);

            // Validar nombre único
            $this->validateUniqueName($data['name']);

            // Manejar lógica de almacén principal
            if ($data['is_main'] ?? false) {
                $this->unsetCurrentMainWarehouse();
            }

            $warehouse = Warehouse::create($data);
            $warehouse->load('ubigeoData');

            return $warehouse;
        });
    }

    /**
     * Actualizar almacén
     */
    public function update(int $id, array $data): Warehouse
    {
        return DB::transaction(function () use ($id, $data) {
            $warehouse = $this->getById($id);

            // Validar ubigeo si cambió
            if (isset($data['ubigeo']) && $data['ubigeo'] !== $warehouse->ubigeo) {
                $this->validateUbigeo($data['ubigeo']);
            }

            // Validar nombre único si cambió
            if (isset($data['name']) && $data['name'] !== $warehouse->name) {
                $this->validateUniqueName($data['name'], $id);
            }

            // Manejar lógica de almacén principal
            if (isset($data['is_main']) && $data['is_main'] && !$warehouse->is_main) {
                $this->unsetCurrentMainWarehouse($id);
            }

            $warehouse->update($data);
            $warehouse->refresh();
            $warehouse->load('ubigeoData');

            return $warehouse;
        });
    }

    /**
     * Eliminar almacén
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $warehouse = $this->getById($id);

            // Verificar si el almacén tiene inventario
            if ($warehouse->hasInventory()) {
                throw WarehouseException::cannotDeleteWithInventory($id);
            }

            return $warehouse->delete();
        });
    }

    /**
     * Validar que el ubigeo exista
     */
    private function validateUbigeo(string $ubigeo): void
    {
        if (!Ubigeo::where('ubigeo', $ubigeo)->exists()) {
            throw WarehouseException::invalidUbigeo($ubigeo);
        }
    }

    /**
     * Validar nombre único de almacén
     */
    private function validateUniqueName(string $name, ?int $excludeId = null): void
    {
        $query = Warehouse::where('name', $name);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw WarehouseException::duplicateName($name);
        }
    }

    /**
     * Desmarcar almacén principal actual
     */
    private function unsetCurrentMainWarehouse(?int $excludeId = null): void
    {
        $query = Warehouse::where('is_main', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $query->update(['is_main' => false]);
    }

    /**
     * Obtener almacén principal
     */
    public function getMainWarehouse(): ?Warehouse
    {
        return Warehouse::main()->with('ubigeoData')->first();
    }
}
