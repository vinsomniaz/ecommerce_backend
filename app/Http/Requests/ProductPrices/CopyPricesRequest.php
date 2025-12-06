<?php


// ============================================================================
namespace App\Http\Requests\ProductPrices;

use Illuminate\Foundation\Http\FormRequest;

class CopyPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_price_list_id' => 'required|exists:price_lists,id',
            'target_price_list_id' => 'required|exists:price_lists,id|different:source_price_list_id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'adjustment_percentage' => 'nullable|numeric',
        ];
    }

    public function messages(): array
    {
        return [
            'source_price_list_id.required' => 'La lista de origen es obligatoria',
            'target_price_list_id.required' => 'La lista de destino es obligatoria',
            'target_price_list_id.different' => 'Las listas deben ser diferentes',
        ];
    }
}

