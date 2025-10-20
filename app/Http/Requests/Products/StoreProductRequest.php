<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Maneja con middleware de permisos
    }

    public function rules()
    {
        return [
            'sku' => ['nullable', 'unique:products,sku', 'max:50'],
            'primary_name' => ['required', 'string', 'max:200'],
            'secondary_name' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'unit_price' => ['required', 'numeric', 'min:0.01'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'unit_measure' => ['nullable', 'string', 'max:10'],
            'tax_type' => ['nullable', 'string', 'max:10'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'visible_online' => ['nullable', 'boolean'],
            'initial_stock' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages()
    {
        return [
            'primary_name.required' => 'El nombre del producto es obligatorio',
            'category_id.required' => 'La categoría es obligatoria',
            'category_id.exists' => 'La categoría seleccionada no existe',
            'unit_price.required' => 'El precio unitario es obligatorio',
            'unit_price.min' => 'El precio debe ser mayor a 0',
            'sku.unique' => 'El SKU ya está registrado',
        ];
    }
}