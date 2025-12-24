<?php
// app/Models/PriceList.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    protected $table = 'price_lists';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ==================== MÉTODOS ====================

    /**
     * Obtener cantidad de productos con precios en esta lista
     */
    public function getProductsCount(): int
    {
        return $this->productPrices()
            ->distinct('product_id')
            ->count('product_id');
    }

    /**
     * Verificar si un producto tiene precio en esta lista
     */
    public function hasProductPrice(int $productId, ?int $warehouseId = null): bool
    {
        $query = $this->productPrices()
            ->where('product_id', $productId)
            ->where('is_active', true);

        if ($warehouseId !== null) {
            $query->where(function ($q) use ($warehouseId) {
                $q->whereNull('warehouse_id')
                    ->orWhere('warehouse_id', $warehouseId);
            });
        }

        return $query->exists();
    }

    /**
     * Obtener precio de un producto en esta lista
     */
    public function getProductPrice(int $productId, ?int $warehouseId = null): ?ProductPrice
    {
        return $this->productPrices()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where(function ($q) use ($warehouseId) {
                if ($warehouseId !== null) {
                    $q->whereNull('warehouse_id')
                        ->orWhere('warehouse_id', $warehouseId);
                } else {
                    $q->whereNull('warehouse_id');
                }
            })
            ->orderBy('warehouse_id', 'desc') // Priorizar precios específicos de almacén
            ->first();
    }
}
