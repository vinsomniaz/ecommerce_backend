<?php
// app/Http/Requests/Inventory/StoreInventoryRequest.php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|exists:products,id',
            'warehouse_ids' => 'required|array|min:1',
            'warehouse_ids.*' => 'required|integer|exists:warehouses,id',
            'sale_price' => 'nullable|numeric|min:0',
            'profit_margin' => 'nullable|numeric|min:0|max:100',
            'min_sale_price' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'El producto es obligatorio',
            'product_id.exists' => 'El producto no existe',
            'warehouse_ids.required' => 'Debe seleccionar al menos un almacén',
            'warehouse_ids.*.exists' => 'Uno o más almacenes no existen',
            'sale_price.numeric' => 'El precio debe ser un número válido',
            'sale_price.min' => 'El precio no puede ser negativo',
            'profit_margin.numeric' => 'El margen debe ser un número válido',
            'profit_margin.max' => 'El margen no puede ser mayor a 100%',
        ];
    }
}
