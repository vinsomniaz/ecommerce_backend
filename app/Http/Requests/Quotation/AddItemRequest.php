<?php
namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddItemRequest extends FormRequest
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
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
            'product_name' => 'required|string|max:255',
            'product_sku' => 'nullable|string|max:100',
            'product_brand' => 'nullable|string|max:100',
            
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            
            // Origen del producto
            'source_type' => [
                'required',
                'string',
                Rule::in(['warehouse', 'supplier']),
            ],
            
            // Si es de almacén
            'warehouse_id' => [
                'required_if:source_type,warehouse',
                'nullable',
                'integer',
                'exists:warehouses,id',
            ],
            
            // Si es de proveedor
            'supplier_id' => [
                'required_if:source_type,supplier',
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
            
            // Precio de compra (para calcular márgenes)
            'purchase_price' => 'required|numeric|min:0',
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
            'product_id.required' => 'El producto es obligatorio',
            'product_id.exists' => 'El producto seleccionado no existe',
            'product_name.required' => 'El nombre del producto es obligatorio',
            'quantity.required' => 'La cantidad es obligatoria',
            'quantity.min' => 'La cantidad mínima es 1',
            'unit_price.required' => 'El precio unitario es obligatorio',
            'unit_price.min' => 'El precio unitario debe ser mayor o igual a 0',
            'source_type.required' => 'El origen del producto es obligatorio',
            'source_type.in' => 'El origen debe ser almacén o proveedor',
            'warehouse_id.required_if' => 'El almacén es obligatorio cuando el origen es almacén',
            'warehouse_id.exists' => 'El almacén seleccionado no existe',
            'supplier_id.required_if' => 'El proveedor es obligatorio cuando el origen es proveedor',
            'supplier_id.exists' => 'El proveedor seleccionado no existe',
            'purchase_price.required' => 'El precio de compra es obligatorio',
            'purchase_price.min' => 'El precio de compra debe ser mayor o igual a 0',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Si no viene source_type, inferirlo
        if (!$this->has('source_type')) {
            if ($this->has('warehouse_id')) {
                $this->merge(['source_type' => 'warehouse']);
            } elseif ($this->has('supplier_id')) {
                $this->merge(['source_type' => 'supplier']);
            }
        }

        // Convertir is_requested_from_supplier a booleano
        if ($this->has('is_requested_from_supplier')) {
            $this->merge([
                'is_requested_from_supplier' => filter_var(
                    $this->is_requested_from_supplier,
                    FILTER_VALIDATE_BOOLEAN
                ),
            ]);
        }
    }
}