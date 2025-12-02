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
    // ✅ MÉTODOS DE MARGEN CORREGIDOS
    // ========================================

    /**
     * Obtiene el margen mínimo efectivo (heredado o propio)
     * Busca hacia arriba en la jerarquía hasta encontrar un valor configurado (no null y no 0)
     */
    public function getEffectiveMinMargin(): float
    {
        // ✅ Si tiene valor propio configurado (no es null y no es 0), usarlo
        if (!is_null($this->min_margin_percentage) && $this->min_margin_percentage != 0) {
            return (float) $this->min_margin_percentage;
        }

        // ✅ Si tiene padre, buscar recursivamente en la jerarquía
        if ($this->parent) {
            return $this->parent->getEffectiveMinMargin();
        }

        // ✅ Si no tiene valor ni padre, usar el de settings
        $defaultMargin = DB::table('settings')
            ->where('group', 'margins')
            ->where('key', 'min_margin_percentage')
            ->value('value');

        return $defaultMargin ? (float) $defaultMargin : 1.00;
    }

    /**
     * Obtiene el margen normal efectivo (heredado o propio)
     */
    public function getEffectiveNormalMargin(): float
    {
        // ✅ Si tiene valor propio configurado (no es null y no es 0), usarlo
        if (!is_null($this->normal_margin_percentage) && $this->normal_margin_percentage != 0) {
            return (float) $this->normal_margin_percentage;
        }

        // ✅ Si tiene padre, buscar recursivamente en la jerarquía
        if ($this->parent) {
            return $this->parent->getEffectiveNormalMargin();
        }

        // ✅ Si no tiene valor ni padre, usar el de settings
        $defaultMargin = DB::table('settings')
            ->where('group', 'margins')
            ->where('key', 'default_margin_percentage')
            ->value('value');

        return $defaultMargin ? (float) $defaultMargin : 1.00;
    }

    /**
     * Verifica si hereda márgenes del padre
     */
    public function inheritsMargins(): bool
    {
        return (is_null($this->min_margin_percentage) || $this->min_margin_percentage == 0)
            && (is_null($this->normal_margin_percentage) || $this->normal_margin_percentage == 0);
    }

    /**
     * ✅ NUEVO: Verifica si usa el default del sistema (sin padre y sin valor propio)
     */
    public function usesSystemDefault(): bool
    {
        return $this->inheritsMargins() && !$this->parent_id;
    }

    /**
     * ✅ NUEVO: Obtiene de qué categoría está heredando el margen
     */
    public function getMarginSource(): ?Category
    {
        // Si tiene margen propio, retorna null (no hereda)
        if (!is_null($this->normal_margin_percentage) && $this->normal_margin_percentage != 0) {
            return null;
        }

        // Buscar el primer padre con margen configurado
        $parent = $this->parent;
        while ($parent) {
            if (!is_null($parent->normal_margin_percentage) && $parent->normal_margin_percentage != 0) {
                return $parent;
            }
            $parent = $parent->parent;
        }

        return null; // Usa defaults del sistema
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
        $marginSource = $this->getMarginSource();

        return [
            'category_id' => $this->id,
            'category_name' => $this->name,
            'level' => $this->level,
            'parent_id' => $this->parent_id,
            'parent_name' => $this->parent?->name,
            'own_min_margin' => $this->min_margin_percentage,
            'own_normal_margin' => $this->normal_margin_percentage,
            'effective_min_margin' => $this->getEffectiveMinMargin(),
            'effective_normal_margin' => $this->getEffectiveNormalMargin(),
            'inherits_from_parent' => $this->inheritsMargins(),
            'margin_source_id' => $marginSource?->id,
            'margin_source_name' => $marginSource?->name,
            'uses_system_default' => is_null($marginSource),
        ];
    }
}
