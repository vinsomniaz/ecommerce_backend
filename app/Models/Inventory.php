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
        'average_cost', // ✅ Agregado para consistencia
        'sale_price',
        'profit_margin',
        'min_sale_price',
        'price_updated_at',
        'last_movement_at',
    ];

    protected $casts = [
        'available_stock' => 'integer',
        'reserved_stock' => 'integer',
        'average_cost' => 'float', // ✅ Agregado
        'sale_price' => 'float',
        'profit_margin' => 'float',
        'min_sale_price' => 'decimal:2',
        'last_movement_at' => 'datetime',
        'price_updated_at' => 'datetime',
    ];

    // Relaciones
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    // Métodos
    public function getTotalStockAttribute(): int
    {
        return $this->available_stock + $this->reserved_stock;
    }

    /**
     * ✅ Actualiza precio y margen
     */
    public function updateSalePrice(float $newPrice, ?float $profitMargin = null): void
    {
        $this->sale_price = $newPrice;

        if ($profitMargin !== null) {
            $this->profit_margin = $profitMargin;
        }

        $this->price_updated_at = now();
        $this->save();
    }
}
