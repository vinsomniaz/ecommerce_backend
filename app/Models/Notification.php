<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // Accessors
    public function getIsReadAttribute(): bool
    {
        return !is_null($this->read_at);
    }

    public function getIconAttribute(): string
    {
        return match($this->type) {
            'order_confirmed' => 'check-circle',
            'order_shipped' => 'truck',
            'order_delivered' => 'package-check',
            'order_cancelled' => 'x-circle',
            'low_stock' => 'alert-triangle',
            'payment_received' => 'credit-card',
            default => 'bell'
        };
    }

    public function getColorAttribute(): string
    {
        return match($this->type) {
            'order_confirmed' => 'success',
            'order_shipped' => 'info',
            'order_delivered' => 'success',
            'order_cancelled' => 'error',
            'low_stock' => 'warning',
            'payment_received' => 'success',
            default => 'default'
        };
    }

    // Methods
    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }

    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }
}
