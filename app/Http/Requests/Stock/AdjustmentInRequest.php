<?php
// app/Http/Requests/Stock/AdjustmentInRequest.php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustmentInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer|min:1|max:999999',
            'unit_cost' => 'required|numeric|min:0|max:999999.99',
            'new_sale_price' => 'required|numeric|min:0|max:999999.99',
            'reason' => [
                'required',
                Rule::in(['purchase','manual_entry', 'found_stock', 'correction', 'return', 'other'])
            ],
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            "new_sale_price.required" => "El nuevo precio de venta debe enviarse",
        ];
    }
}
