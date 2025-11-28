<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuotationDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'product_id',
        'product_name',
        'product_sku',
        'product_brand',
        'quantity',
        'purchase_price',
        'distribution_price',
        'unit_price',
        'discount',
        'discount_percentage',
        'subtotal',
        'tax_amount',
        'total',
        'unit_cost',
        'total_cost',
        'unit_margin',
        'total_margin',
        'margin_percentage',
        'source_type',
        'warehouse_id',
        'supplier_id',
        'supplier_product_id',
        'is_requested_from_supplier',
        'suggested_supplier_id',
        'supplier_price',
        'available_stock',
        'in_stock',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'purchase_price' => 'decimal:2',
        'distribution_price' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'unit_margin' => 'decimal:2',
        'total_margin' => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'supplier_price' => 'decimal:2',
        'available_stock' => 'integer',
        'in_stock' => 'boolean',
        'is_requested_from_supplier' => 'boolean',
    ];

    // ============================================================================
    // RELACIONES
    // ============================================================================

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }

    public function supplierProduct()
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function suggestedSupplier()
    {
        return $this->belongsTo(Entity::class, 'suggested_supplier_id');
    }

    /**
     * Inventario del producto en el warehouse (si aplica)
     */
    public function inventory()
    {
        return $this->hasOne(Inventory::class, 'product_id', 'product_id')
                    ->where('warehouse_id', $this->warehouse_id);
    }

    // ============================================================================
    // SCOPES
    // ============================================================================

    public function scopeFromWarehouse($query)
    {
        return $query->where('source_type', 'warehouse');
    }

    public function scopeFromSupplier($query)
    {
        return $query->where('source_type', 'supplier');
    }

    public function scopeRequested($query)
    {
        return $query->where('is_requested_from_supplier', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('in_stock', true);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('in_stock', false);
    }

    // ============================================================================
    // ACCESSORS
    // ============================================================================

    public function getIsFromWarehouseAttribute(): bool
    {
        return $this->source_type === 'warehouse';
    }

    public function getIsFromSupplierAttribute(): bool
    {
        return $this->source_type === 'supplier';
    }

    public function getTotalPriceAttribute(): float
    {
        return $this->unit_price * $this->quantity;
    }
}