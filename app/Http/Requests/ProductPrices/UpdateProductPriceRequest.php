<?php


namespace App\Http\Requests\ProductPrices;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'price' => 'sometimes|required|numeric|min:0',
            'min_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|in:PEN,USD',
            'min_quantity' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
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
            if ($this->filled(['price', 'min_price']) && $this->min_price > $this->price) {
                $validator->errors()->add('min_price', 'El precio mínimo no puede ser mayor al precio de venta');
            }
        });
    }
}

// ============================================================================

// app/Http/Requests/ProductPrices/BulkUpdatePricesRequest.php

namespace App\Http\Requests\ProductPrices;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdatePricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'exists:products,id',

            'price_list_id' => 'required|exists:price_lists,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',

            'adjustment_type' => 'required|in:percentage,fixed,replace',
            'adjustment_value' => 'required|numeric',

            'apply_to_min_price' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'product_ids.required' => 'Debe seleccionar al menos un producto',
            'product_ids.*.exists' => 'Uno o más productos no existen',

            'price_list_id.required' => 'La lista de precios es obligatoria',
            'price_list_id.exists' => 'La lista de precios no existe',

            'adjustment_type.required' => 'El tipo de ajuste es obligatorio',
            'adjustment_type.in' => 'Tipo de ajuste no válido',

            'adjustment_value.required' => 'El valor de ajuste es obligatorio',
            'adjustment_value.numeric' => 'El valor debe ser numérico',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'apply_to_min_price' => $this->boolean('apply_to_min_price', false),
        ]);
    }
}

// ============================================================================

// app/Http/Requests/ProductPrices/CalculatePriceRequest.php

namespace App\Http\Requests\ProductPrices;

use Illuminate\Foundation\Http\FormRequest;

class CalculatePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'margin_percentage' => 'required|numeric|min:0|max:1000',
            'base_cost' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'El producto es obligatorio',
            'product_id.exists' => 'El producto no existe',
            'margin_percentage.required' => 'El margen es obligatorio',
            'margin_percentage.numeric' => 'El margen debe ser numérico',
            'margin_percentage.min' => 'El margen debe ser mayor o igual a 0',
        ];
    }
}
