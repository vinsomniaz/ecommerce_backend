<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Representa la tabla 'exchange_rates' o 'tipo_cambio'.
 * La tasa es la cantidad de PEN por 1 unidad de la moneda extranjera.
 */
class ExchangeRate extends Model
{
    use HasFactory;

    protected $table = 'exchange_rates';
    protected $primaryKey = 'currency';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'currency', // 'moneda'
        'exchange_rate', // 'cambio'
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:4',
        'updated_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Obtiene la tasa de cambio desde la base de datos (con caché).
     * @param string $currency Código de la moneda destino (USD, EUR).
     * @return float|null La tasa (X PEN por 1 unidad de $currency), o 1.0 si es PEN.
     */
    public static function getRate(string $currency): ?float
    {
        if (empty($currency) || strtoupper($currency) === 'PEN') {
            return 1.0;
        }

        $currency = strtoupper($currency);
        $cacheKey = "exchange_rate:{$currency}";

        // Cachea la tasa por 30 minutos
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($currency) {
            $rate = self::where('currency', $currency)->value('exchange_rate');
            
            // Retorna la tasa como float, o null si no existe
            return $rate ? (float) $rate : null;
        });
    }
}