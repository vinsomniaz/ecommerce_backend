<?php

namespace App\Models\Supports;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'warehouse_id',
        'batch_code',
        'quantity_purchased',
        'quantity_available',
        'purchase_price',
        'distribution_price',
        'additional_costs',
        'purchase_date',
        'expiry_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'quantity_purchased' => 'integer',
        'quantity_available' => 'integer',
        'purchase_price' => 'decimal:2',
        'distribution_price' => 'decimal:2',
        'additional_costs' => 'decimal:2',
        'purchase_date' => 'date',
        'expiry_date' => 'date',
    ];

    // Relaciones
    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('quantity_available', '>', 0);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('status', 'active')
                    ->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [now(), now()->addDays($days)]);
    }

    // Métodos
    public function reduceQuantity(int $quantity): bool
    {
        if ($this->quantity_available < $quantity) {
            throw new \Exception("Stock insuficiente en el lote {$this->batch_code}");
        }

        $this->quantity_available -= $quantity;

        if ($this->quantity_available === 0) {
            $this->status = 'depleted';
        }

        return $this->save();
    }

    public function getTotalCostAttribute(): float
    {
        return $this->quantity_available * $this->distribution_price;
    }
}
