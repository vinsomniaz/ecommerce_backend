<?php
// app/Http/Requests/Products/StoreProductRequest.php

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
            // CAMPOS OBLIGATORIOS
            'primary_name' => ['required', 'string', 'min:3', 'max:200'],
            'category_id' => 'required|exists:categories,id',

            // CAMPOS OPCIONALES
            'sku' => 'nullable|string|max:50|unique:products,sku',
            'secondary_name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:5000',
            'brand' => 'nullable|string|max:100',
            'min_stock' => 'nullable|integer|min:0',
            'unit_measure' => 'nullable|string|max:10',
            'tax_type' => 'nullable|string|max:2',
            'weight' => 'nullable|numeric|min:0',
            'barcode' => 'nullable|string|max:50',
            'distribution_price' => 'nullable|numeric|min:0',

            // ESTADOS
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'visible_online' => 'boolean',
            'is_new' => 'boolean',

            // ✅ NUEVO: PRECIOS POR ALMACÉN
            'warehouse_prices' => 'nullable|array',
            'warehouse_prices.*.warehouse_id' => 'required|exists:warehouses,id',
            'warehouse_prices.*.sale_price' => 'required|numeric|min:0',
            'warehouse_prices.*.min_sale_price' => 'required|numeric|min:0',

            // ATRIBUTOS
            'attributes' => 'nullable|array',
            'attributes.*.name' => 'required|string|max:50',
            'attributes.*.value' => 'required|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'primary_name.required' => 'El nombre del producto es obligatorio',
            'primary_name.max' => 'El nombre no puede exceder 200 caracteres',
            'category_id.required' => 'La categoría es obligatoria',
            'category_id.exists' => 'La categoría seleccionada no existe',
            'sku.unique' => 'Este SKU ya está registrado en el sistema',
            'sku.max' => 'El SKU no puede exceder 50 caracteres',
            'min_stock.min' => 'El stock mínimo debe ser mayor o igual a 0',

            // ✅ NUEVO: Mensajes para precios por almacén
            'warehouse_prices.array' => 'Los precios de almacén deben ser un array',
            'warehouse_prices.*.warehouse_id.required' => 'El ID del almacén es obligatorio',
            'warehouse_prices.*.warehouse_id.exists' => 'El almacén seleccionado no existe',
            'warehouse_prices.*.sale_price.required' => 'El precio de venta es obligatorio',
            'warehouse_prices.*.sale_price.numeric' => 'El precio de venta debe ser un número',
            'warehouse_prices.*.sale_price.min' => 'El precio de venta debe ser mayor o igual a 0',
            'warehouse_prices.*.min_sale_price.required' => 'El precio mínimo es obligatorio',
            'warehouse_prices.*.min_sale_price.numeric' => 'El precio mínimo debe ser un número',
            'warehouse_prices.*.min_sale_price.min' => 'El precio mínimo debe ser mayor o igual a 0',

            'attributes.*.name.required' => 'El nombre del atributo es obligatorio',
            'attributes.*.value.required' => 'El valor del atributo es obligatorio',
        ];
    }

    public function attributes(): array
    {
        return [
            'primary_name' => 'nombre del producto',
            'category_id' => 'categoría',
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
        ]);
    }

    // ✅ NUEVO: Validación adicional
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $warehousePrices = $this->input('warehouse_prices', []);

            foreach ($warehousePrices as $index => $prices) {
                // Validar que min_sale_price no sea mayor que sale_price
                if (isset($prices['sale_price']) && isset($prices['min_sale_price'])) {
                    if ($prices['min_sale_price'] > $prices['sale_price']) {
                        $validator->errors()->add(
                            "warehouse_prices.{$index}.min_sale_price",
                            'El precio mínimo no puede ser mayor al precio de venta'
                        );
                    }
                }
            }
        });
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
