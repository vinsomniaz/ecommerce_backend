<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Banner;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorisize handled by middleware/gates
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'link' => ['nullable', 'string', 'max:255'],
            'section' => ['required', 'string', Rule::in(Banner::getSections())],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
            'image' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048', // 2MB
                function ($attribute, $value, $fail) {
                    $section = $this->input('section');
                    // Dimensiones recomendadas
                    $dimensions = match ($section) {
                        Banner::SECTION_HERO_SLIDER => ['width' => 1920, 'height' => 620],
                        Banner::SECTION_PROMOTIONS => ['width' => 500, 'height' => 500],
                        Banner::SECTION_BANNER => ['width' => 800, 'height' => 600],
                        default => null
                    };

                    if ($dimensions) {
                        $image = getimagesize($value->getPathname());
                        // Permitir un margen de error pequeño o exigir exactitud?
                        // Por ahora exigiremos exactitud o mínimo, según requerimiento "Medidas recomendadas".
                        // Implementaré validación: width >= recomendado, height >= recomendado (o exacto?)
                        // User message: "Medidas recomendadas de cada apartado: Hero Slider (1920 x 620)..."
                        // I will use `dimensions` rule validation if possible, but closure is more flexible.
                        // Let's rely on Laravel's dimension rule dynamically constructed below instead of closure if possible, 
                        // but since `section` is dynamic, closure is easier or conditional array merge.
                    }
                }
            ],
        ] + $this->getDimensionRules();
    }

    protected function getDimensionRules(): array
    {
        $section = $this->input('section');
        $rule = 'dimensions:';

        $dimensions = match ($section) {
            Banner::SECTION_HERO_SLIDER => 'min_width=1920,min_height=620',
            Banner::SECTION_PROMOTIONS => 'min_width=500,min_height=500',
            Banner::SECTION_BANNER => 'min_width=800,min_height=600',
            // Para Top Bar no hay medidas especificas, opcional
            default => null
        };

        if ($dimensions) {
            // Nota: Usamos min_width/height para ser flexibles con "recomendadas" 
            // pero si usuario quiere estricto se puede cambiar a width=x,height=y.
            // Dado que son "recomendadas", "min" suele ser buena práctica para nitidez.
            return ['image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240', $rule . $dimensions]];
        }

        return ['image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240']];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $section = $this->input('section');
            if (!$section) return;

            // Validar cantidad maxima
            $maxCount = match ($section) {
                Banner::SECTION_HERO_SLIDER => 10,
                Banner::SECTION_PROMOTIONS => 3,
                Banner::SECTION_BANNER => 2,
                default => 999
            };

            $currentCount = Banner::where('section', $section)->where('is_active', true)->count();

            // Si estamos creando uno nuevo y ya llegamos al limite y el nuevo se quiere crear como activo
            if ($this->boolean('is_active', true) && $currentCount >= $maxCount) {
                $validator->errors()->add('section', "Se ha alcanzado la cantidad máxima de imágenes activas ($maxCount) para la sección $section.");
            }
        });
    }
}
