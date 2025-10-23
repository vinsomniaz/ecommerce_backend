<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // AGREGAR ESTO
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory; // AGREGAR ESTO

    protected $table = 'inventory';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'available_stock',
        'reserved_stock',
        'precio_venta',
        'last_movement_at',
    ];

    protected $casts = [
        'precio_venta' => 'decimal:2',
        'last_movement_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
