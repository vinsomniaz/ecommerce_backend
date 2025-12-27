<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Banner;

class UpdateBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $baseRules = [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'link' => ['nullable', 'string', 'max:255'],
            'section' => ['required', 'string', Rule::in(Banner::getSections())],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ];

        // Image validation only if present
        return $baseRules + $this->getDimensionRules();
    }

    protected function getDimensionRules(): array
    {
        if (!$this->hasFile('image')) {
            return ['image' => ['nullable']];
        }

        $section = $this->input('section');
        $rule = 'dimensions:';

        $dimensions = match ($section) {
            Banner::SECTION_HERO_SLIDER => 'min_width=1920,min_height=620',
            Banner::SECTION_PROMOTIONS => 'min_width=500,min_height=500',
            Banner::SECTION_BANNER => 'min_width=800,min_height=600',
            default => null
        };

        if ($dimensions) {
            return ['image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240', $rule . $dimensions]];
        }

        return ['image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240']];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $section = $this->input('section');
            if (!$section) return;

            // Validar cantidad maxima solo si estamos activando un banner inactivo o cambiando de seccion
            // Si ya estaba activo y en la misma seccion, no cuenta como +1
            $banner = $this->route('banner'); // Assumes route model binding ? or id usage

            // Si el banner cambia de estado a activo O cambia de sección hacia una llena
            $isBecomingActive = $this->boolean('is_active', $banner->is_active); // Si no se envia is_active, asume el actual? No, en update request usually we send what changes.
            // Mejor: if input has is_active and it is true...

            $newSection = $this->input('section', $banner->section);
            $isActive = $this->boolean('is_active') || ($this->missing('is_active') && $banner->is_active);

            if ($isActive) {
                $maxCount = match ($newSection) {
                    Banner::SECTION_HERO_SLIDER => 10,
                    Banner::SECTION_PROMOTIONS => 3,
                    Banner::SECTION_BANNER => 2,
                    default => 999
                };

                // Contar cuantos activos hay en esa seccion, excluyendo este banner
                $currentCount = Banner::where('section', $newSection)
                    ->where('is_active', true)
                    ->where('id', '!=', $banner->id)
                    ->count();

                if ($currentCount >= $maxCount) {
                    $validator->errors()->add('section', "Se ha alcanzado la cantidad máxima de imágenes activas ($maxCount) para la sección $newSection.");
                }
            }
        });
    }
}
