<?php
// app/Models/CouponUsage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'order_id',
        'user_id',
        'discount_applied',
        'order_subtotal',
    ];

    protected $casts = [
        'discount_applied' => 'decimal:2',
        'order_subtotal' => 'decimal:2',
    ];

    // ========================================
    // RELACIONES
    // ========================================

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
