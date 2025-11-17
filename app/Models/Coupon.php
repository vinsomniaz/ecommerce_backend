<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_amount',
        'start_date',
        'end_date',
        'active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
    ];

    // Relaciones
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeValid($query)
    {
        $today = Carbon::today();

        return $query->where('active', true)
                    ->where('start_date', '<=', $today)
                    ->where('end_date', '>=', $today);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }

    // Accessors
    public function getTypeNameAttribute(): string
    {
        return $this->type === 'percentage' ? 'Porcentaje' : 'Monto Fijo';
    }

    public function getIsValidAttribute(): bool
    {
        if (!$this->active) return false;

        $today = Carbon::today();

        return $this->start_date <= $today && $this->end_date >= $today;
    }

    public function getIsExpiredAttribute(): bool
    {
        return Carbon::today()->greaterThan($this->end_date);
    }

    public function getDaysRemainingAttribute(): int
    {
        if ($this->is_expired) return 0;

        return Carbon::today()->diffInDays($this->end_date);
    }

    // Methods
    public function calculateDiscount(float $amount): float
    {
        if (!$this->isValidForAmount($amount)) {
            return 0;
        }

        if ($this->type === 'percentage') {
            return round(($amount * $this->value) / 100, 2);
        }

        return min($this->value, $amount);
    }

    public function isValidForAmount(float $amount): bool
    {
        return $this->is_valid && $amount >= $this->min_amount;
    }

    public function getUsageCount(): int
    {
        return $this->orders()->count();
    }
}
