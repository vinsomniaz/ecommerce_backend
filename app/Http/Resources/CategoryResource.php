<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'level' => $this->level,
            'parent_id' => $this->parent_id,
            'order' => $this->order,
            'is_active' => $this->is_active,

            // ✅ MÁRGENES PROPIOS O HEREDADOS (Propios o heredados)
            'min_margin_percentage' => $this->getEffectiveMinMargin(),
            'normal_margin_percentage' => $this->getEffectiveNormalMargin(),

            // ✅ MÁRGENES EFECTIVOS (Propios)
            'effective_min_margin' => (float) ($this->min_margin_percentage ?? 0),
            'effective_normal_margin' => (float) ($this->normal_margin_percentage ?? 0),

            // ✅ INDICA SI HEREDA MÁRGENES (ambos son 0)
            'inherits_margins' => $this->inheritsMargins(),

            // ✅ INDICA SI USA DEFAULT DEL SISTEMA (sin padre y sin valor)
            'uses_system_default' => $this->usesSystemDefault(),

            // ✅ CONTEO DE PRODUCTOS
            'products_count' => $this->when(
                isset($this->products_count),
                $this->products_count ?? 0
            ),
            'total_products' => $this->getTotalProductsRecursive(),

            // Relación con padre (solo info básica)
            'parent' => $this->whenLoaded('parent', function () {
                return [
                    'id' => $this->parent->id,
                    'name' => $this->parent->name,
                    'slug' => $this->parent->slug,
                    'level' => $this->parent->level,
                ];
            }),

            // Subcategorías (recursivo)
            'children' => CategoryResource::collection($this->whenLoaded('children')),

            // Contador de hijos
            'children_count' => $this->when(
                isset($this->children_count),
                $this->children_count
            ),

            // Timestamps formateados
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * ✅ Cuenta productos propios + de todos los hijos recursivamente
     */
    private function getTotalProductsRecursive(): int
    {
        $count = $this->products_count ?? $this->products()->count();

        if ($this->relationLoaded('children')) {
            foreach ($this->children as $child) {
                $count += (new self($child))->getTotalProductsRecursive();
            }
        }

        return $count;
    }
}
