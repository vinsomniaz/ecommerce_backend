<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // AGREGAR ESTO
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // NUEVO

class Ubigeo extends Model
{
    use HasFactory; // AGREGAR ESTO

    protected $table = 'ubigeos';
    protected $primaryKey = 'ubigeo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'ubigeo',
        'country_code', // NUEVO
        'departamento',
        'provincia',
        'distrito',
        'codigo_sunat',
    ];

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class, 'ubigeo', 'ubigeo');
    }

    /**
     * NUEVO: Relación con País
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }
}