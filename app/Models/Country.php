<?php


namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $table = 'countries';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * Un país (como Perú) puede tener muchos ubigeos.
     */
    public function ubigeos()
    {
        return $this->hasMany(Ubigeo::class, 'country_code', 'code');
    }

    /**
     * Un país puede tener muchas direcciones.
     */
    public function addresses()
    {
        return $this->hasMany(Address::class, 'country_code', 'code');
    }

    /**
     * Un país puede tener muchas entidades (dirección fiscal).
     */
    public function entities()
    {
        return $this->hasMany(Entity::class, 'country_code', 'code');
    }

    /**
     * Un país puede tener muchos almacenes.
     */
    public function warehouses()
    {
        return $this->hasMany(Warehouse::class, 'country_code', 'code');
    }
}