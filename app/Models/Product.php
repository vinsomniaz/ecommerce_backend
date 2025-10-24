<?php
// app/Models/Product.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'unit_price',
        'cost_price',
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
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'min_stock' => 'integer',
        'weight' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'visible_online' => 'boolean',
    ];

    // CORREGIR: Activity Log Configuration
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('products')
            ->logOnly(['sku', 'primary_name', 'unit_price', 'cost_price', 'min_stock', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(function (string $eventName) {
                return match ($eventName) {
                    'created'  => 'Producto creado',
                    'updated'  => 'Producto actualizado',
                    'deleted'  => 'Producto eliminado',
                    'restored' => 'Producto restaurado',
                    default    => "Producto {$eventName}",
                };
            });
    }

    // Spatie Media Configuration
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxFilesize(2 * 1024 * 1024)
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

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    // Scopes
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
}
