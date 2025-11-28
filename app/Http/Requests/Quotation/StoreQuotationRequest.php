<?php

namespace App\Http\Requests\Quotation;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
        // return $this->user()->can('create', Quotation::class);
    }
    
    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:entities,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'currency' => 'nullable|in:PEN,USD',
            'exchange_rate' => 'nullable|numeric|min:0',
            'valid_days' => 'nullable|integer|min:1|max:90',
            
            // Snapshot de cliente
            'customer_name' => 'required|string|max:200',
            'customer_document' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:100',
            'customer_phone' => 'nullable|string|max:20',
            
            // Items
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_sku' => 'required|string|max:200',
            'items.*.product_brand' => 'required|string|max:200',
            'items.*.product_name' => 'required|string|max:200',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.purchase_price' => 'required|numeric|min:0',
            'items.*.distribution_price' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.source_type' => 'required|in:warehouse,supplier',
            'items.*.warehouse_id' => 'required_if:items.*.source_type,warehouse|exists:warehouses,id',
            'items.*.supplier_id' => 'required_if:items.*.source_type,supplier|exists:entities,id',
            'items.*.supplier_product_id' => 'nullable|exists:supplier_products,id',
            
            // Costos adicionales
            'shipping_cost' => 'nullable|numeric|min:0',
            'packaging_cost' => 'nullable|numeric|min:0',
            'assembly_cost' => 'nullable|numeric|min:0',
            
            'notes' => 'nullable|string|max:1000',
        ];
    }
    
    public function messages(): array
    {
        return [
            'items.required' => 'Debe agregar al menos un producto a la cotización',
            'items.*.warehouse_id.required_if' => 'Debe seleccionar el almacén de origen',
            'items.*.supplier_id.required_if' => 'Debe seleccionar el proveedor',
        ];
    }
}
