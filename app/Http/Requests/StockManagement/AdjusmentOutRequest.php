<?php
// app/Http/Requests/Stock/AdjustmentOutRequest.php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustmentOutRequest extends FormRequest
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
            'quantity' => 'required|integer|min:1',
            'reason' => [
                'required',
                Rule::in(['damaged', 'expired', 'lost', 'correction', 'sample', 'other'])
            ],
            'notes' => 'nullable|string|max:500',
        ];
    }
}
