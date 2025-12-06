<?php

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
