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
            'unit_measure' => 'nullable|string|in:NIU,UND,KGM,MTR,LTR,ZZ',
            'tax_type' => 'nullable|string|in:10,20,30',
            'weight' => 'nullable|numeric|min:0',
            'barcode' => 'nullable|string|max:50',
            'initial_cost' => 'required|numeric|min:0.0001',

            // ESTADOS
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'visible_online' => 'boolean',
            'is_new' => 'boolean',

            // ATRIBUTOS PERSONALIZADOS
            'attributes' => 'nullable|array',
            'attributes.*.name' => 'required|string|max:50',
            'attributes.*.value' => 'required|string|max:200',

            // ✅ PRECIOS (NUEVO)
            'prices' => 'nullable|array',
            'prices.*.price_list_id' => 'required|exists:price_lists,id',
            'prices.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.min_price' => 'nullable|numeric|min:0|lte:prices.*.price',
            'prices.*.currency' => 'nullable|string|in:PEN,USD',
            'prices.*.min_quantity' => 'nullable|integer|min:1',
            'prices.*.is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'primary_name.required' => 'El nombre del producto es obligatorio',
            'primary_name.min' => 'El nombre debe tener al menos 3 caracteres',
            'primary_name.max' => 'El nombre no puede exceder 200 caracteres',

            'initial_cost.required' => 'El costo inicial es obligatorio',
            'initial_cost.numeric'  => 'El costo inicial debe ser numérico',
            'initial_cost.min'      => 'El costo inicial debe ser mayor a 0',

            'category_id.required' => 'La categoría es obligatoria',
            'category_id.exists' => 'La categoría seleccionada no existe',

            'sku.unique' => 'Este SKU ya está registrado en el sistema',
            'sku.max' => 'El SKU no puede exceder 50 caracteres',

            'min_stock.min' => 'El stock mínimo debe ser mayor o igual a 0',
            'unit_measure.in' => 'La unidad de medida no es válida',
            'tax_type.in' => 'El tipo de IGV no es válido',

            'attributes.*.name.required' => 'El nombre del atributo es obligatorio',
            'attributes.*.value.required' => 'El valor del atributo es obligatorio',

            // ✅ MENSAJES DE PRECIOS
            'prices.*.price_list_id.required' => 'La lista de precios es obligatoria',
            'prices.*.price_list_id.exists' => 'La lista de precios seleccionada no existe',
            'prices.*.warehouse_id.exists' => 'El almacén seleccionado no existe',
            'prices.*.price.required' => 'El precio es obligatorio',
            'prices.*.price.min' => 'El precio debe ser mayor o igual a 0',
            'prices.*.min_price.min' => 'El precio mínimo debe ser mayor o igual a 0',
            'prices.*.min_price.lte' => 'El precio mínimo no puede ser mayor al precio normal',
            'prices.*.currency.in' => 'La moneda debe ser PEN o USD',
            'prices.*.min_quantity.min' => 'La cantidad mínima debe ser al menos 1',
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
            'is_new' => $this->boolean('is_new', false),
        ]);

        // ✅ Establecer valores por defecto en precios
        if ($this->has('prices') && is_array($this->prices)) {
            $prices = collect($this->prices)->map(function ($price) {
                return array_merge([
                    'currency' => 'PEN',
                    'min_quantity' => 1,
                    'is_active' => true,
                ], $price);
            })->toArray();

            $this->merge(['prices' => $prices]);
        }
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

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('prices') && is_array($this->prices)) {
                $combinations = [];

                foreach ($this->prices as $index => $price) {
                    $key = sprintf(
                        '%s-%s',
                        $price['price_list_id'] ?? 'null',
                        $price['warehouse_id'] ?? 'null'
                    );

                    if (in_array($key, $combinations)) {
                        $validator->errors()->add(
                            "prices.{$index}",
                            'Ya existe un precio con la misma lista y almacén en este formulario'
                        );
                    }

                    $combinations[] = $key;
                }
            }
        });
    }
}
