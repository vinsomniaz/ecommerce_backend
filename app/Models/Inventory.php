<?php
// app/Models/Inventory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Inventory extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $table = 'inventory';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'available_stock',
        'reserved_stock',
        'average_cost',
        'price_updated_at',
        'last_movement_at',
    ];

    protected $casts = [
        'available_stock' => 'integer',
        'reserved_stock' => 'integer',
        'average_cost' => 'decimal:4',
        'last_movement_at' => 'datetime',
        'price_updated_at' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Relación con precios por lista de precios
     */
    public function productPrices()
    {
        return $this->hasMany(ProductPrice::class, 'product_id', 'product_id')
            ->where('warehouse_id', $this->warehouse_id);
    }

    // ==================== ATRIBUTOS CALCULADOS ====================

    public function getTotalStockAttribute(): int
    {
        return $this->available_stock + $this->reserved_stock;
    }

    /**
     * Obtener precio de venta según lista de precios activa
     *
     * @param int|null $priceListId ID de lista de precios (null = lista por defecto)
     * @return float|null
     */
    public function getSalePrice(?int $priceListId = null): ?float
    {
        if ($priceListId === null) {
            $priceListId = $this->getDefaultPriceListId();
        }

        $productPrice = ProductPrice::where('product_id', $this->product_id)
            ->where('price_list_id', $priceListId)
            ->where(function ($q) {
                $q->whereNull('warehouse_id')
                    ->orWhere('warehouse_id', $this->warehouse_id);
            })
            ->where('is_active', true)
            ->orderBy('warehouse_id', 'desc') // Priorizar precio específico de almacén
            ->first();

        return $productPrice?->price;
    }

    /**
     * Obtener precio mínimo según lista de precios
     */
    public function getMinSalePrice(?int $priceListId = null): ?float
    {
        if ($priceListId === null) {
            $priceListId = $this->getDefaultPriceListId();
        }

        $productPrice = ProductPrice::where('product_id', $this->product_id)
            ->where('price_list_id', $priceListId)
            ->where(function ($q) {
                $q->whereNull('warehouse_id')
                    ->orWhere('warehouse_id', $this->warehouse_id);
            })
            ->where('is_active', true)
            ->orderBy('warehouse_id', 'desc')
            ->first();

        return $productPrice?->min_price;
    }

    /**
     * Obtener margen de ganancia según lista de precios
     */
    public function getProfitMargin(?int $priceListId = null): ?float
    {
        $salePrice = $this->getSalePrice($priceListId);

        if (!$salePrice || $this->average_cost <= 0) {
            return null;
        }

        return round((($salePrice - $this->average_cost) / $this->average_cost) * 100, 2);
    }

    /**
     * Obtener ID de lista de precios por defecto (cacheado)
     */
    private function getDefaultPriceListId(): int
    {
        return Cache::remember('default_price_list_id', 3600, function () {
            return PriceList::where('is_active', true)
                ->orderBy('id')
                ->value('id') ?? 1;
        });
    }

    /**
     * Verificar si hay precio configurado
     */
    public function hasPriceConfigured(?int $priceListId = null): bool
    {
        return $this->getSalePrice($priceListId) !== null;
    }

    /**
     * Obtener todos los precios disponibles para este inventario
     */
    public function getAllPrices(): array
    {
        $prices = ProductPrice::where('product_id', $this->product_id)
            ->where(function ($q) {
                $q->whereNull('warehouse_id')
                    ->orWhere('warehouse_id', $this->warehouse_id);
            })
            ->where('is_active', true)
            ->with('priceList:id,code,name')
            ->get();

        return $prices->map(function ($price) {
            return [
                'price_list_id' => $price->price_list_id,
                'price_list_name' => $price->priceList->name,
                'price' => $price->price,
                'min_price' => $price->min_price,
                'is_warehouse_specific' => $price->warehouse_id !== null,
            ];
        })->toArray();
    }

    // ==================== SCOPES ====================

    public function scopeWithStock($query)
    {
        return $query->where('available_stock', '>', 0);
    }

    public function scopeLowStock($query)
    {
        return $query->whereHas('product', function ($q) {
            $q->whereColumn('inventory.available_stock', '<=', 'products.min_stock');
        });
    }
}
