<?php

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
