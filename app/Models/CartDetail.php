<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    // Relaciones
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Accessors
    public function getUnitPriceAttribute(): float
    {
        $inventory = $this->product->inventory()
            ->whereHas('warehouse', fn($q) => $q->main()->visibleOnline())
            ->first();

        return $inventory ? (float) $inventory->sale_price : 0; // R1.3
    }

    public function getSubtotalAttribute(): float
    {
        return $this->unit_price * $this->quantity;
    }

    public function getProductNameAttribute(): string
    {
        return $this->product->primary_name ?? 'Producto no disponible';
    }

    public function getProductImageAttribute(): ?string
    {
        return $this->product->getFirstMediaUrl('products');
    }

    // Methods
    public function hasStock(): bool
    {
        $totalStock = $this->product->inventory()
            ->whereHas('warehouse', fn($q) => $q->main()->visibleOnline())
            ->sum('available_stock');

        return $totalStock >= $this->quantity;
    }

    public function getAvailableStock(): int
    {
        return $this->product->inventory()
            ->whereHas('warehouse', fn($q) => $q->main()->visibleOnline())
            ->sum('available_stock');
    }
}
