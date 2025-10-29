<?php
// app/Http/Requests/Inventory/UpdateInventoryRequest.php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sale_price' => 'nullable|numeric|min:0',
            'profit_margin' => 'nullable|numeric|min:0|max:100',
            'min_sale_price' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'sale_price.numeric' => 'El precio debe ser un número válido',
            'sale_price.min' => 'El precio no puede ser negativo',
            'profit_margin.numeric' => 'El margen debe ser un número válido',
            'profit_margin.min' => 'El margen no puede ser negativo',
            'profit_margin.max' => 'El margen no puede ser mayor a 100%',
            'min_sale_price.numeric' => 'El precio mínimo debe ser un número válido',
            'min_sale_price.min' => 'El precio mínimo no puede ser negativo',
        ];
    }
}
