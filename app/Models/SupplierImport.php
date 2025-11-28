<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierImport extends Model
{
    protected $fillable = [
        'supplier_id',
        'raw_data',
        'status',
        'total_products',
        'processed_products',
        'updated_products',
        'new_products',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }
}