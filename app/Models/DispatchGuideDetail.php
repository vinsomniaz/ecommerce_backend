<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchGuideDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'guide_id',
        'product_id',
        'quantity',
        'unit_measure',
        'description',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    // Relaciones
    public function guide(): BelongsTo
    {
        return $this->belongsTo(DispatchGuide::class, 'guide_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
