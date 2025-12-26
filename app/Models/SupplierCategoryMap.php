<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\SupplierCategoryMapObserver;

#[ObservedBy([SupplierCategoryMapObserver::class])]
class SupplierCategoryMap extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'supplier_category',
        'category_id',
        'is_active',
        'confidence',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'confidence' => 'decimal:2',
    ];

    // ============================================================================
    // RELACIONES
    // ============================================================================

    public function supplier()
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // ============================================================================
    // SCOPES
    // ============================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeBySupplierCategory($query, string $supplierCategory)
    {
        return $query->where('supplier_category', $supplierCategory);
    }

    public function scopeMapped($query)
    {
        return $query->whereNotNull('category_id');
    }

    public function scopeUnmapped($query)
    {
        return $query->whereNull('category_id');
    }

    // ============================================================================
    // MÉTODOS
    // ============================================================================

    /**
     * Mapea esta categoría del proveedor a una categoría del ERP
     */
    public function mapToCategory(int $categoryId, ?float $confidence = null): void
    {
        $this->update([
            'category_id' => $categoryId,
            'confidence' => $confidence,
            'is_active' => true,
        ]);
    }

    /**
     * Desmapea esta categoría (quita la relación con categoría ERP)
     */
    public function unmap(): void
    {
        $this->update([
            'category_id' => null,
            'confidence' => null,
        ]);
    }

    /**
     * Verifica si esta categoría está mapeada
     */
    public function isMapped(): bool
    {
        return !is_null($this->category_id);
    }
}
