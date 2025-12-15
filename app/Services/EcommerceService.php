<?php
// app/Services/ProductService.php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;


class EcommerceService
{


    /**
     * Obtener productos con filtros (ACTUALIZADO para sistema de lotes)
     */
    public function getFiltered(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::query()->with(['category', 'media', 'firstWarehouseInventory']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('primary_name', 'like', "%{$search}%")
                    ->orWhere('secondary_name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category_id'])) {
            // 1. $filters['category_id'] llega como string '42' o '42,43,44'
            $selectedCategoryIds = explode(',', $filters['category_id']);

            $allCategoryIds = collect();

            // 2. Buscar las categorías y sus descendientes (hijos y nietos)
            // Usamos with('children.children') similar a como lo hace tu CategoryService
            $rootCategories = Category::whereIn('id', $selectedCategoryIds)
                ->with('children.children')
                ->get();

            // 3. Recolectar todos los IDs (padres, hijos y nietos)
            foreach ($rootCategories as $category) {
                // Agregar el ID de la categoría seleccionada (ej. Nivel 2 "Cases")
                $allCategoryIds->push($category->id);

                foreach ($category->children as $child) {
                    // Agregar el ID del hijo (ej. Nivel 3 "Cases ATX")
                    $allCategoryIds->push($child->id);

                    // Agregar los IDs de los nietos (si los hubiera)
                    foreach ($child->children as $grandChild) {
                        $allCategoryIds->push($grandChild->id);
                    }
                }
            }

            // 4. Obtener IDs únicos y aplicar el filtro
            $finalIds = $allCategoryIds->unique()->values();

            if ($finalIds->isNotEmpty()) {
                $query->whereIn('category_id', $finalIds);
            }
        }

        if (!empty($filters['brand'])) {
            $query->where('brand', $filters['brand']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['is_featured'])) {
            $isFeatured = filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_featured', $isFeatured);
        }

        if (isset($filters['visible_online'])) {
            $query->where('visible_online', $filters['visible_online']);
        }

        if (isset($filters['is_new'])) {
            $isNew = filter_var($filters['is_new'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_new', $isNew);
        }

        // Asignamos 0 si min_price no viene (el bug del frontend)
        $minPrice = $filters['min_price'] ?? null;

        // maxPrice puede ser null si el frontend lo omite (cuando es 5000)
        $maxPrice = $filters['max_price'] ?? null;

        if ($minPrice !== null || $maxPrice !== null) {

            // Si solo se envió max_price, minPrice debe ser 0 para el rango
            $minPrice = $minPrice ?? 0;

            // 1. REGLA DE INCLUSIÓN:
            // Debe tener AL MENOS UN inventario DENTRO del rango [min, max].
            $query->whereHas('firstWarehouseInventory', function ($q) use ($minPrice, $maxPrice) {

                // Aplicar filtro de precio mínimo
                $q->where('sale_price', '>=', $minPrice);

                // Aplicar filtro de precio máximo (solo si se proporciona)
                if ($maxPrice !== null) {
                    $q->where('sale_price', '<=', $maxPrice);
                }
            });

            // 2. REGLA DE EXCLUSIÓN:
            // Se aplica solo si se envió un precio máximo.
            if ($maxPrice !== null) {
                // NO DEBE TENER NINGÚN inventario (que no sea 0)
                // por ENCIMA del precio máximo.
                $query->whereDoesntHave('inventory', function ($q) use ($maxPrice) {
                    $q->where('sale_price', '>', $maxPrice)
                        ->where('sale_price', '!=', 0); // Excluimos 0.00
                });
            }
        }

        // Filtrar por almacén específico
        if (!empty($filters['warehouse_id'])) {
            $query->whereHas('inventory', function ($q) use ($filters) {
                $q->where('warehouse_id', $filters['warehouse_id']);
            });
        }

        // Filtrar productos con stock
        if (!empty($filters['with_stock'])) {
            $query->whereHas('inventory', function ($q) {
                $q->where('available_stock', '>', 0);
            });
        }

        // Filtrar productos con stock bajo
        if (!empty($filters['low_stock'])) {
            $query->whereHas('inventory', function ($q) {
                $q->whereColumn('available_stock', '<=', 'products.min_stock');
            });
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        // Validar que el campo de ordenamiento existe
        $allowedSortFields = ['id', 'created_at', 'updated_at', 'primary_name', 'sku', 'brand'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        if (!empty($filters['with_trashed'])) {
            $query->withTrashed();
        }

        return $query->paginate($perPage);
    }



    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Verificar si el producto tiene transacciones
     */
    private function hasTransactions(Product $product): bool
    {
        try {
            // Verificar movimientos de stock
            if (method_exists($product, 'stockMovements') && $product->stockMovements()->exists()) {
                return true;
            }

            // Verificar lotes de compra
            if (method_exists($product, 'purchaseBatches') && $product->purchaseBatches()->exists()) {
                return true;
            }

            // Verificar inventario con stock
            if ($product->inventory()->where('available_stock', '>', 0)->exists()) {
                return true;
            }
        } catch (\Exception $e) {
            // Tabla aún no existe
        }

        return false;
    }

    /**
     * Generar SKU único
     */
    private function generateUniqueSku(): string
    {
        do {
            $sku = 'PRD-' . strtoupper(uniqid());
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }

    /**
     * Establecer valores por defecto
     */
    private function setDefaultValues(array $data): array
    {
        $defaults = [
            'min_stock' => 5,
            'unit_measure' => 'NIU',
            'tax_type' => '10',
            'is_active' => true,
            'is_featured' => false,
            'visible_online' => true,
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($data[$key])) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Formatear bytes
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Obtener lista de distribución completa (SIN PAGINACIÓN)
     * Solo productos con distribution_price > 0
     */
    public function getDistributionList(array $filters): Collection
    {
        $query = Product::query()
            ->with(['category', 'media', 'firstWarehouseInventory'])
            // Filtrar productos que tengan precio en la lista de distribuidor (ID 3)
            ->whereHas('productPrices', function ($q) {
                $q->where('price_list_id', 3)
                    ->where('is_active', true)
                    ->where('valid_from', '<=', now())
                    ->where(function ($subQ) {
                        $subQ->whereNull('valid_to')
                            ->orWhere('valid_to', '>=', now());
                    });
            })
            ->with(['productPrices' => function ($q) {
                $q->where('price_list_id', 3)
                    ->where('is_active', true)
                    ->where('valid_from', '<=', now())
                    ->where(function ($subQ) {
                        $subQ->whereNull('valid_to')
                            ->orWhere('valid_to', '>=', now());
                    });
            }])
            ->where('products.is_active', true);

        // --- Filtros (Se mantienen igual) ---

        // 1. Búsqueda
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('products.primary_name', 'like', "%{$search}%")
                    ->orWhere('products.secondary_name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%")
                    ->orWhere('products.brand', 'like', "%{$search}%")
                    ->orWhere('products.barcode', 'like', "%{$search}%");
            });
        }

        // 2. Categoría (Lógica recursiva)
        if (!empty($filters['category_id'])) {
            $selectedCategoryIds = explode(',', $filters['category_id']);
            $allCategoryIds = collect();

            $rootCategories = Category::whereIn('id', $selectedCategoryIds)
                ->with('children.children')
                ->get();

            foreach ($rootCategories as $category) {
                $allCategoryIds->push($category->id);
                foreach ($category->children as $child) {
                    $allCategoryIds->push($child->id);
                    foreach ($child->children as $grandChild) {
                        $allCategoryIds->push($grandChild->id);
                    }
                }
            }

            $finalIds = $allCategoryIds->unique()->values();
            if ($finalIds->isNotEmpty()) {
                $query->whereIn('category_id', $finalIds);
            }
        }

        // 3. Ordenamiento
        $sortBy = $filters['sort_by'] ?? 'primary_name';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        if ($sortBy === 'price') {
            // Unir con product_prices para ordenar por precio
            $query->join('product_prices', 'products.id', '=', 'product_prices.product_id')
                ->where('product_prices.price_list_id', 3)
                ->where('product_prices.is_active', true)
                ->orderBy('product_prices.price', $sortOrder)
                ->select('products.*'); // Importante para no traer campos de product_prices
        } else {
            $allowedSorts = ['primary_name', 'sku', 'brand', 'created_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder);
            }
        }

        // RETORNAR COLECCIÓN COMPLETA
        return $query->get();
    }
}
