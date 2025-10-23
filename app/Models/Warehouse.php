<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'ubigeo',
        'address',
        'phone',
        'is_main',
        'is_active',
        'visible_online',
        'picking_priority',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_active' => 'boolean',
        'visible_online' => 'boolean',
        'picking_priority' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
        'visible_online' => true,
        'picking_priority' => 0,
        'is_main' => false,
    ];

    // Relaciones
    public function ubigeoData()
    {
        return $this->belongsTo(Ubigeo::class, 'ubigeo', 'ubigeo'); // 'ubigeo' no 'code'
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVisibleOnline($query)
    {
        return $query->where('visible_online', true);
    }

    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('picking_priority', 'desc');
    }

    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    // Métodos
    public function hasInventory(): bool
    {
        return $this->inventories()->exists();
    }

    public function makeMain(): void
    {
        DB::transaction(function () {
            static::where('is_main', true)
                ->where('id', '!=', $this->id)
                ->update(['is_main' => false]);

            $this->update(['is_main' => true]);
        });
    }
}
