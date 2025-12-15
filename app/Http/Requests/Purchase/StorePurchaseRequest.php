<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Validar permiso en controller o middleware
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:entities,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'series' => 'required|string|max:20',
            'number' => 'required|string|max:20',
            'date' => 'required|date',
            'currency' => 'required|in:PEN,USD',
            'exchange_rate' => 'nullable|numeric|min:0',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.price' => 'required|numeric|min:0',
            'payment_status' => 'nullable|in:pending,paid,partial',
            'notes' => 'nullable|string',
        ];
    }
}
