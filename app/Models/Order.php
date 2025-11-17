<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Order extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id',
        'warehouse_id',
        'shipping_address_id',
        'coupon_id',
        'status',
        'currency',
        'subtotal',
        'discount',
        'coupon_discount',
        'tax',
        'shipping_cost',
        'total',
        'order_date',
        'observations',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'order_date' => 'datetime',
    ];

    // Relaciones
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'desc');
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pendiente');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmado');
    }

    public function scopePreparing($query)
    {
        return $query->where('status', 'preparando');
    }

    public function scopeShipped($query)
    {
        return $query->where('status', 'enviado');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'entregado');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelado');
    }

    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('order_date', 'desc');
    }

    // Accessors
    public function getStatusNameAttribute(): string
    {
        return match($this->status) {
            'pendiente' => 'Pendiente',
            'confirmado' => 'Confirmado',
            'preparando' => 'Preparando',
            'enviado' => 'Enviado',
            'entregado' => 'Entregado',
            'cancelado' => 'Cancelado',
            default => 'Desconocido'
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pendiente' => 'warning',
            'confirmado' => 'info',
            'preparando' => 'processing',
            'enviado' => 'primary',
            'entregado' => 'success',
            'cancelado' => 'error',
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

    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->status, ['pendiente', 'confirmado']);
    }

    public function getCurrentStatusHistoryAttribute()
    {
        return $this->statusHistory()->latest('created_at')->first();
    }

    // Methods
    public function updateStatus(string $newStatus, ?string $notes = null, ?string $trackingCode = null): void
    {
        $this->update(['status' => $newStatus]);

        $this->statusHistory()->create([
            'status' => $newStatus,
            'notes' => $notes,
            'tracking_code' => $trackingCode,
            'user_id' => auth()->id(),
        ]);

        // Enviar notificación al cliente
        $this->notifyCustomer($newStatus, $trackingCode);
    }

    protected function notifyCustomer(string $status, ?string $trackingCode = null): void
    {
        $messages = [
            'confirmado' => 'Tu pedido ha sido confirmado',
            'preparando' => 'Estamos preparando tu pedido',
            'enviado' => 'Tu pedido ha sido enviado' . ($trackingCode ? " - Tracking: {$trackingCode}" : ''),
            'entregado' => 'Tu pedido ha sido entregado',
            'cancelado' => 'Tu pedido ha sido cancelado',
        ];

        if (isset($messages[$status])) {
            Notification::create([
                'user_id' => $this->customer_id,
                'type' => "order_{$status}",
                'title' => "Pedido #{$this->id}",
                'message' => $messages[$status],
                'data' => json_encode([
                    'order_id' => $this->id,
                    'tracking_code' => $trackingCode,
                ]),
            ]);
        }
    }

    public function cancel(string $reason = null): void
    {
        if (!$this->can_be_cancelled) {
            throw new \Exception('Este pedido no puede ser cancelado');
        }

        $this->updateStatus('cancelado', $reason);
    }

    // Activity Log
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total', 'customer_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
