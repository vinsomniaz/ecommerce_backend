<?php

namespace App\Http\Resources\Categories;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcommerceCategoryResource extends JsonResource
{
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
            // 'is_active' => $this->is_active, // Se asume true si se está listando en el frontend

            // Omitimos márgenes y configuraciones internas (min_margin, normal_margin, inherits, system_default)

            // ✅ CONTEO DE PRODUCTOS (Útil para mostrar en menús)
            'products_count' => $this->when(
                isset($this->products_count),
                $this->products_count ?? 0
            ),
            // 'total_products' => $this->getTotalProductsRecursive(), // Puede ser costoso, evaluar si se necesita

            // Relación con padre (solo info básica)
            'parent' => $this->whenLoaded('parent', function () {
                return [
                    'id' => $this->parent->id,
                    'name' => $this->parent->name,
                    'slug' => $this->parent->slug,
                    'level' => $this->parent->level,
                ];
            }),

            // Subcategorías (recursivo - usar SELF)
            'children' => EcommerceCategoryResource::collection($this->whenLoaded('children')),

            // Contador de hijos
            'children_count' => $this->when(
                isset($this->children_count),
                $this->children_count
            ),
        ];
    }
}
