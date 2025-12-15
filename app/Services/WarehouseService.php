<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Models\Ubigeo;
use App\Exceptions\Warehouses\WarehouseException;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class WarehouseService
{
    /**
     * Listar almacenes con filtros opcionales y cache
     *
     * @param array $filters
     *  - is_active: bool
     *  - visible_online: bool
     *  - is_main: bool
     *  - warehouse_ids: array (filtrar por IDs específicos)
     */
    public function list(array $filters = []): Collection
    {
        $version = Cache::remember('warehouses_version', now()->addDay(), fn() => 1);

        $cacheKey = "warehouses_v{$version}_" . md5(serialize($filters));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($filters) {
            $query = Warehouse::with('ubigeoData');

            // Restricción por IDs de almacenes accesibles
            if (isset($filters['warehouse_ids']) && is_array($filters['warehouse_ids'])) {
                $query->whereIn('id', $filters['warehouse_ids']);
            }

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
        });
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
            $this->validateUbigeo($data['ubigeo']);
            $this->validateUniqueName($data['name']);

            if ($data['is_main'] ?? false) {
                $this->unsetCurrentMainWarehouse();
            }

            $warehouse = Warehouse::create($data);
            $warehouse->load('ubigeoData');

            if ($data['is_active'] ?? true) {
                $this->assignAllProductsToWarehouse($warehouse);
            }

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
            $wasInactive = !$warehouse->is_active;

            if (isset($data['ubigeo']) && $data['ubigeo'] !== $warehouse->ubigeo) {
                $this->validateUbigeo($data['ubigeo']);
            }

            if (isset($data['name']) && $data['name'] !== $warehouse->name) {
                $this->validateUniqueName($data['name'], $id);
            }

            if (isset($data['is_main']) && $data['is_main'] && !$warehouse->is_main) {
                $this->unsetCurrentMainWarehouse($id);
            }

            $warehouse->update($data);
            $warehouse->refresh();
            $warehouse->load('ubigeoData');

            if ($wasInactive && ($data['is_active'] ?? false)) {
                $this->assignAllProductsToWarehouse($warehouse);
            }

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
     * Asignar todos los productos al almacén nuevo
     */
    private function assignAllProductsToWarehouse(Warehouse $warehouse): void
    {
        $products = Product::select('id')->get();

        if ($products->isEmpty()) {
            Log::info("No hay productos para asignar al almacén #{$warehouse->id}");
            return;
        }

        $inventoryData = [];

        foreach ($products as $product) {
            $exists = Inventory::where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->exists();

            if (!$exists) {
                $inventoryData[] = [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'available_stock' => 0,
                    'reserved_stock' => 0,
                    'average_cost' => 0.00,
                    'last_movement_at' => null,
                    'price_updated_at' => null,
                ];
            }
        }

        if (!empty($inventoryData)) {
            Inventory::insert($inventoryData);
            Log::info("Almacén #{$warehouse->id} asignado a " . count($inventoryData) . " productos");
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

    /**
     * Estadísticas globales de almacenes con cache
     */
    public function getGlobalStatistics(): array
    {
        $version = Cache::remember('warehouses_version', now()->addDay(), fn() => 1);
        $key = "warehouses_global_stats_v{$version}";

        return Cache::remember($key, now()->addMinutes(5), function () {
            $mainWarehouse = Warehouse::where('is_main', true)->first();

            return [
                'total_warehouses' => Warehouse::count(),
                'active_warehouses' => Warehouse::where('is_active', true)->count(),
                'inactive_warehouses' => Warehouse::where('is_active', false)->count(),
                'visible_online' => Warehouse::where('visible_online', true)->count(),
                'with_inventory' => Warehouse::whereHas('inventories')->count(),
                'main_warehouse' => $mainWarehouse ? [
                    'id' => $mainWarehouse->id,
                    'name' => $mainWarehouse->name,
                ] : null,
            ];
        });
    }

    /**
     * Limpiar cache de almacenes
     */
    public function clearCache(): void
    {
        Cache::forget('warehouses_version');
        Cache::put('warehouses_version', now()->timestamp, now()->addDay());
    }
}
