<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Invoice extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'sale_id',
        'order_id',
        'customer_id',
        'invoice_type',
        'series',
        'number',
        'issue_date',
        'currency',
        'exchange_rate',
        'subtotal',
        'discount',
        'tax',
        'total',
        'sunat_status',
        'sunat_response_code',
        'sunat_response_message',
        'xml_path',
        'pdf_path',
        'cdr_path',
        'hash',
        'qr_code',
        'sent_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'exchange_rate' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'sent_at' => 'datetime',
    ];

    // Relaciones
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'customer_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('sunat_status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('sunat_status', 'accepted');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('invoice_type', $type);
    }

    // Accessors
    public function getFullNumberAttribute(): string
    {
        return $this->series . '-' . $this->number;
    }

    public function getInvoiceTypeNameAttribute(): string
    {
        return match($this->invoice_type) {
            '01' => 'Factura',
            '03' => 'Boleta',
            '07' => 'Nota de Crédito',
            '08' => 'Nota de Débito',
            default => 'Desconocido'
        };
    }

    // Activity Log
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['invoice_type', 'series', 'number', 'total', 'sunat_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
