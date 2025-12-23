<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'entity_id',
        'user_id',
        'address',
        'ubigeo',
        'country_code', // NUEVO
        'postcode',
        'reference',
        'phone',
        'label',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Get the entity that owns the address.
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * Get the user that owns the address.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the ubigeo for the address.
     */
    public function ubigeoData(): BelongsTo
    {
        return $this->belongsTo(Ubigeo::class, 'ubigeo', 'ubigeo');
    }

    /**
     * NUEVO: Get the country for the address.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }
}
