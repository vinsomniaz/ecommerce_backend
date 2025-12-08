<?php
// app/Services/PriceListService.php

namespace App\Services;

use App\Models\PriceList;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PriceListService
{
    /**
     * Obtener todas las listas de precios con paginación
     */
    public function getAllPriceLists(
        int $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null
    ): LengthAwarePaginator {
        $query = PriceList::query()
            ->withCount('productPrices');

        // Búsqueda por nombre o código
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filtrar por estado
        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        return $query->orderBy('name', 'asc')
            ->paginate($perPage);
    }

    /**
     * Obtener solo listas de precios activas
     */
    public function getActivePriceLists(): Collection
    {
        return PriceList::active()
            ->orderBy('name', 'asc')
            ->get(['id', 'code', 'name', 'description']);
    }

    /**
     * Obtener estadísticas de listas de precios
     */
    public function getStatistics(): array
    {
        $totalLists = PriceList::count();
        $activeLists = PriceList::where('is_active', true)->count();
        $inactiveLists = PriceList::where('is_active', false)->count();

        // Obtener la lista con más productos
        $mostUsedList = PriceList::withCount('productPrices')
            ->orderBy('product_prices_count', 'desc')
            ->first();

        return [
            'total_lists' => $totalLists,
            'active_lists' => $activeLists,
            'inactive_lists' => $inactiveLists,
            'most_used_list' => $mostUsedList ? [
                'id' => $mostUsedList->id,
                'name' => $mostUsedList->name,
                'products_count' => $mostUsedList->product_prices_count,
            ] : null,
        ];
    }

    /**
     * Crear una nueva lista de precios
     */
    public function createPriceList(array $data): PriceList
    {
        return DB::transaction(function () use ($data) {
            return PriceList::create($data);
        });
    }

    /**
     * Obtener una lista de precios por ID
     */
    public function getPriceListById(int $id): ?PriceList
    {
        return PriceList::withCount('productPrices')->find($id);
    }

    /**
     * Actualizar una lista de precios
     */
    public function updatePriceList(int $id, array $data): ?PriceList
    {
        return DB::transaction(function () use ($id, $data) {
            $priceList = PriceList::find($id);

            if (!$priceList) {
                return null;
            }

            $priceList->update($data);
            return $priceList->fresh();
        });
    }

    /**
     * Eliminar una lista de precios
     */
    public function deletePriceList(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $priceList = PriceList::find($id);

            if (!$priceList) {
                return [
                    'success' => false,
                    'message' => 'Lista de precios no encontrada',
                    'code' => 404,
                ];
            }

            // Verificar si tiene precios asociados
            $hasProducts = $priceList->productPrices()->exists();

            if ($hasProducts) {
                return [
                    'success' => false,
                    'message' => 'No se puede eliminar la lista porque tiene productos con precios asociados',
                    'code' => 400,
                ];
            }

            $priceList->delete();

            return [
                'success' => true,
                'message' => 'Lista de precios eliminada correctamente',
            ];
        });
    }

    /**
     * Activar/Desactivar lista de precios
     */
    public function toggleStatus(int $id): ?PriceList
    {
        return DB::transaction(function () use ($id) {
            $priceList = PriceList::find($id);

            if (!$priceList) {
                return null;
            }

            $priceList->update([
                'is_active' => !$priceList->is_active,
            ]);

            return $priceList->fresh();
        });
    }

    /**
     * Obtener productos con precios de una lista específica
     */
    public function getProductsWithPrices(int $priceListId, int $perPage = 15): ?LengthAwarePaginator
    {
        $priceList = PriceList::find($priceListId);

        if (!$priceList) {
            return null;
        }

        return $priceList->productPrices()
            ->with(['product' => function ($query) {
                $query->select('id', 'sku', 'primary_name', 'secondary_name', 'is_active');
            }])
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Verificar si un código ya existe
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $query = PriceList::where('code', $code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Obtener lista de precios por código
     */
    public function getPriceListByCode(string $code): ?PriceList
    {
        return PriceList::where('code', $code)->first();
    }
}
