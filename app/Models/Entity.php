<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Entity extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'tipo_documento',
        'numero_documento',
        'tipo_persona',
        'business_name',
        'trade_name',
        'first_name',
        'last_name',
        'address',
        'ubigeo',
        'phone',
        'email',
        'estado_sunat',
        'condicion_sunat',
        'user_id',
        'is_active',
        'registered_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'registered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'type' => 'customer',
        'is_active' => true,
    ];

    /**
     * Boot method to set default registered_at
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($entity) {
            if (!$entity->registered_at) {
                $entity->registered_at = now();
            }
        });
    }

    /**
     * Get the user that created this entity
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the ubigeo information
     */
    public function ubigeoData(): BelongsTo
    {
        return $this->belongsTo(Ubigeo::class, 'ubigeo', 'ubigeo');
    }

    /**
     * Get the default address for the entity.
     */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }

    /**
     * Scope to filter customers only
     */
    public function scopeCustomers($query)
    {
        return $query->whereIn('type', ['customer', 'both']);
    }

    /**
     * Scope to filter suppliers only
     */
    public function scopeSuppliers($query)
    {
        return $query->whereIn('type', ['supplier', 'both']);
    }

    /**
     * Scope to filter active entities
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get full name attribute
     */
    public function getFullNameAttribute(): ?string
    {
        if ($this->tipo_persona == 'natural') {
            return trim($this->first_name . ' ' . $this->last_name);
        }
        return $this->business_name;
    }

    /**
     * Get display name (full name or business name)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_name;
    }
}