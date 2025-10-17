<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $productId = $this->route('product');

        return [
            'sku' => ['nullable', Rule::unique('products', 'sku')->ignore($productId), 'max:50'],
            'primary_name' => ['nullable', 'string', 'max:200'],
            'secondary_name' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'unit_price' => ['nullable', 'numeric', 'min:0.01'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'unit_measure' => ['nullable', 'string', 'max:10'],
            'tax_type' => ['nullable', 'string', 'max:10'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'visible_online' => ['nullable', 'boolean'],
        ];
    }

    public function messages()
    {
        return [
            'category_id.exists' => 'La categoría seleccionada no existe',
            'unit_price.min' => 'El precio debe ser mayor a 0',
            'sku.unique' => 'El SKU ya está registrado',
        ];
    }
}
