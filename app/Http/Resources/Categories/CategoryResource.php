<?php

namespace App\Http\Resources\Categories;

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
            // Campos detallados (Solo en Show)
            'description' => $this->when($request->routeIs('*.show'), $this->description),

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

            // ✅ INDICA SI USA DEFAULT DEL SISTEMA (Solo en Show)
            'uses_system_default' => $this->when($request->routeIs('*.show'), function () {
                return $this->usesSystemDefault();
            }),

            // ✅ CONTEO DE PRODUCTOS
            'products_count' => $this->when(
                isset($this->products_count),
                $this->products_count ?? 0
            ),

            // Total recursivo (Heavy - Solo en Show)
            'total_products' => $this->when(
                $request->routeIs('*.show'),
                fn() => $this->getTotalProductsRecursive()
            ),

            // Relación con padre (solo info básica, si está cargada)
            'parent' => $this->whenLoaded('parent', function () {
                return [
                    'id' => $this->parent->id,
                    'name' => $this->parent->name,
                    'slug' => $this->parent->slug,
                    'level' => $this->parent->level,
                ];
            }),

            // Subcategorías (en Show, Tree, o si se pide explícitamente)
            'children' => $this->when(
                $request->routeIs('*.show') || $request->routeIs('*.tree') || $request->input('include_children'),
                fn() => CategoryResource::collection($this->whenLoaded('children'))
            ),

            // Contador de hijos (Solo en Show)
            'children_count' => $this->when(
                $request->routeIs('*.show') && isset($this->children_count),
                fn() => $this->children_count
            ),

            // ✅ NUEVO: Indica si tiene hijos (Para el listado, derivado de children_count)
            'has_children' => ($this->children_count ?? 0) > 0,

            // Timestamps (Solo en Show)
            'created_at' => $this->when($request->routeIs('*.show'), $this->created_at?->format('Y-m-d H:i:s')),
            'updated_at' => $this->when($request->routeIs('*.show'), $this->updated_at?->format('Y-m-d H:i:s')),
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
