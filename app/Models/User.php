<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class User extends Authenticatable implements MustVerifyEmail, HasMedia
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes, InteractsWithMedia;

    protected $guard_name = 'sanctum';

    protected $fillable = [
        'first_name',
        'last_name',
        'cellphone',
        'email',
        'password',
        'is_active',
        'warehouse_id',
        'commission_percentage',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'commission_percentage' => 'decimal:2',
            'last_login_at' => 'datetime',
        ];
    }

    // ========================================
    // RELACIONES
    // ========================================

    /**
     * Entity del usuario (datos fiscales/negocio)
     * 1:1 - Un user puede tener UNA entity
     */
    public function entity(): HasOne
    {
        return $this->hasOne(Entity::class);
    }

    /**
     * Carritos del usuario
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }
    /**
     * Almacén asignado (para vendedores)
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
    /**
     * Pedidos como usuario autenticado
     * Incluye tanto pedidos propios como guest que luego se registró
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id'); // ⭐ CAMBIADO
    }

    /**
     * Direcciones del usuario
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Ventas registradas por este usuario (si es vendedor)
     */
    public function registeredSales(): HasMany
    {
        return $this->hasMany(Sale::class, 'user_id');
    }

    // ========================================
    // ACCESSORS / HELPERS
    // ========================================

    /**
     * Carrito actual del usuario
     */
    public function getCurrentCartAttribute(): ?Cart
    {
        return $this->carts()
            ->whereNull('converted_to_order_at') // Solo carritos no convertidos
            ->latest()
            ->first();
    }

    /**
     * Nombre completo
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Verificar si el usuario tiene entity vinculada
     */
    public function hasEntity(): bool
    {
        // Solo consideramos "entity" si es customer
        if (!$this->hasRole('customer')) {
            return false;
        }

        return !is_null($this->entity);
    }

    /**
     * Obtener o crear entity del usuario
     */
    public function getOrCreateEntity(array $data = []): ?Entity
    {
        if (!$this->hasRole('customer')) {
            return null;
        }

        if ($this->entity) {
            return $this->entity;
        }

        return Entity::create(array_merge([
            'user_id'          => $this->id,
            'type'             => 'customer',
            'tipo_documento'   => '01',
            'numero_documento' => $this->cellphone ?? 'TEMP_' . $this->id,
            'tipo_persona'     => 'natural',
            'first_name'       => $this->first_name,
            'last_name'        => $this->last_name,
            'email'            => $this->email,
            'phone'            => $this->cellphone,
        ], $data));
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCustomers($query)
    {
        return $query->role('customer');
    }

    public function scopeAdmins($query)
    {
        return $query->role(['admin', 'super-admin']);
    }

    // ========================================
    // ROLE HELPERS (Spatie)
    // ========================================

    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'super-admin']);
    }

    public function isVendor(): bool
    {
        return $this->hasRole('vendor');
    }

    // ========================================
    // MEDIA LIBRARY (Avatar)
    // ========================================

    /**
     * Register media collections
     * Avatar uses singleFile() to automatically replace old avatar
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->useDisk('public');
    }

    /**
     * Register media conversions for avatar
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->quality(85)
            ->nonQueued()
            ->performOnCollections('avatar');

        $this->addMediaConversion('medium')
            ->width(400)
            ->height(400)
            ->quality(85)
            ->nonQueued()
            ->performOnCollections('avatar');
    }

    /**
     * Get avatar URL (accessor)
     */
    public function getAvatarUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('avatar');
        return $media ? $media->getUrl('medium') : null;
    }

    /**
     * Get avatar thumbnail URL
     */
    public function getAvatarThumbUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('avatar');
        return $media ? $media->getUrl('thumb') : null;
    }
}
