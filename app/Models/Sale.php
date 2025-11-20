<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Sale extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'order_id',
        'customer_id',
        'warehouse_id',
        'sale_type', // ⭐ NUEVO
        'date',
        'currency',
        'exchange_rate',
        'subtotal',
        'tax',
        'total',
        'payment_status',
        'user_id',
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

    // Relaciones
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'customer_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function dispatchGuides(): HasMany
    {
        return $this->hasMany(DispatchGuide::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePartial($query)
    {
        return $query->where('payment_status', 'partial');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    // Accessors
    public function getPaymentStatusNameAttribute(): string
    {
        return match ($this->payment_status) {
            'pending' => 'Pendiente',
            'partial' => 'Parcial',
            'paid' => 'Pagado',
            default => 'Desconocido'
        };
    }

    public function getPaymentStatusColorAttribute(): string
    {
        return match ($this->payment_status) {
            'pending' => 'error',
            'partial' => 'warning',
            'paid' => 'success',
            default => 'default'
        };
    }

    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->where('status', 'completed')->sum('amount');
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->total_paid >= $this->total;
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(0, $this->total - $this->total_paid);
    }

    public function getHasInvoiceAttribute(): bool
    {
        return $this->invoices()->exists();
    }

    // Methods
    public function addPayment(float $amount, string $method): Payment
    {
        $payment = $this->payments()->create([
            'amount' => $amount,
            'currency' => $this->currency,
            'payment_method' => $method,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->updatePaymentStatus();

        return $payment;
    }

    protected function updatePaymentStatus(): void
    {
        $totalPaid = $this->total_paid;

        if ($totalPaid >= $this->total) {
            $this->update(['payment_status' => 'paid']);
        } elseif ($totalPaid > 0) {
            $this->update(['payment_status' => 'partial']);
        } else {
            $this->update(['payment_status' => 'pending']);
        }
    }

    // Activity Log
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['total', 'payment_status', 'customer_id', 'warehouse_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
