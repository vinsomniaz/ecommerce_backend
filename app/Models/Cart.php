<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
    ];

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(CartDetail::class);
    }

    // Scopes
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBySession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    // Accessors
    public function getItemsCountAttribute(): int
    {
        return $this->details->sum('quantity');
    }

    public function getSubtotalAttribute(): float
    {
        return $this->details->sum(function($detail) {
            return $detail->subtotal;
        });
    }

    public function getTotalAttribute(): float
    {
        return $this->subtotal;
    }

    // Methods
    public function addProduct(int $productId, int $quantity = 1): CartDetail
    {
        $detail = $this->details()->where('product_id', $productId)->first();

        if ($detail) {
            $detail->increment('quantity', $quantity);
            return $detail->fresh();
        }

        return $this->details()->create([
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);
    }

    public function updateQuantity(int $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeProduct($productId);
            return;
        }

        $this->details()
            ->where('product_id', $productId)
            ->update(['quantity' => $quantity]);
    }

    public function removeProduct(int $productId): void
    {
        $this->details()->where('product_id', $productId)->delete();
    }

    public function clear(): void
    {
        $this->details()->delete();
    }

    public function isEmpty(): bool
    {
        return $this->details()->count() === 0;
    }

    // Static methods
    public static function findOrCreateForUser(int $userId, string $sessionId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId, 'session_id' => $sessionId]
        );
    }

    public static function findOrCreateForGuest(string $sessionId): self
    {
        return self::firstOrCreate(['session_id' => $sessionId]);
    }
}
