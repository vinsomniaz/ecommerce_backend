<?php
// app/Http/Requests/UpdateProductRequest.php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')->id;

        return [
            'primary_name' => 'required|string|max:200',
            'category_id' => 'required|exists:categories,id',
            'unit_price' => 'required|numeric|min:0.01',

            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->ignore($productId)
            ],
            'secondary_name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:5000',
            'brand' => 'nullable|string|max:100',
            'cost_price' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'unit_measure' => 'nullable|string|max:10',
            'tax_type' => 'nullable|string|max:2',
            'weight' => 'nullable|numeric|min:0',

            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'visible_online' => 'boolean',

            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,webp|max:2048',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'integer|exists:media,id',
        ];
    }

    public function messages(): array
    {
        return [
            'primary_name.required' => 'El nombre del producto es obligatorio',
            'category_id.required' => 'La categoría es obligatoria',
            'category_id.exists' => 'La categoría seleccionada no existe',
            'unit_price.required' => 'El precio unitario es obligatorio',
            'unit_price.min' => 'El precio unitario debe ser mayor a 0',
            'sku.unique' => 'Este SKU ya está registrado',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        // Si el error es de SKU duplicado, retornar 409
        if ($validator->errors()->has('sku')) {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first('sku'),
                    'errors' => $validator->errors(),
                ], 409)
            );
        }

        parent::failedValidation($validator);
    }
}
