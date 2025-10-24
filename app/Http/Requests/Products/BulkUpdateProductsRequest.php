<?php
// app/Http/Requests/Products/BulkUpdateProductsRequest.php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // CAMBIAR de false a true
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'exists:products,id',
            'action' => 'required|in:activate,deactivate,feature,unfeature,show_online,hide_online,delete',
        ];
    }

    public function messages(): array
    {
        return [
            'product_ids.required' => 'Debe seleccionar al menos un producto',
            'product_ids.*.exists' => 'Uno o más productos no existen',
            'action.required' => 'Debe especificar una acción',
            'action.in' => 'Acción no válida',
        ];
    }
}
