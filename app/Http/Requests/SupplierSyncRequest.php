<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El middleware de autenticación se maneja en la ruta
    }

    public function rules(): array
    {
        return [
            // Metadata del scraper
            'supplier_id' => 'required|integer|exists:entities,id',
            'fetched_at' => 'required|date',
            'margin_percent' => 'nullable|numeric|min:0|max:100',
            'source_totals' => 'nullable|array',
            'source_totals.total_products' => 'nullable|integer|min:0',
            'source_totals.images_extracted' => 'nullable|integer|min:0',
            'hash' => 'required|string|size:64',

            // Items (productos)
            'items' => 'required|array|min:1',
            'items.*.supplier_sku' => 'required|string|max:160',
            'items.*.name' => 'required|string|max:255',
            'items.*.brand' => 'nullable|string|max:100',
            'items.*.supplier_category' => 'nullable|string|max:160',
            'items.*.category_suggested' => 'nullable|string|max:160',
            'items.*.location' => 'nullable|string|max:100',
            'items.*.url' => 'nullable|string|max:500',  // Cambiado de 'url' a 'string'
            'items.*.image_url' => 'nullable|string|max:500',  // Cambiado de 'url' a 'string'
            'items.*.stock_qty' => 'nullable|integer|min:0',
            'items.*.stock_text' => 'nullable|string|max:255',
            'items.*.is_available' => 'nullable|boolean',
            'items.*.currency' => 'required|string|in:PEN,USD,EUR',
            'items.*.supplier_price' => 'nullable|numeric|min:0',
            'items.*.price_suggested' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'El ID del proveedor es obligatorio',
            'supplier_id.exists' => 'El proveedor especificado no existe',
            'fetched_at.required' => 'La fecha de extracción es obligatoria',
            'fetched_at.date' => 'La fecha de extracción debe ser una fecha válida',
            'hash.required' => 'El hash del payload es obligatorio',
            'hash.size' => 'El hash debe tener exactamente 64 caracteres',
            'items.required' => 'Debe incluir al menos un producto',
            'items.min' => 'Debe incluir al menos un producto',
            'items.*.supplier_sku.required' => 'El SKU del proveedor es obligatorio para cada producto',
            'items.*.name.required' => 'El nombre del producto es obligatorio',
            'items.*.currency.required' => 'La moneda es obligatoria para cada producto',
            'items.*.currency.in' => 'La moneda debe ser PEN, USD o EUR',
        ];
    }

    /**
     * Prepara los datos para validación
     */
    protected function prepareForValidation(): void
    {
        // Convertir supplier_price y price_suggested de los items si vienen como strings
        if ($this->has('items')) {
            $items = collect($this->items)->map(function ($item) {
                return array_merge($item, [
                    'supplier_price' => isset($item['supplier_price']) ? (float) $item['supplier_price'] : null,
                    'price_suggested' => isset($item['price_suggested']) ? (float) $item['price_suggested'] : null,
                    'stock_qty' => isset($item['stock_qty']) ? (int) $item['stock_qty'] : null,
                    'is_available' => isset($item['is_available']) ? (bool) $item['is_available'] : null,
                ]);
            })->toArray();

            $this->merge(['items' => $items]);
        }
    }
}
