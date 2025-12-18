<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupplierProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'product_id',
        'supplier_sku',
        'supplier_name',
        'brand',
        'location',
        'source_url',
        'image_url',
        'supplier_category',
        'category_suggested',
        'purchase_price',
        'sale_price',
        'currency',
        'available_stock',
        'stock_text',
        'is_available',
        'last_seen_at',
        'last_import_id',
        'delivery_days',
        'min_order_quantity',
        'priority',
        'is_active',
        'price_updated_at',
        'notes',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'available_stock' => 'integer',
        'delivery_days' => 'integer',
        'min_order_quantity' => 'integer',
        'priority' => 'integer',
        'is_available' => 'boolean',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'price_updated_at' => 'datetime',
    ];

    // ============================================================================
    // RELACIONES
    // ============================================================================

    public function supplier()
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // ============================================================================
    // SCOPES
    // ============================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)
            ->where('available_stock', '>', 0);
    }

    public function scopeBySupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    // ============================================================================
    // MÉTODOS
    // ============================================================================

    /**
     * Mutator: La disponibilidad se calcula automáticamente según el stock
     */
    protected function setAvailableStockAttribute($value): void
    {
        $this->attributes['available_stock'] = $value;
        $this->attributes['is_available'] = ($value > 0);
    }

    public function updatePrice(float $price): void
    {
        $this->update([
            'purchase_price' => $price,
            'price_updated_at' => now(),
        ]);
    }

    public function updateStock(int $stock): void
    {
        $this->update([
            'available_stock' => $stock,
            // is_available se calcula automáticamente por el mutator
        ]);
    }
}
