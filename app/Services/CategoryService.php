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
    /**
     * Obtiene categorías con filtros y paginación
     * Laravel 12: Aprovecha caché asíncrono para mejor performance
     */
    public function getCategories(Request $request): LengthAwarePaginator
    {
        $perPage = $request->query('per_page', 20);
        $search = $request->query('search');
        $level = $request->query('level');
        $parentId = $request->query('parent_id');
        $isActive = $request->query('is_active');

        // 🔥 Obtener versión actual del caché
        $version = Cache::remember('categories_version', now()->addDay(), fn() => 1);

        // Crear cache key con versión
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

            // Filtro por búsqueda
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            }

            // Filtro por nivel
            if ($level) {
                $query->where('level', $level);
            }

            // Filtro por padre
            if ($parentId !== null) {
                if ($parentId === '0' || $parentId === 'null') {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', $parentId);
                }
            }

            // Filtro por estado
            if ($isActive !== null) {
                $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
            }

            // Eager loading optimizado
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
     * Laravel 12: Transacciones optimizadas
     */
    public function createCategory(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            // Generar slug si no viene
            if (!isset($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            // Determinar nivel según el padre
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

            // Validar nombre único en mismo nivel/padre
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

            // Validar slug único
            if (Category::where('slug', $data['slug'])->exists()) {
                throw new CategoryValidationException(
                    'El slug ya está en uso',
                    ['slug' => ['El slug debe ser único']]
                );
            }

            // Establecer orden si no viene
            if (!isset($data['order'])) {
                $maxOrder = Category::where('parent_id', $data['parent_id'])
                    ->max('order') ?? 0;
                $data['order'] = $maxOrder + 1;
            }

            // Estado activo por defecto
            $data['is_active'] = $data['is_active'] ?? true;

            $category = Category::create($data);

            // Limpiar caché relacionado
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
     */
    public function updateCategory(int $id, array $data): Category
    {
        return DB::transaction(function () use ($id, $data) {
            $category = Category::findOrFail($id);

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

            $category->update($data);

            // Limpiar caché
            $this->clearCategoryCache($category);

            Log::info('Categoría actualizada', ['id' => $category->id]);

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

            // Limpiar caché
            $this->clearCategoryCache($category);

            Log::info('Categoría eliminada', ['name' => $category->name]);

            return $deleted;
        });
    }

    /**
     * Limpia el caché relacionado con la categoría
     * Laravel 12: Gestión eficiente de caché
     */
    /**
     * Limpia el caché relacionado con la categoría
     * Usa versioning para invalidar todo el caché de consultas
     */
    private function clearCategoryCache(?Category $category = null): void
    {
        if ($category) {
            // Limpiar caché específico de la categoría
            Cache::forget("category_{$category->id}");
            Cache::forget("category_{$category->id}_total_products");

            // Limpiar caché del padre
            if ($category->parent_id) {
                Cache::forget("category_{$category->parent_id}");
                Cache::forget("category_{$category->parent_id}_total_products");
            }

            // Limpiar caché de los hijos
            if ($category->relationLoaded('children')) {
                foreach ($category->children as $child) {
                    Cache::forget("category_{$child->id}");
                    Cache::forget("category_{$child->id}_total_products");
                }
            }
        }

        // Limpiar caché del árbol completo
        Cache::forget('categories_tree');

        // 🔥 Incrementar versión para invalidar todas las consultas paginadas
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
