<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Nota: Asume que ya ejecutaste la migración para crear la tabla exchange_rates.
 */
class ExchangeRate extends Model
{
    use HasFactory;

    protected $table = 'exchange_rates';
    protected $primaryKey = 'currency';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'currency', 
        'exchange_rate', 
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:4',
    ];
    
    // Usamos updated_at y created_at de la migración
    public $timestamps = true;


    public static function getRate(string $currency): ?float
    {
        if (empty($currency) || strtoupper($currency) === 'PEN') {
            return 1.0;
        }

        $currency = strtoupper($currency);
        $cacheKey = "exchange_rate:{$currency}";

        // Lee la tasa de la BD, cacheada por 30 minutos
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($currency) {
            $rate = self::where('currency', $currency)->value('exchange_rate');
            return $rate ? (float) $rate : null;
        });
    }
}