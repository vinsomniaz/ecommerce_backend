<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use HasFactory;

    const UPDATED_AT = null; // No tiene updated_at

    protected $fillable = [
        'order_id',
        'status',
        'notes',
        'tracking_code',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Relaciones
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function getStatusIconAttribute(): string
    {
        return match($this->status) {
            'pendiente' => 'clock',
            'confirmado' => 'check-circle',
            'preparando' => 'package',
            'enviado' => 'truck',
            'entregado' => 'check-square',
            'cancelado' => 'x-circle',
            default => 'circle'
        };
    }
}
