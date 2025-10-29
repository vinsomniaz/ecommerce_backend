<?php
// app/Models/Purchase.php

namespace App\Models;

use App\Models\Supports\PurchaseBatch;
use App\Models\Supports\PurchaseDetail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'warehouse_id',
        'series',
        'number',
        'date',
        'currency',
        'exchange_rate',
        'subtotal',
        'tax',
        'total',
        'payment_status',
        'user_id',
        'notes',
        'registered_at',
    ];

    protected $casts = [
        'date' => 'date',
        'exchange_rate' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'registered_at' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    public function supplier()
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    public function batches()
    {
        return $this->hasMany(PurchaseBatch::class);
    }

    // ==================== SCOPES ====================

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeBySupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    // ==================== MÉTODOS ====================

    public function getFullDocumentNumberAttribute(): string
    {
        return "{$this->series}-{$this->number}";
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    public function isPartiallyPaid(): bool
    {
        return $this->payment_status === 'partial';
    }
}
