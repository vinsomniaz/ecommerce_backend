<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class DispatchGuide extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'sale_id',
        'order_id',
        'series',
        'number',
        'issue_date',
        'transfer_start_date',
        'transfer_reason',
        'shipper_id',
        'recipient_id',
        'origin_warehouse_id',
        'destination_address',
        'destination_ubigeo',
        'transport_mode',
        'vehicle_plate',
        'driver_doc_type',
        'driver_doc_number',
        'driver_name',
        'total_weight',
        'sunat_status',
        'sunat_response_message',
        'xml_path',
        'pdf_path',
        'cdr_path',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'transfer_start_date' => 'datetime',
        'total_weight' => 'decimal:2',
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

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'shipper_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'recipient_id');
    }

    public function originWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'origin_warehouse_id');
    }

    public function destinationUbigeo(): BelongsTo
    {
        return $this->belongsTo(Ubigeo::class, 'destination_ubigeo', 'ubigeo');
    }

    public function details(): HasMany
    {
        return $this->hasMany(DispatchGuideDetail::class, 'guide_id');
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

    // Accessors
    public function getFullNumberAttribute(): string
    {
        return $this->series . '-' . $this->number;
    }

    public function getTransferReasonNameAttribute(): string
    {
        return match($this->transfer_reason) {
            '01' => 'Venta',
            '02' => 'Compra',
            '03' => 'Venta con entrega a terceros',
            '04' => 'Traslado entre establecimientos',
            '13' => 'Otros',
            '14' => 'Venta sujeta a confirmación',
            default => 'Desconocido'
        };
    }

    public function getTransportModeNameAttribute(): string
    {
        return $this->transport_mode === '01' ? 'Público' : 'Privado';
    }

    // Activity Log
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['series', 'number', 'transfer_reason', 'sunat_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
