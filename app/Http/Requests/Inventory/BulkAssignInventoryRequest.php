<?php
// app/Http/Requests/Inventory/BulkAssignInventoryRequest.php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class BulkAssignInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|integer|exists:products,id',
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
            'product_ids.required' => 'Debe seleccionar al menos un producto',
            'product_ids.min' => 'Debe seleccionar al menos un producto',
            'product_ids.*.exists' => 'Uno o más productos no existen',
            'warehouse_ids.required' => 'Debe seleccionar al menos un almacén',
            'warehouse_ids.min' => 'Debe seleccionar al menos un almacén',
            'warehouse_ids.*.exists' => 'Uno o más almacenes no existen',
            'sale_price.numeric' => 'El precio debe ser un número válido',
            'profit_margin.max' => 'El margen no puede ser mayor a 100%',
        ];
    }

    /**
     * Validación adicional personalizada
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Verificar que no se intenten asignar más de 50 productos a la vez
            if (count($this->product_ids) > 50) {
                $validator->errors()->add(
                    'product_ids',
                    'No se pueden asignar más de 50 productos a la vez'
                );
            }

            // Verificar que no se intenten asignar a más de 10 almacenes a la vez
            if (count($this->warehouse_ids) > 10) {
                $validator->errors()->add(
                    'warehouse_ids',
                    'No se pueden asignar a más de 10 almacenes a la vez'
                );
            }
        });
    }
}
