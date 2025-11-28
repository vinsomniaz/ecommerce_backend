<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Datos del producto (opcionales para update)
            'product_name' => 'sometimes|string|max:255',
            'product_sku' => 'nullable|string|max:100',
            'product_brand' => 'nullable|string|max:100',
            
            // Cantidad y precios
            'quantity' => 'sometimes|integer|min:1',
            'unit_price' => 'sometimes|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            
            // Origen del producto
            'source_type' => [
                'sometimes',
                'string',
                Rule::in(['warehouse', 'supplier']),
            ],
            
            // Almacén
            'warehouse_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:warehouses,id',
            ],
            
            // Proveedor
            'supplier_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:entities,id',
            ],
            'supplier_product_id' => [
                'nullable',
                'integer',
                'exists:supplier_products,id',
            ],
            'is_requested_from_supplier' => 'nullable|boolean',
            
            // Precio de compra
            'purchase_price' => 'sometimes|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_name.string' => 'El nombre del producto debe ser texto',
            'product_name.max' => 'El nombre del producto no puede exceder 255 caracteres',
            'quantity.integer' => 'La cantidad debe ser un número entero',
            'quantity.min' => 'La cantidad mínima es 1',
            'unit_price.numeric' => 'El precio unitario debe ser un número',
            'unit_price.min' => 'El precio unitario debe ser mayor o igual a 0',
            'discount.numeric' => 'El descuento debe ser un número',
            'discount.min' => 'El descuento debe ser mayor o igual a 0',
            'source_type.in' => 'El origen debe ser almacén o proveedor',
            'warehouse_id.integer' => 'El ID del almacén debe ser un número',
            'warehouse_id.exists' => 'El almacén seleccionado no existe',
            'supplier_id.integer' => 'El ID del proveedor debe ser un número',
            'supplier_id.exists' => 'El proveedor seleccionado no existe',
            'supplier_product_id.integer' => 'El ID del producto del proveedor debe ser un número',
            'supplier_product_id.exists' => 'El producto del proveedor seleccionado no existe',
            'purchase_price.numeric' => 'El precio de compra debe ser un número',
            'purchase_price.min' => 'El precio de compra debe ser mayor o igual a 0',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir is_requested_from_supplier a booleano si está presente
        if ($this->has('is_requested_from_supplier')) {
            $this->merge([
                'is_requested_from_supplier' => filter_var(
                    $this->is_requested_from_supplier,
                    FILTER_VALIDATE_BOOLEAN
                ),
            ]);
        }

        // Limpiar valores nulos innecesarios
        $data = $this->all();
        foreach ($data as $key => $value) {
            if ($value === '' || $value === 'null') {
                $data[$key] = null;
            }
        }
        $this->replace($data);
    }

    /**
     * Get validated data with only non-null values.
     */
    public function validatedOnly(): array
    {
        return array_filter(
            $this->validated(),
            fn($value) => $value !== null
        );
    }
}