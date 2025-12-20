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
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\ProductObserver;

#[ObservedBy([ProductObserver::class])]
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
        'initial_cost',
        'is_active',
        'is_featured',
        'visible_online',
        'is_new',
    ];

    protected $casts = [
        'min_stock' => 'integer',
        'weight' => 'decimal:2',
        'initial_cost' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'visible_online' => 'boolean',
        'is_new' => 'boolean',
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
    public function registerMediaConversions(?Media $media = null): void
    {
        // No lo usas, pero la firma queda compatible con Spatie
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->quality(85)
            ->background('rgba(0, 0, 0, 0)')
            ->nonQueued()
            ->performOnCollections('images');

        $this->addMediaConversion('medium')
            ->width(600)
            ->height(600)
            ->quality(85)
            ->background('rgba(0, 0, 0, 0)')
            ->nonQueued()
            ->performOnCollections('images');

        $this->addMediaConversion('large')
            ->width(1200)
            ->height(1200)
            ->quality(85)
            ->background('rgba(0, 0, 0, 0)')
            ->nonQueued()
            ->performOnCollections('images');

        $this->addMediaConversion('webp')
            ->width(800)
            ->height(800)
            ->format('webp')
            ->quality(85)
            ->background('rgba(0, 0, 0, 0)')
            ->nonQueued()
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

    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
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

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
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
        $inv = $this->inventory()->orderBy('warehouse_id')->first();

        return $inv ? (float) $inv->average_cost : 0.0;
    }

    /**
     * Retorna el precio del producto global si tiene
     */

    public function getSalePrice(?int $priceListId = null, ?int $warehouseId = null): ?float
    {
        // Si no se especifica lista, usar RETAIL por defecto
        if (!$priceListId) {
            $priceListId = PriceList::where('code', 'RETAIL')
                ->where('is_active', true)
                ->value('id');
        }

        if (!$priceListId) {
            return null;
        }

        $query = $this->productPrices()
            ->where('price_list_id', $priceListId)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            });

        // Prioridad: específico del almacén > general
        if ($warehouseId) {
            // Buscar primero precio específico del almacén
            $specificPrice = (clone $query)
                ->where('warehouse_id', $warehouseId)
                ->orderBy('valid_from', 'desc')
                ->value('price');

            if ($specificPrice !== null) {
                return $specificPrice;
            }
        }

        // Si no hay específico, buscar precio general
        return $query->whereNull('warehouse_id')
            ->orderBy('valid_from', 'desc')
            ->value('price');
    }

    public function getMinSalePrice(?int $priceListId = null, ?int $warehouseId = null): ?float
    {
        if (!$priceListId) {
            $priceListId = PriceList::where('code', 'RETAIL')
                ->where('is_active', true)
                ->value('id');
        }

        if (!$priceListId) {
            return null;
        }

        $query = $this->productPrices()
            ->where('price_list_id', $priceListId)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            });

        if ($warehouseId) {
            $specificPrice = (clone $query)
                ->where('warehouse_id', $warehouseId)
                ->orderBy('valid_from', 'desc')
                ->value('min_price');

            if ($specificPrice !== null) {
                return $specificPrice;
            }
        }

        return $query->whereNull('warehouse_id')
            ->orderBy('valid_from', 'desc')
            ->value('min_price');
    }


    public function getProfitMargin(?int $priceListId = null, ?int $warehouseId = null): ?float
    {
        $salePrice = $this->getSalePrice($priceListId, $warehouseId);
        $averageCost = $this->average_cost;

        if (!$salePrice || !$averageCost || $averageCost <= 0) {
            return null;
        }

        return round((($salePrice - $averageCost) / $averageCost) * 100, 2);
    }

    /**
     * ✅ Obtener todos los precios del producto agrupados por lista
     *
     * @param int|null $warehouseId Filtrar por almacén específico
     * @return array
     */
    public function getAllPrices(?int $warehouseId = null): array
    {
        $query = $this->productPrices()
            ->with('priceList:id,code,name')
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            });

        if ($warehouseId) {
            $query->where(function ($q) use ($warehouseId) {
                $q->whereNull('warehouse_id')
                    ->orWhere('warehouse_id', $warehouseId);
            });
        } else {
            $query->whereNull('warehouse_id');
        }

        return $query->orderBy('price_list_id')
            ->orderBy('min_quantity')
            ->get()
            ->map(function ($price) {
                return [
                    'price_list_id' => $price->price_list_id,
                    'price_list_code' => $price->priceList->code,
                    'price_list_name' => $price->priceList->name,
                    'price' => $price->price,
                    'min_price' => $price->min_price,
                    'min_quantity' => $price->min_quantity,
                    'warehouse_id' => $price->warehouse_id,
                    'is_warehouse_specific' => $price->warehouse_id !== null,
                    'currency' => $price->currency,
                ];
            })
            ->toArray();
    }

    /**
     * ✅ Obtener precios por almacén (para gestión de inventario)
     *
     * @param int|null $priceListId
     * @return array
     */
    public function getPricesByWarehouse(?int $priceListId = null): array
    {
        if (!$priceListId) {
            $priceListId = PriceList::where('code', 'RETAIL')
                ->where('is_active', true)
                ->value('id');
        }

        return $this->inventory()
            ->with('warehouse:id,name')
            ->get()
            ->map(function ($inv) use ($priceListId) {
                $salePrice = $this->getSalePrice($priceListId, $inv->warehouse_id);
                $minPrice = $this->getMinSalePrice($priceListId, $inv->warehouse_id);

                return [
                    'warehouse_id' => $inv->warehouse_id,
                    'warehouse_name' => $inv->warehouse->name,
                    'available_stock' => $inv->available_stock,
                    'reserved_stock' => $inv->reserved_stock,
                    'average_cost' => $inv->average_cost,
                    'sale_price' => $salePrice,
                    'min_sale_price' => $minPrice,
                    'profit_margin' => $salePrice && $inv->average_cost > 0
                        ? round((($salePrice - $inv->average_cost) / $inv->average_cost) * 100, 2)
                        : null,
                ];
            })
            ->toArray();
    }
    /**
     * ✅ Verificar si el producto tiene precio configurado
     *
     * @param int|null $priceListId
     * @param int|null $warehouseId
     * @return bool
     */
    public function hasPrice(?int $priceListId = null, ?int $warehouseId = null): bool
    {
        return $this->getSalePrice($priceListId, $warehouseId) !== null;
    }

    /**
     * ✅ Obtener el mejor precio disponible (menor precio activo)
     * Útil para mostrar ofertas o precio competitivo
     *
     * @param int|null $warehouseId
     * @return float|null
     */
    public function getBestPrice(?int $warehouseId = null): ?float
    {
        $query = $this->productPrices()
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            });

        if ($warehouseId) {
            $query->where(function ($q) use ($warehouseId) {
                $q->whereNull('warehouse_id')
                    ->orWhere('warehouse_id', $warehouseId);
            });
        } else {
            $query->whereNull('warehouse_id');
        }

        return $query->min('price');
    }

    /**
     * Stock total disponible en todos los almacenes
     */
    public function getTotalStockAttribute(): int
    {
        return $this->inventory()->sum('available_stock');
    }

    // ==================== SCOPES ====================
    public function scopeNew($query)
    {
        return $query->where('is_new', true);
    }

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
