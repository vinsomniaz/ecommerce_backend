<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Banner extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'title',
        'description',
        'link',
        'section',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Constantes para las secciones
    const SECTION_HERO_SLIDER = 'hero_slider';
    const SECTION_PROMOTIONS = 'promotions';
    const SECTION_BANNER = 'banner';
    const SECTION_TOP_BAR = 'top_bar';

    public static function getSections()
    {
        return [
            self::SECTION_HERO_SLIDER,
            self::SECTION_PROMOTIONS,
            self::SECTION_BANNER,
            self::SECTION_TOP_BAR,
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile(); // Solo una imagen por banner
    }
}
