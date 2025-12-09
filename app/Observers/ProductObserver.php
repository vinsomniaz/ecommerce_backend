<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ProductObserver implements ShouldHandleEventsAfterCommit
{
    public bool $afterCommit = true;

    public function created(Product $product): void
    {
        $this->flush($product);
    }

    public function updated(Product $product): void
    {
        if ($product->wasChanged([
            'category_id',
            'is_active',
            'is_featured',
            'visible_online',
            'is_new',
        ])) {
            $this->flush($product);
        }
    }

    public function deleted(Product $product): void
    {
        $this->flush($product);
    }

    public function restored(Product $product): void
    {
        $this->flush($product);
    }

    public function forceDeleted(Product $product): void
    {
        $this->flush($product);
    }

    private function flush(Product $product): void
    {
        if ($product->category_id) {
            Cache::forget("category_{$product->category_id}");
            Cache::forget("category_{$product->category_id}_total_products");
            Cache::forget("category_{$product->category_id}_descendant_ids");
        }

        $oldCategoryId = $product->getOriginal('category_id');
        if ($oldCategoryId && $oldCategoryId !== $product->category_id) {
            Cache::forget("category_{$oldCategoryId}");
            Cache::forget("category_{$oldCategoryId}_total_products");
            Cache::forget("category_{$oldCategoryId}_descendant_ids");
        }

        Cache::forget('categories_tree');
        Cache::increment('categories_version');
        Cache::forget('categories_global_stats');

        Cache::increment('products_version'); // ✅ invalida global_stats cacheadas
    }
}
