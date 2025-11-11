<?php

namespace App\Models\Supports;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'value',
    ];

    /**
     * Relación con Producto
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
