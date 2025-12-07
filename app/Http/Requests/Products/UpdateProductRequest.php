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
            'unit_measure' => ['sometimes', 'nullable', 'string', 'in:NIU,UND,KGM,MTR,LTR,ZZ'],
            'tax_type' => ['sometimes', 'nullable', 'string', 'in:10,20,30'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:50'],
            'initial_cost' => ['sometimes', 'numeric', 'min:0.0001'],

            // Estados
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'is_featured' => ['sometimes', 'nullable', 'boolean'],
            'visible_online' => ['sometimes', 'nullable', 'boolean'],
            'is_new' => ['sometimes', 'nullable', 'boolean'],

            // Atributos personalizados
            'attributes' => 'nullable|array',
            'attributes.*.name' => 'required|string|max:50',
            'attributes.*.value' => 'required|string|max:200',

            // ✅ PRECIOS (NUEVO)
            'prices' => 'nullable|array',
            'prices.*.id' => 'nullable|exists:product_prices,id', // Para actualizar precios existentes
            'prices.*.price_list_id' => 'required|exists:price_lists,id',
            'prices.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.min_price' => 'nullable|numeric|min:0|lte:prices.*.price',
            'prices.*.currency' => 'nullable|string|in:PEN,USD',
            'prices.*.min_quantity' => 'nullable|integer|min:1',
            'prices.*.valid_from' => 'nullable|date',
            'prices.*.valid_to' => 'nullable|date|after:prices.*.valid_from',
            'prices.*.is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'primary_name.required' => 'El nombre del producto es obligatorio',
            'primary_name.min' => 'El nombre debe tener al menos 3 caracteres',
            'primary_name.max' => 'El nombre no puede exceder 200 caracteres',

            'initial_cost.numeric'  => 'El costo inicial debe ser numérico',
            'initial_cost.min'      => 'El costo inicial debe ser mayor a 0',

            'category_id.required' => 'La categoría es obligatoria',
            'category_id.exists' => 'La categoría seleccionada no existe',

            'sku.unique' => 'Este SKU ya está registrado',
            'unit_measure.in' => 'La unidad de medida no es válida',
            'tax_type.in' => 'El tipo de IGV no es válido',

            'attributes.*.name.required' => 'El nombre del atributo es obligatorio',
            'attributes.*.value.required' => 'El valor del atributo es obligatorio',

            // ✅ MENSAJES DE PRECIOS
            'prices.*.id.exists' => 'El precio seleccionado no existe',
            'prices.*.price_list_id.required' => 'La lista de precios es obligatoria',
            'prices.*.price_list_id.exists' => 'La lista de precios seleccionada no existe',
            'prices.*.warehouse_id.exists' => 'El almacén seleccionado no existe',
            'prices.*.price.required' => 'El precio es obligatorio',
            'prices.*.price.min' => 'El precio debe ser mayor o igual a 0',
            'prices.*.min_price.min' => 'El precio mínimo debe ser mayor o igual a 0',
            'prices.*.min_price.lte' => 'El precio mínimo no puede ser mayor al precio normal',
            'prices.*.currency.in' => 'La moneda debe ser PEN o USD',
            'prices.*.min_quantity.min' => 'La cantidad mínima debe ser al menos 1',
            'prices.*.valid_to.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
        ];
    }

    protected function prepareForValidation()
    {
        // ✅ Establecer valores por defecto en precios si se envían
        if ($this->has('prices') && is_array($this->prices)) {
            $prices = collect($this->prices)->map(function ($price) {
                $defaults = [
                    'currency' => 'PEN',
                    'min_quantity' => 1,
                    'is_active' => true,
                ];

                // Solo agregar valid_from si no existe el ID (precio nuevo)
                if (!isset($price['id'])) {
                    $defaults['valid_from'] = now()->toDateTimeString();
                }

                return array_merge($defaults, $price);
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
