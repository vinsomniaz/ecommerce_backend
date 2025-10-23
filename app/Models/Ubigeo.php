<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // AGREGAR ESTO
use Illuminate\Database\Eloquent\Model;

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
        'departamento',
        'provincia',
        'distrito',
        'codigo_sunat',
    ];

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class, 'ubigeo', 'ubigeo');
    }
}
