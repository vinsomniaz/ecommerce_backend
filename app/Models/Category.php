<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'parent_id',
        'description',
        'level',
        'slug',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
        'order' => 'integer',
        'parent_id' => 'integer',
    ];

    /**
     * Summary of getActivitylogOptions
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable() // registra todos los campos fillable
            ->logOnlyDirty() // solo guarda los cambios (no valores iguales)
            ->setDescriptionForEvent(fn(string $eventName) => "Categoría {$eventName}");
    }

    /**
     * Relación con categoría padre
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Relación con subcategorías (hijos)
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('order');
    }

    /**
     * Relación con productos
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Scope para categorías activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para categorías raíz (nivel 1)
     */
    public function scopeRoot($query)
    {
        return $query->where('level', 1)->whereNull('parent_id');
    }

    /**
     * Scope para familias (nivel 2)
     */
    public function scopeFamily($query)
    {
        return $query->where('level', 2);
    }

    /**
     * Scope para subfamilias (nivel 3)
     */
    public function scopeSubfamily($query)
    {
        return $query->where('level', 3);
    }

    /**
     * Scope por nivel
     */
    public function scopeByLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Verificar si es categoría raíz
     */
    public function isRoot(): bool
    {
        return $this->level === 1 && is_null($this->parent_id);
    }

    /**
     * Verificar si tiene subcategorías
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Obtener todas las subcategorías recursivamente
     */
    public function getAllChildren(): HasMany
    {
        return $this->children()->with('children');
    }

    /**
     * Obtener el path completo de la categoría (breadcrumb)
     */
    public function getPathAttribute(): string
    {
        $path = collect([$this->name]);
        $parent = $this->parent;

        while ($parent) {
            $path->prepend($parent->name);
            $parent = $parent->parent;
        }

        return $path->implode(' > ');
    }

    /**
     * Obtener todos los ancestros
     */
    public function ancestors()
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Contar productos en esta categoría y subcategorías
     */
    public function getTotalProductsAttribute(): int
    {
        $count = $this->products()->count();

        foreach ($this->children as $child) {
            $count += $child->total_products;
        }

        return $count;
    }
}
