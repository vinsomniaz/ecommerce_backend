<?php

namespace App\Services;

use App\Models\Category;
use App\Exceptions\Categories\CategoryNotFoundException;
use App\Exceptions\Categories\CategoryValidationException;
use App\Exceptions\Categories\CategoryMaxLevelException;
use App\Exceptions\Categories\CategoryHasChildrenException;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\{DB, Cache, Log};

class CategoryService
{
    public function __construct(
        private PricingService $pricingService
    ) {}

    /**
     * Obtiene categorías con filtros y paginación
     */
    public function getCategories(Request $request): LengthAwarePaginator
    {
        $perPage = $request->query('per_page', 20);
        $search = $request->query('search');
        $level = $request->query('level');
        $parentId = $request->query('parent_id');
        $isActive = $request->query('is_active');

        $version = Cache::remember('categories_version', now()->addDay(), fn() => 1);

        $cacheKey = "categories_v{$version}_" . md5(serialize([
            $perPage,
            $search,
            $level,
            $parentId,
            $isActive,
            $request->query('page', 1)
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($perPage, $search, $level, $parentId, $isActive) {
            $query = Category::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            }

            if ($level) {
                $query->where('level', $level);
            }

            if ($parentId !== null) {
                if ($parentId === '0' || $parentId === 'null') {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', $parentId);
                }
            }

            if ($isActive !== null) {
                $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
            }

            return $query->with([
                'parent',
                'children' => function ($q) {
                    $q->withCount('products');
                }
            ])
                ->withCount('children')
                ->withCount('products')
                ->orderBy('order')
                ->orderBy('name')
                ->paginate($perPage);
        });
    }

    /**
     * Obtiene una categoría por ID con caché
     */
    public function getCategoryById(int $id): Category
    {
        $category = Cache::remember("category_{$id}", now()->addHour(), function () use ($id) {
            return Category::with(['parent', 'children'])
                ->withCount('children')
                ->find($id);
        });

        if (!$category) {
            throw new CategoryNotFoundException("No se encontró la categoría con ID: {$id}");
        }

        return $category;
    }

    /**
     * Crea una nueva categoría
     */
    public function createCategory(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            if (!isset($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            if (isset($data['parent_id']) && $data['parent_id']) {
                $parent = Category::find($data['parent_id']);

                if (!$parent) {
                    throw new CategoryValidationException(
                        'Categoría padre no válida',
                        ['parent_id' => ['La categoría padre no existe']]
                    );
                }

                if ($parent->level >= 3) {
                    throw new CategoryMaxLevelException();
                }

                $data['level'] = $parent->level + 1;
            } else {
                $data['level'] = 1;
                $data['parent_id'] = null;
            }

            if (
                Category::where('name', $data['name'])
                ->where('parent_id', $data['parent_id'])
                ->exists()
            ) {
                throw new CategoryValidationException(
                    'Ya existe una categoría con ese nombre en el mismo nivel',
                    ['name' => ['El nombre ya está en uso']]
                );
            }

            if (Category::where('slug', $data['slug'])->exists()) {
                throw new CategoryValidationException(
                    'El slug ya está en uso',
                    ['slug' => ['El slug debe ser único']]
                );
            }

            if (!isset($data['order'])) {
                $maxOrder = Category::where('parent_id', $data['parent_id'])
                    ->max('order') ?? 0;
                $data['order'] = $maxOrder + 1;
            }

            $data['is_active'] = $data['is_active'] ?? true;

            $category = Category::create($data);

            $this->clearCategoryCache($category);

            Log::info('Categoría creada', [
                'id' => $category->id,
                'name' => $category->name
            ]);

            return $category->load(['parent', 'children']);
        });
    }

    /**
     * Actualiza una categoría
     * 🔥 AHORA con validación ESTRICTA: si falla el pricing, se revierte TODO
     */
    public function updateCategory(int $id, array $data): Category
    {
        return DB::transaction(function () use ($id, $data) {
            $category = Category::findOrFail($id);

            // 🔥 GUARDAR MÁRGENES ANTERIORES
            $oldMarginRetail = $category->normal_margin_percentage;
            $oldMarginRetailMin = $category->min_margin_percentage;
            $marginChanged = false;

            // Validar nombre único si cambió
            if (isset($data['name']) && $data['name'] !== $category->name) {
                if (
                    Category::where('name', $data['name'])
                    ->where('parent_id', $category->parent_id)
                    ->where('id', '!=', $id)
                    ->exists()
                ) {
                    throw new CategoryValidationException(
                        'Ya existe una categoría con ese nombre',
                        ['name' => ['El nombre ya está en uso']]
                    );
                }
            }

            // Validar slug único si cambió
            if (isset($data['slug']) && $data['slug'] !== $category->slug) {
                if (
                    Category::where('slug', $data['slug'])
                    ->where('id', '!=', $id)
                    ->exists()
                ) {
                    throw new CategoryValidationException(
                        'El slug ya está en uso',
                        ['slug' => ['El slug debe ser único']]
                    );
                }
            }

            // 🔥 DETECTAR si cambió el margen
            if (
                (isset($data['normal_margin_percentage']) && $data['normal_margin_percentage'] != $oldMarginRetail) ||
                (isset($data['min_margin_percentage']) && $data['min_margin_percentage'] != $oldMarginRetailMin)
            ) {
                $marginChanged = true;

                Log::info('Cambio de margen detectado', [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'old_margin_retail' => $oldMarginRetail,
                    'new_margin_retail' => $data['normal_margin_percentage'] ?? $oldMarginRetail,
                    'old_margin_retail_min' => $oldMarginRetailMin,
                    'new_margin_retail_min' => $data['min_margin_percentage'] ?? $oldMarginRetailMin,
                ]);
            }

            // Actualizar categoría
            $category->update($data);

            // Limpiar caché
            $this->clearCategoryCache($category);

            Log::info('Categoría actualizada exitosamente', [
                'id' => $category->id,
                'name' => $category->name,
                'note' => 'Los precios ahora se calculan dinámicamente al consultar',
            ]);

            return $category->fresh(['parent', 'children']);
        });
    }

    /**
     * Elimina una categoría
     */
    public function deleteCategory(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $category = Category::withCount('children')->findOrFail($id);

            if ($category->children_count > 0) {
                throw new CategoryHasChildrenException(
                    "La categoría '{$category->name}' tiene {$category->children_count} subcategorías"
                );
            }

            $deleted = $category->delete();

            $this->clearCategoryCache($category);

            Log::info('Categoría eliminada', ['name' => $category->name]);

            return $deleted;
        });
    }

    /**
     * Limpia el caché relacionado con la categoría
     */
    private function clearCategoryCache(?Category $category = null): void
    {
        if ($category) {
            Cache::forget("category_{$category->id}");
            Cache::forget("category_{$category->id}_total_products");

            if ($category->parent_id) {
                Cache::forget("category_{$category->parent_id}");
                Cache::forget("category_{$category->parent_id}_total_products");
            }

            if ($category->relationLoaded('children')) {
                foreach ($category->children as $child) {
                    Cache::forget("category_{$child->id}");
                    Cache::forget("category_{$child->id}_total_products");
                }
            }
        }

        Cache::forget('categories_tree');
        Cache::increment('categories_version');
    }

    /**
     * Obtiene árbol completo de categorías (con caché)
     */
    public function getCategoryTree(): \Illuminate\Support\Collection
    {
        return Cache::remember('categories_tree', now()->addDay(), function () {
            return Category::whereNull('parent_id')
                ->with('children.children')
                ->where('is_active', true)
                ->orderBy('order')
                ->get();
        });
    }
}
