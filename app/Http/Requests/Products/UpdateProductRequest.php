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
        $isPatch = $this->isMethod('patch');
        $requiredRule = $isPatch ? 'sometimes' : 'required';

        return [
            // Campos obligatorios en PUT, opcionales en PATCH
            'primary_name' => [$requiredRule, 'string', 'min:3', 'max:200'],
            'category_id' => [$requiredRule, 'exists:categories,id'],
            'unit_price' => [$requiredRule, 'numeric', 'min:0.01'],
            // Campos opcionales
            'sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('products')->ignore($productId),
            ],
            'secondary_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:100'],
            'cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'min_stock' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'unit_measure' => ['sometimes', 'nullable', 'string', 'in:NIU,UND,KGM,MTR,LTR'],
            'tax_type' => ['sometimes', 'nullable', 'string', 'in:10,20,30'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'is_featured' => ['sometimes', 'nullable', 'boolean'],
            'visible_online' => ['sometimes', 'nullable', 'boolean'],
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
