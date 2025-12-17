<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;

class GetQuotationProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => 'required|exists:warehouses,id',
            'supplier_id' => 'nullable|exists:entities,id',
            'category_id' => 'nullable|exists:categories,id',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'Debe seleccionar un almacén',
            'warehouse_id.exists' => 'El almacén seleccionado no existe',
            'supplier_id.exists' => 'El proveedor seleccionado no existe',
            'category_id.exists' => 'La categoría seleccionada no existe',
        ];
    }
}
