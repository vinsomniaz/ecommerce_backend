<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles; // ⭐ AGREGADO
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes; // ⭐ AGREGADO

    protected $fillable = [
        'first_name',
        'last_name',
        'cellphone', // ⭐ AGREGADO
        'email',
        'password',
        'is_active', // ⭐ AGREGADO
        'warehouse_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean', // ⭐ AGREGADO
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
        // Si NO es cliente e-commerce, no debe tener entity
        if (!$this->hasRole('customer')) {
            return null;
        }

        // Si ya tiene, la devolvemos
        if ($this->entity) {
            return $this->entity;
        }

        // Crear una entity "cliente" básica
        return Entity::create(array_merge([
            'user_id'         => $this->id,
            'type'            => 'customer',   // o el tipo que uses
            'tipo_documento'  => 'DNI',        // por defecto
            'numero_documento' => $this->cellphone ?? 'TEMP_' . $this->id,
            'tipo_persona'    => 'natural',
            'first_name'      => $this->first_name,
            'last_name'       => $this->last_name,
            'email'           => $this->email,
            'phone'           => $this->cellphone,
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
}
