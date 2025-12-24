<?php
// app/Models/ProductPrice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductPrice extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'product_id',
        'price_list_id',
        'warehouse_id',
        'price',
        'min_price',
        'currency',
        'min_quantity',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'min_price' => 'decimal:2',
        'min_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    // ==================== ACTIVITY LOG ====================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('product_prices')
            ->logOnly(['product_id', 'price_list_id', 'price', 'min_price', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ==================== RELACIONES ====================

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function priceList()
    {
        return $this->belongsTo(PriceList::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPriceList($query, int $priceListId)
    {
        return $query->where('price_list_id', $priceListId);
    }

    public function scopeForWarehouse($query, ?int $warehouseId)
    {
        if ($warehouseId === null) {
            return $query->whereNull('warehouse_id');
        }
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeGeneral($query)
    {
        return $query->whereNull('warehouse_id');
    }

    public function scopeWarehouseSpecific($query)
    {
        return $query->whereNotNull('warehouse_id');
    }

    /**
     * Scope para precios promocionales
     */
    public function scopePromo($query)
    {
        return $query->whereHas('priceList', fn($q) => $q->where('code', 'PROMO'))
            ->where('is_active', true);
    }

    // ==================== MÉTODOS DE INSTANCIA ====================

    /**
     * Calcular margen de ganancia
     */
    public function calculateProfitMargin(): ?float
    {
        if (!$this->product || $this->product->average_cost <= 0) {
            return null;
        }

        $cost = $this->product->average_cost;
        return round((($this->price - $cost) / $cost) * 100, 2);
    }

    /**
     * Calcular ganancia en monto
     */
    public function calculateProfitAmount(): ?float
    {
        if (!$this->product || $this->product->average_cost <= 0) {
            return null;
        }

        return round($this->price - $this->product->average_cost, 2);
    }

    /**
     * Obtener descripción del alcance (general o por almacén)
     */
    public function getScopeDescription(): string
    {
        if ($this->warehouse_id) {
            return $this->warehouse?->name ?? "Almacén #{$this->warehouse_id}";
        }
        return 'General (todos los almacenes)';
    }
}
