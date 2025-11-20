<?php
// app/Models/Product.php

namespace App\Models;

use App\Models\Supports\ProductAttribute;
use App\Models\Supports\PurchaseBatch;
use App\Models\Supports\StockMovement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'sku',
        'primary_name',
        'secondary_name',
        'description',
        'category_id',
        'brand',
        'min_stock',
        'unit_measure',
        'tax_type',
        'weight',
        'barcode',
        'is_active',
        'is_featured',
        'visible_online',
    ];

    protected $casts = [
        'min_stock' => 'integer',
        'weight' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'visible_online' => 'boolean',
    ];

    // Append attributes calculados
    protected $appends = ['average_cost', 'total_stock'];

    /**
     * Activity Log Configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('products')
            ->logOnly(['sku', 'primary_name', 'min_stock', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(function (string $eventName) {
                return match ($eventName) {
                    'created' => 'Producto creado y asignado a almacenes',
                    'updated' => 'Producto actualizado',
                    'deleted' => 'Producto eliminado',
                    'restored' => 'Producto restaurado',
                    default => "Producto {$eventName}",
                };
            });
    }

    /**
     * Registrar las conversiones de medios
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->quality(85)
            ->nonQueued() // ⚠️ IMPORTANTE: Genera inmediatamente
            ->performOnCollections('images');

        $this->addMediaConversion('medium')
            ->width(600)
            ->height(600)
            ->quality(85)
            ->nonQueued() // ⚠️ IMPORTANTE: Genera inmediatamente
            ->performOnCollections('images');

        $this->addMediaConversion('large')
            ->width(1200)
            ->height(1200)
            ->quality(85)
            ->nonQueued() // ⚠️ IMPORTANTE: Genera inmediatamente
            ->performOnCollections('images');
    }

    /**
     * Configuración de colecciones de medios
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk('public');
    }

    // ==================== RELACIONES ====================

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseBatches()
    {
        return $this->hasMany(PurchaseBatch::class);
    }

    public function inventory()
    {
        return $this->hasMany(Inventory::class);
    }

    public function firstWarehouseInventory()
    {
        // Define una relación "HasOne" que se ordena por warehouse_id ascendente
        // y toma el primero (el ID más bajo).
        return $this->hasOne(Inventory::class)->orderBy('warehouse_id', 'asc');
    }

    public function attributes()
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function cartDetails(): HasMany
    {
        return $this->hasMany(CartDetail::class);
    }

    public function saleDetails(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function isInStock(): bool
    {
        return $this->inventory->sum('available_stock') > 0;
    }
    // ==================== ATRIBUTOS CALCULADOS ====================

    /**
     * Costo promedio ponderado desde lotes activos
     */
    public function getAverageCostAttribute(): float
    {
        $batches = $this->purchaseBatches()
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->get();

        if ($batches->isEmpty()) {
            return 0.0;
        }

        $totalCost = 0;
        $totalQuantity = 0;

        foreach ($batches as $batch) {
            $totalCost += $batch->distribution_price * $batch->quantity_available;
            $totalQuantity += $batch->quantity_available;
        }

        return $totalQuantity > 0 ? round($totalCost / $totalQuantity, 2) : 0.0;
    }

    /**
     * Stock total disponible en todos los almacenes
     */
    public function getTotalStockAttribute(): int
    {
        return $this->inventory()->sum('available_stock');
    }

    /**
     * ✅ Obtener precio de venta para un almacén específico
     */
    public function getSalePriceForWarehouse(int $warehouseId): ?float
    {
        $inventory = $this->inventory()
            ->where('warehouse_id', $warehouseId)
            ->first();

        return $inventory?->sale_price;
    }

    /**
     * ✅ Obtener inventario de todos los almacenes con precios (usado en Resource)
     */
    public function getWarehousePrices(): array
    {
        return $this->inventory()
            ->with('warehouse:id,name')
            ->get()
            ->map(fn($inv) => [
                'warehouse_id' => $inv->warehouse_id,
                'warehouse_name' => $inv->warehouse->name,
                'available_stock' => $inv->available_stock,
                'reserved_stock' => $inv->reserved_stock,
                'sale_price' => $inv->sale_price,
                'profit_margin' => $inv->profit_margin,
            ])
            ->toArray();
    }

    /**
     * Calcular margen de ganancia para un almacén
     */
    public function calculateProfitMargin(int $warehouseId): ?float
    {
        $salePrice = $this->getSalePriceForWarehouse($warehouseId);
        $averageCost = $this->average_cost;

        if (!$salePrice || !$averageCost) {
            return null;
        }

        return round((($salePrice - $averageCost) / $averageCost) * 100, 2);
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVisibleOnline($query)
    {
        return $query->where('visible_online', true)->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeWithStock($query)
    {
        return $query->whereHas('inventory', function ($q) {
            $q->where('available_stock', '>', 0);
        });
    }

    public function scopeLowStock($query)
    {
        return $query->whereHas('inventory', function ($q) {
            $q->whereColumn('available_stock', '<=', 'products.min_stock');
        });
    }
}
