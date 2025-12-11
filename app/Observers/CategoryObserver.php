<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CategoryObserver implements ShouldHandleEventsAfterCommit
{
    // ✅ corre después del commit de DB::transaction()
    public bool $afterCommit = true;

    public function created(Category $category): void
    {
        $this->flush($category);
    }

    public function updated(Category $category): void
    {
        $this->flush($category);
    }

    public function deleted(Category $category): void
    {
        $this->flush($category);
    }

    private function flush(Category $category): void
    {
        // cache individual
        Cache::forget("category_{$category->id}");
        Cache::forget("category_{$category->id}_total_products");
        Cache::forget("category_{$category->id}_descendant_ids");

        // padre actual
        if ($category->parent_id) {
            Cache::forget("category_{$category->parent_id}");
            Cache::forget("category_{$category->parent_id}_total_products");
            Cache::forget("category_{$category->parent_id}_descendant_ids");
        }

        // ✅ padre anterior si cambió
        $oldParentId = $category->getOriginal('parent_id');
        if ($oldParentId && $oldParentId !== $category->parent_id) {
            Cache::forget("category_{$oldParentId}");
            Cache::forget("category_{$oldParentId}_total_products");
            Cache::forget("category_{$oldParentId}_descendant_ids");
        }

        // árbol + versionado de listados paginados
        Cache::forget('categories_tree');
        Cache::increment('categories_version');

        // stats globales cacheadas (si las usas)
        Cache::forget('categories_global_stats');
    }
}
