<?php

namespace App\Models\Supports;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'purchase_batch_id',
        'type',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'user_id',
        'notes',
        'moved_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'moved_at' => 'datetime',
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

    public function purchaseBatch()
    {
        return $this->belongsTo(PurchaseBatch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeInbound($query)
    {
        return $query->where('type', 'in');
    }

    public function scopeOutbound($query)
    {
        return $query->where('type', 'out');
    }
}
