<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'sometimes|exists:entities,id', // Can send null to keep current? adjust logic in service
            'warehouse_id' => 'sometimes|exists:warehouses,id',
            'series' => 'sometimes|string|max:10',
            'number' => 'sometimes|string|max:20',
            'date' => 'sometimes|date',
            'currency' => 'sometimes|string|in:PEN,USD',
            'exchange_rate' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
            // For now, complex product updates might be restricted or require full replacement
            // Let's allow basic header updates primarily
        ];
    }
}
