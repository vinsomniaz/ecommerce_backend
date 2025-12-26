<?php

namespace App\Http\Resources\Coupons;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isShow = $request->routeIs('*.show');

        // =========================================================
        // CAMPOS PARA LISTA (Index) - Solo lo esencial
        // =========================================================
        $data = [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'type_label' => $this->type_name,
            'value' => (float) $this->value,
            'discount_display' => $this->type === 'percentage'
                ? "{$this->value}%"
                : "S/. " . number_format($this->value, 2),

            // Control de uso
            'usage_count' => $this->usage_count,
            'usage_limit' => $this->usage_limit,

            // Vigencia
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),

            // Estado
            'status' => $this->status,
            'status_label' => $this->status_label,
            'active' => $this->active,
        ];

        // =========================================================
        // CAMPOS ADICIONALES SOLO PARA SHOW (Detalle/Formulario)
        // =========================================================
        if ($isShow) {
            $data = array_merge($data, [
                // Descripción y límites
                'description' => $this->description,
                'min_amount' => (float) $this->min_amount,
                'max_discount' => $this->max_discount ? (float) $this->max_discount : null,
                'usage_per_user' => $this->usage_per_user,

                // Aplicación
                'applies_to' => $this->applies_to,

                // IDs de relaciones para el formulario
                'category_ids' => $this->whenLoaded(
                    'categories',
                    fn() =>
                    $this->categories->pluck('id')->toArray()
                ),
                'product_ids' => $this->whenLoaded(
                    'products',
                    fn() =>
                    $this->products->pluck('id')->toArray()
                ),

                // Estadísticas de uso (ya pre-cargado con withSum)
                'total_discount_granted' => (float) ($this->usages_sum_discount_applied ?? 0),

                // Auditoría
                'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            ]);
        }

        return $data;
    }
}
