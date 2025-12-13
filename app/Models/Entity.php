<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\EntityObserver;

#[ObservedBy([EntityObserver::class])]
class Entity extends Model
{
    use HasFactory, SoftDeletes;

    // NOTA: Las direcciones y teléfonos NO viven en entities.
    // La fuente de verdad es addresses (direcciones) y contacts (personas).
    protected $fillable = [
        'type',
        'tipo_documento',
        'numero_documento',
        'tipo_persona',
        'business_name',
        'trade_name',
        'first_name',
        'last_name',
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
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'type' => 'customer',
        'is_active' => true,
    ];

    /**
     * Boot method to set default registered_at and cascade soft deletes
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($entity) {
            if (!$entity->registered_at) {
                $entity->registered_at = now();
            }
        });

        // Cascade soft delete to addresses and contacts
        static::deleting(function ($entity) {
            // Only cascade if it's a soft delete (not force delete)
            if (!$entity->isForceDeleting()) {
                $entity->addresses()->delete();
                $entity->contacts()->delete();
            }
        });

        // Cascade restore to addresses and contacts
        static::restoring(function ($entity) {
            $entity->addresses()->withTrashed()->restore();
            $entity->contacts()->withTrashed()->restore();
        });
    }

    /**
     * Relación con tipo de documento
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'tipo_documento', 'code');
    }

    /**
     * Get the user that created this entity
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the primary address for the entity.
     */
    public function primaryAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }

    /**
     * Get all addresses for the entity.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get the primary contact for the entity.
     */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(Contact::class)->where('is_primary', true);
    }

    /**
     * Get all contacts for the entity.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
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