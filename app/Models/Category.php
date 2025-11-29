<?php
// app/Models/Category.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        'normal_margin_percentage',  // ✅ NUEVO
        'min_margin_percentage',      // ✅ NUEVO
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
        'order' => 'integer',
        'parent_id' => 'integer',
        'normal_margin_percentage' => 'decimal:2',  // ✅ NUEVO
        'min_margin_percentage' => 'decimal:2',      // ✅ NUEVO
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Categoría {$eventName}");
    }

    // ========================================
    // RELACIONES
    // ========================================

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot($query)
    {
        return $query->where('level', 1)->whereNull('parent_id');
    }

    public function scopeFamily($query)
    {
        return $query->where('level', 2);
    }

    public function scopeSubfamily($query)
    {
        return $query->where('level', 3);
    }

    public function scopeByLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    // ========================================
    // MÉTODOS DE CATEGORÍA
    // ========================================

    public function isRoot(): bool
    {
        return $this->level === 1 && is_null($this->parent_id);
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function getAllChildren(): HasMany
    {
        return $this->children()->with('children');
    }

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

    public function getTotalProductsAttribute(): int
    {
        return Cache::remember("category_{$this->id}_total_products", now()->addMinutes(10), function () {
            return $this->getTotalProductsRecursive();
        });
    }

    /**
     * ✅ NUEVO: Método recursivo para contar productos
     */
    public function getTotalProductsRecursive(): int
    {
        // Contar productos propios
        $count = $this->products()->count();

        // Sumar productos de cada hijo recursivamente
        foreach ($this->children as $child) {
            $count += $child->getTotalProductsRecursive();
        }

        return $count;
    }
    // ========================================
    // ✅ NUEVOS MÉTODOS DE MARGEN
    // ========================================

    /**
     * Obtiene el margen mínimo efectivo (heredado o propio)
     * Busca hacia arriba en la jerarquía hasta encontrar un valor > 0
     */
    public function getEffectiveMinMargin(): float
    {
        // Si tiene valor propio y es > 0, usarlo
        if ($this->min_margin_percentage > 0) {
            return (float) $this->min_margin_percentage;
        }

        // Si tiene padre, buscar en el padre recursivamente
        if ($this->parent) {
            return $this->parent->getEffectiveMinMargin();
        }

        // ✅ Si no tiene valor ni padre, usar el de settings
        $defaultMargin = DB::table('settings')
            ->where('group', 'margins')
            ->where('key', 'min_margin_percentage')
            ->value('value');

        return $defaultMargin ? (float) $defaultMargin : 1.00; // 👈 Fallback temporal a 1%
    }

    /**
     * Obtiene el margen normal efectivo (heredado o propio)
     */
    public function getEffectiveNormalMargin(): float
    {
        if ($this->normal_margin_percentage > 0) {
            return (float) $this->normal_margin_percentage;
        }

        if ($this->parent) {
            return $this->parent->getEffectiveNormalMargin();
        }

        // ✅ Si no tiene valor ni padre, usar el de settings
        $defaultMargin = DB::table('settings')
            ->where('group', 'margins')
            ->where('key', 'default_margin_percentage')
            ->value('value');

        return $defaultMargin ? (float) $defaultMargin : 1.00; // 👈 Fallback temporal a 1%
    }

    /**
     * Verifica si hereda márgenes del padre
     */
    public function inheritsMargins(): bool
    {
        return $this->min_margin_percentage == 0 && $this->normal_margin_percentage == 0;
    }

    /**
     * ✅ NUEVO: Verifica si usa el default del sistema (sin padre y sin valor propio)
     */
    public function usesSystemDefault(): bool
    {
        return $this->inheritsMargins() && !$this->parent_id;
    }


    /**
     * ✅ NUEVO: Obtiene todos los IDs de esta categoría y sus descendientes
     * Útil para filtrar productos por categoría incluyendo subcategorías
     */
    public function getAllDescendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }

    /**
     * ✅ NUEVO: Versión optimizada con caché
     */
    public function getAllDescendantIdsWithCache(): array
    {
        return Cache::remember("category_{$this->id}_descendant_ids", now()->addHours(24), function () {
            return $this->getAllDescendantIds();
        });
    }

    /**
     * ✅ NUEVO: Scope para filtrar productos por categoría incluyendo subcategorías
     */
    public function scopeWithDescendants($query, int $categoryId)
    {
        $category = static::with('children.children')->find($categoryId);

        if (!$category) {
            return $query->whereNull('category_id'); // No matches
        }

        $categoryIds = $category->getAllDescendantIdsWithCache();

        return $query->whereIn('category_id', $categoryIds);
    }

    /**
     * Obtiene información completa de márgenes (para debugging/admin)
     */
    public function getMarginInfo(): array
    {
        return [
            'category_name' => $this->name,
            'level' => $this->level,
            'own_min_margin' => (float) $this->min_margin_percentage,
            'own_normal_margin' => (float) $this->normal_margin_percentage,
            'effective_min_margin' => $this->getEffectiveMinMargin(),
            'effective_normal_margin' => $this->getEffectiveNormalMargin(),
            'inherits_from_parent' => $this->inheritsMargins(),
            'parent_category' => $this->parent?->name,
        ];
    }
}
