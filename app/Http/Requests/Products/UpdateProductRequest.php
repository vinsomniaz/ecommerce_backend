<?php
// app/Http/Requests/Products/UpdateProductRequest.php

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

            // Campos opcionales
            'sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('products')->ignore($productId),
            ],
            'secondary_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:100'],
            'min_stock' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'unit_measure' => ['sometimes', 'nullable', 'string', 'in:NIU,UND,KGM,MTR,LTR'],
            'tax_type' => ['sometimes', 'nullable', 'string', 'in:10,20,30'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:50'],

            // Estados
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'is_featured' => ['sometimes', 'nullable', 'boolean'],
            'visible_online' => ['sometimes', 'nullable', 'boolean'],
            'attributes' => 'nullable|array',
            'attributes.*.name' => 'required|string|max:50',
            'attributes.*.value' => 'required|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'primary_name.required' => 'El nombre del producto es obligatorio',
            'category_id.required' => 'La categoría es obligatoria',
            'category_id.exists' => 'La categoría seleccionada no existe',
            'sku.unique' => 'Este SKU ya está registrado',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
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
