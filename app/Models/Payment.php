<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Payment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'order_id',
        'sale_id',
        'payment_method',
        'amount',
        'currency',
        'status',
        'transaction_id',
        'gateway_response',
        'authorization_code',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
    ];

    // Relaciones
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    // Accessors
    public function getPaymentMethodNameAttribute(): string
    {
        return match($this->payment_method) {
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            'culqi' => 'Culqi',
            'izipay' => 'Izipay',
            'yape' => 'Yape',
            'plin' => 'Plin',
            default => 'Desconocido'
        };
    }

    public function getStatusNameAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pendiente',
            'completed' => 'Completado',
            'failed' => 'Fallido',
            'refunded' => 'Reembolsado',
            default => 'Desconocido'
        };
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    // Mutators
    public function setGatewayResponseAttribute($value)
    {
        $this->attributes['gateway_response'] = is_array($value) ? json_encode($value) : $value;
    }

    // Activity Log
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['payment_method', 'amount', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
