<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierImport extends Model
{
    protected $fillable = [
        'supplier_id',
        'raw_data',
        'fetched_at',
        'margin_percent',
        'source_totals',
        'items_count',
        'status',
        'total_products',
        'processed_products',
        'updated_products',
        'new_products',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'raw_data' => 'array',  // ← CRÍTICO: Convierte array PHP ↔ JSON
        'fetched_at' => 'datetime',
        'margin_percent' => 'decimal:2',
        'source_totals' => 'array',
        'items_count' => 'integer',
        'total_products' => 'integer',
        'processed_products' => 'integer',
        'updated_products' => 'integer',
        'new_products' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }
}
