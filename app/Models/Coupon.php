<?php
// app/Models/Coupon.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\CouponObserver;
use Carbon\Carbon;
use Spatie\Activitylog\LogOptions;

#[ObservedBy([CouponObserver::class])]
class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'max_discount',
        'min_amount',
        'usage_limit',
        'usage_count',
        'usage_per_user',
        'start_date',
        'end_date',
        'applies_to',
        'active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'usage_per_user' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Cupón {$eventName}");
    }

    // ========================================
    // RELACIONES
    // ========================================

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_categories')
            ->withTimestamps();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_products')
            ->withTimestamps();
    }

    // ========================================
    // SCOPES
    // ========================================

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

    public function scopeAvailable($query)
    {
        return $query->valid()
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhereRaw('usage_count < usage_limit');
            });
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }

    public function scopeExpired($query)
    {
        return $query->where('end_date', '<', Carbon::today());
    }

    // ========================================
    // ACCESSORS
    // ========================================

    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            'percentage' => 'Porcentaje',
            'amount' => 'Monto Fijo',
            default => 'Desconocido',
        };
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

    public function getIsComingSoonAttribute(): bool
    {
        return Carbon::today()->lessThan($this->start_date);
    }

    public function getDaysRemainingAttribute(): int
    {
        if ($this->is_expired) return 0;
        if ($this->is_coming_soon) return -1;

        return Carbon::today()->diffInDays($this->end_date);
    }

    public function getRemainingUsesAttribute(): ?int
    {
        if (is_null($this->usage_limit)) return null;
        return max(0, $this->usage_limit - $this->usage_count);
    }

    public function getIsUsageLimitedAttribute(): bool
    {
        return !is_null($this->usage_limit);
    }

    public function getStatusAttribute(): string
    {
        if (!$this->active) return 'inactive';
        if ($this->is_expired) return 'expired';
        if ($this->is_coming_soon) return 'scheduled';
        if ($this->is_usage_limited && $this->remaining_uses <= 0) return 'exhausted';
        return 'active';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'expired' => 'Expirado',
            'scheduled' => 'Programado',
            'exhausted' => 'Agotado',
            default => 'Desconocido',
        };
    }

    public function getTotalDiscountGrantedAttribute(): float
    {
        return (float) $this->usages()->sum('discount_applied');
    }

    // ========================================
    // MÉTODOS DE VALIDACIÓN
    // ========================================

    public function isValidForAmount(float $amount): bool
    {
        return $this->is_valid && $amount >= $this->min_amount;
    }

    public function canBeUsed(): bool
    {
        if (!$this->is_valid) return false;
        if ($this->is_usage_limited && $this->remaining_uses <= 0) return false;
        return true;
    }

    public function canBeUsedBy(?User $user): bool
    {
        if (!$this->canBeUsed()) return false;

        if ($user && $this->usage_per_user) {
            $userUsageCount = $this->usages()
                ->where('user_id', $user->id)
                ->count();

            if ($userUsageCount >= $this->usage_per_user) {
                return false;
            }
        }

        return true;
    }

    public function appliesToProduct(Product $product): bool
    {
        return match ($this->applies_to) {
            'all' => true,
            'products' => $this->products()->where('product_id', $product->id)->exists(),
            'categories' => $product->category_id && $this->categories()
                ->where('category_id', $product->category_id)
                ->exists(),
            default => false,
        };
    }

    // ========================================
    // MÉTODOS DE CÁLCULO
    // ========================================

    public function calculateDiscount(float $amount): float
    {
        if (!$this->isValidForAmount($amount)) {
            return 0;
        }

        if ($this->type === 'percentage') {
            $discount = round(($amount * $this->value) / 100, 2);

            // Aplicar máximo si existe
            if ($this->max_discount && $discount > $this->max_discount) {
                return (float) $this->max_discount;
            }

            return $discount;
        }

        // Monto fijo
        return min((float) $this->value, $amount);
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    public function decrementUsage(): void
    {
        if ($this->usage_count > 0) {
            $this->decrement('usage_count');
        }
    }

    // ========================================
    // MÉTODOS ESTÁTICOS
    // ========================================

    public static function findByCode(string $code): ?self
    {
        return static::where('code', strtoupper(trim($code)))->first();
    }
}
