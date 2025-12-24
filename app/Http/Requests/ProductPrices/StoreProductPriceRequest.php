<?php
// app/Http/Requests/ProductPrices/StoreProductPriceRequest.php

namespace App\Http\Requests\ProductPrices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'price_list_id' => 'required|exists:price_lists,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',

            'price' => 'required|numeric|min:0',
            'min_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|in:PEN,USD',
            'min_quantity' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'El producto es obligatorio',
            'product_id.exists' => 'El producto seleccionado no existe',
            'price_list_id.required' => 'La lista de precios es obligatoria',
            'price_list_id.exists' => 'La lista de precios no existe',
            'warehouse_id.exists' => 'El almacén seleccionado no existe',

            'price.required' => 'El precio es obligatorio',
            'price.numeric' => 'El precio debe ser un número',
            'price.min' => 'El precio debe ser mayor o igual a 0',

            'min_price.numeric' => 'El precio mínimo debe ser un número',
            'min_price.min' => 'El precio mínimo debe ser mayor o igual a 0',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar que min_price no sea mayor que price
            if ($this->filled(['price', 'min_price']) && $this->min_price > $this->price) {
                $validator->errors()->add('min_price', 'El precio mínimo no puede ser mayor al precio de venta');
            }

            // Validar que no exista un precio duplicado activo
            $exists = \App\Models\ProductPrice::where('product_id', $this->product_id)
                ->where('price_list_id', $this->price_list_id)
                ->where('warehouse_id', $this->warehouse_id)
                ->where('min_quantity', $this->min_quantity ?? 1)
                ->where('is_active', true)
                ->exists();

            if ($exists) {
                $validator->errors()->add('product_id', 'Ya existe un precio activo con estas características');
            }
        });
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'currency' => $this->input('currency', 'PEN'),
            'min_quantity' => $this->input('min_quantity', 1),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
