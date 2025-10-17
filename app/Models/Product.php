<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model
{
    use HasFactory, SoftDeletes, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'sku',
        'primary_name',
        'secondary_name',
        'description',
        'category_id',
        'brand',
        'unit_price',
        'cost_price',
        'min_stock',
        'unit_measure',
        'tax_type',
        'weight',
        'is_active',
        'is_featured',
        'visible_online',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'min_stock' => 'integer',
        'weight' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'visible_online' => 'boolean',
    ];

    // Activity Log Configuration
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['sku', 'primary_name', 'unit_price', 'cost_price', 'min_stock', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Spatie Media Configuration
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxFilesize(2 * 1024 * 1024) // 2MB
            ->maxNumberOfFiles(5);
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->quality(85);

        $this->addMediaConversion('medium')
            ->width(600)
            ->height(600)
            ->quality(85);

        $this->addMediaConversion('large')
            ->width(1200)
            ->height(1200)
            ->quality(85);
    }

    // // Relationships
    // public function category()
    // {
    //     return $this->belongsTo(Category::class);
    // }

    // public function attributes()
    // {
    //     return $this->hasMany(ProductAttribute::class);
    // }

    // public function inventory()
    // {
    //     return $this->hasMany(Inventory::class);
    // }

    // public function stockMovements()
    // {
    //     return $this->hasMany(StockMovement::class);
    // }

    // public function sales()
    // {
    //     return $this->hasManyThrough(Sale::class, SaleDetail::class);
    // }

    // // Accessors
    // public function getStockAvailableAttribute()
    // {
    //     return $this->inventory()->sum('available_stock');
    // }

    // public function getTotalStockAttribute()
    // {
    //     return $this->inventory()->sum('available_stock') + $this->inventory()->sum('reserved_stock');
    // }

    // public function getImagesCountAttribute()
    // {
    //     return $this->getMedia('images')->count();
    // }

    // // Scopes
    // public function scopeActive($query)
    // {
    //     return $query->where('is_active', true);
    // }

    // public function scopeVisibleOnline($query)
    // {
    //     return $query->where('visible_online', true)->where('is_active', true);
    // }

    // public function scopeFeatured($query)
    // {
    //     return $query->where('is_featured', true);
    // }

    // public function scopeLowStock($query)
    // {
    //     return $query->whereHas('inventory', function ($q) {
    //         $q->whereRaw('available_stock <= min_stock');
    //     });
    // }
}
