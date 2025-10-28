<?php
// app/Http/Requests/StoreProductRequest.php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'primary_name' => ['required', 'string', 'min:3', 'max:200'],
            'category_id' => 'required|exists:categories,id',
            'unit_price' => 'required|numeric|min:0.01',

            'sku' => 'nullable|string|max:50|unique:products,sku',
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
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'primary_name.required' => 'El nombre del producto es obligatorio',
            'primary_name.max' => 'El nombre no puede exceder 200 caracteres',
            'category_id.required' => 'La categoría es obligatoria',
            'category_id.exists' => 'La categoría seleccionada no existe',
            'unit_price.required' => 'El precio unitario es obligatorio',
            'unit_price.min' => 'El precio unitario debe ser mayor a 0',
            'sku.unique' => 'Este SKU ya está registrado en el sistema',
            'sku.max' => 'El SKU no puede exceder 50 caracteres',
            'cost_price.min' => 'El costo debe ser mayor o igual a 0',
            'min_stock.min' => 'El stock mínimo debe ser mayor o igual a 0',
            'images.max' => 'No puede subir más de 5 imágenes',
            'images.*.image' => 'El archivo debe ser una imagen',
            'images.*.mimes' => 'Solo se permiten imágenes JPG, JPEG, PNG o WEBP',
            'images.*.max' => 'Cada imagen no debe exceder 2MB',
        ];
    }

    public function attributes(): array
    {
        return [
            'primary_name' => 'nombre del producto',
            'category_id' => 'categoría',
            'unit_price' => 'precio unitario',
            'cost_price' => 'costo',
            'min_stock' => 'stock mínimo',
            'unit_measure' => 'unidad de medida',
            'tax_type' => 'tipo de IGV',
        ];
    }

    protected function prepareForValidation()
    {
        // Establecer valores por defecto
        $this->merge([
            'min_stock' => $this->input('min_stock', 5),
            'unit_measure' => $this->input('unit_measure', 'NIU'),
            'tax_type' => $this->input('tax_type', '10'),
            'is_active' => $this->boolean('is_active', true),
            'is_featured' => $this->boolean('is_featured', false),
            'visible_online' => $this->boolean('visible_online', true),
            'cost_price' => $this->input('cost_price', 0),
        ]);
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
