<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Quotation extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'user_id',
        'customer_id',
        'warehouse_id',
        'coupon_id',
        'quotation_code',
        'quotation_date',
        'valid_until',
        'status',
        'currency',
        'exchange_rate',
        'subtotal',
        'discount',
        'coupon_discount',
        'tax',
        'shipping_cost',
        'packaging_cost',
        'assembly_cost',
        'total',
        'total_margin',
        'margin_percentage',
        'commission_amount',
        'commission_percentage',
        'commission_paid',
        'customer_name',
        'customer_document',
        'customer_email',
        'customer_phone',
        'shipping_address',
        'shipping_ubigeo',
        'shipping_reference',
        'observations',
        'internal_notes',
        'terms_conditions',
        'converted_sale_id',
        'converted_at',
        'pdf_path',
        'sent_at',
        'sent_to_email',
        'registered_at',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'valid_until' => 'date',
        'exchange_rate' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'packaging_cost' => 'decimal:2',
        'assembly_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'total_margin' => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'commission_paid' => 'boolean',
        'converted_at' => 'datetime',
        'sent_at' => 'datetime',
        'registered_at' => 'datetime',
    ];

    // ============================================================================
    // RELACIONES
    // ============================================================================

    /**
     * Vendedor que creó la cotización
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    /**
     * Cliente (Entity)
     */
    public function customer()
    {
        return $this->belongsTo(Entity::class, 'customer_id');
    }

    /**
     * Almacén desde donde se cotiza
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Cupón aplicado (si existe)
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Detalle de productos cotizados
     */
    public function details()
    {
        return $this->hasMany(QuotationDetail::class);
    }

    /**
     * Historial de cambios de estado
     */
    public function statusHistory()
    {
        return $this->hasMany(QuotationStatusHistory::class);
    }

    /**
     * Venta generada (si fue convertida)
     */
    public function convertedSale()
    {
        return $this->belongsTo(Sale::class, 'converted_sale_id');
    }

    // ============================================================================
    // SCOPES
    // ============================================================================

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeConverted($query)
    {
        return $query->where('status', 'converted');
    }

    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeBySeller($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeValidToday($query)
    {
        return $query->where('valid_until', '>=', now()->toDateString());
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('quotation_code', 'like', "%{$search}%")
              ->orWhere('customer_name', 'like', "%{$search}%")
              ->orWhere('customer_document', 'like', "%{$search}%");
        });
    }

    // ============================================================================
    // ACCESSORS & MUTATORS
    // ============================================================================

    public function getIsExpiredAttribute(): bool
    {
        return $this->valid_until < now()->toDateString() && 
               $this->status !== 'converted';
    }

    public function getIsEditableAttribute(): bool
    {
        return $this->status === 'draft';
    }

    public function getCanBeSentAttribute(): bool
    {
        return in_array($this->status, ['draft', 'sent']) && 
               $this->details()->count() > 0;
    }

    public function getCanBeConvertedAttribute(): bool
    {
        return $this->status === 'accepted';
    }

    // ============================================================================
    // MÉTODOS DE NEGOCIO
    // ============================================================================

    public function markAsSent(?string $email = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_to_email' => $email ?? $this->customer_email,
        ]);
    }

    public function markAsAccepted(): void
    {
        $this->update(['status' => 'accepted']);
    }

    public function markAsRejected(): void
    {
        $this->update(['status' => 'rejected']);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    public function markAsConverted(Sale $sale): void
    {
        $this->update([
            'status' => 'converted',
            'converted_sale_id' => $sale->id,
            'converted_at' => now(),
        ]);
    }

    public function payCommission(): void
    {
        $this->update(['commission_paid' => true]);
    }

    // ============================================================================
    // ACTIVITY LOG (Spatie)
    // ============================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'total', 'total_margin', 'commission_amount',
                'sent_at', 'converted_at', 'commission_paid'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}