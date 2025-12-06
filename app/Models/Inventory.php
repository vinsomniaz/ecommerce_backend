<?php
// app/Models/Inventory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
     * ✅ NUEVA: Relación con precios por lista de precios
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
     * ✅ NUEVO: Obtener precio de venta según lista de precios activa
     *
     * @param int|null $priceListId ID de lista de precios (null = lista por defecto)
     * @return float|null
     */
    public function getSalePrice(?int $priceListId = null): ?float
    {
        // Si no se especifica lista, usar la lista por defecto del sistema
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
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_to')
                  ->orWhere('valid_to', '>=', now());
            })
            ->orderBy('warehouse_id', 'desc') // Priorizar precio específico de almacén
            ->first();

        return $productPrice?->price;
    }

    /**
     * ✅ NUEVO: Obtener precio mínimo según lista de precios
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
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_to')
                  ->orWhere('valid_to', '>=', now());
            })
            ->orderBy('warehouse_id', 'desc')
            ->first();

        return $productPrice?->min_price;
    }

    /**
     * ✅ NUEVO: Obtener margen de ganancia según lista de precios
     */
    public function getProfitMargin(?int $priceListId = null): ?float
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
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_to')
                  ->orWhere('valid_to', '>=', now());
            })
            ->orderBy('warehouse_id', 'desc')
            ->first();

        return $productPrice?->profit_margin;
    }

    /**
     * ✅ NUEVO: Obtener ID de lista de precios por defecto
     */
    private function getDefaultPriceListId(): int
    {
        // Puedes cachear esto para mejor rendimiento
        return \App\Models\PriceList::where('is_active', true)
            ->orderBy('id')
            ->value('id') ?? 1;
    }

    /**
     * ✅ ACTUALIZADO: Verificar si hay precio configurado
     */
    public function hasPriceConfigured(?int $priceListId = null): bool
    {
        return $this->getSalePrice($priceListId) !== null;
    }

    /**
     * ✅ NUEVO: Obtener todos los precios disponibles para este inventario
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
                'profit_margin' => $price->profit_margin,
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
