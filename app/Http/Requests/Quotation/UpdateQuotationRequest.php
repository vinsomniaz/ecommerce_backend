<?php

namespace App\Http\Requests\Quotation;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuotationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');
        
        // Solo se pueden actualizar cotizaciones en estado draft
        if ($quotation && $quotation->status !== 'draft') {
            return false;
        }
        
        // return $this->user()->can('update', $quotation);
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
            // Datos principales (opcionales en update)
            'customer_id' => 'sometimes|required|exists:entities,id',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            
            // Moneda y cambio
            'currency' => [
                'sometimes',
                'string',
                Rule::in(['PEN', 'USD']),
            ],
            'exchange_rate' => 'sometimes|numeric|min:0',
            'valid_days' => 'sometimes|integer|min:1|max:90',
            
            // Snapshot de cliente (opcionales)
            'customer_name' => 'sometimes|required|string|max:200',
            'customer_document' => 'sometimes|required|string|max:20',
            'customer_email' => 'nullable|email|max:100',
            'customer_phone' => 'nullable|string|max:20',
            
            // Costos adicionales
            'shipping_cost' => 'nullable|numeric|min:0',
            'packaging_cost' => 'nullable|numeric|min:0',
            'assembly_cost' => 'nullable|numeric|min:0',
            
            // Observaciones y notas
            'observations' => 'nullable|string|max:1000',
            'internal_notes' => 'nullable|string|max:1000',
            
            // Términos y condiciones
            'terms_conditions' => 'nullable|string|max:2000',
            'payment_terms' => 'nullable|string|max:500',
            'delivery_terms' => 'nullable|string|max:500',
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
            'customer_id.required' => 'El cliente es obligatorio',
            'customer_id.exists' => 'El cliente seleccionado no existe',
            'warehouse_id.required' => 'El almacén es obligatorio',
            'warehouse_id.exists' => 'El almacén seleccionado no existe',
            'currency.in' => 'La moneda debe ser PEN o USD',
            'exchange_rate.numeric' => 'El tipo de cambio debe ser un número',
            'exchange_rate.min' => 'El tipo de cambio debe ser mayor a 0',
            'valid_days.integer' => 'Los días de validez deben ser un número entero',
            'valid_days.min' => 'Los días de validez deben ser al menos 1',
            'valid_days.max' => 'Los días de validez no pueden exceder 90',
            'customer_name.required' => 'El nombre del cliente es obligatorio',
            'customer_name.max' => 'El nombre del cliente no puede exceder 200 caracteres',
            'customer_document.required' => 'El documento del cliente es obligatorio',
            'customer_document.max' => 'El documento del cliente no puede exceder 20 caracteres',
            'customer_email.email' => 'El email del cliente debe ser válido',
            'customer_email.max' => 'El email del cliente no puede exceder 100 caracteres',
            'customer_phone.max' => 'El teléfono del cliente no puede exceder 20 caracteres',
            'shipping_cost.numeric' => 'El costo de envío debe ser un número',
            'shipping_cost.min' => 'El costo de envío debe ser mayor o igual a 0',
            'packaging_cost.numeric' => 'El costo de embalaje debe ser un número',
            'packaging_cost.min' => 'El costo de embalaje debe ser mayor o igual a 0',
            'assembly_cost.numeric' => 'El costo de armado debe ser un número',
            'assembly_cost.min' => 'El costo de armado debe ser mayor o igual a 0',
            'observations.max' => 'Las observaciones no pueden exceder 1000 caracteres',
            'internal_notes.max' => 'Las notas internas no pueden exceder 1000 caracteres',
            'terms_conditions.max' => 'Los términos y condiciones no pueden exceder 2000 caracteres',
            'payment_terms.max' => 'Las condiciones de pago no pueden exceder 500 caracteres',
            'delivery_terms.max' => 'Las condiciones de entrega no pueden exceder 500 caracteres',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Si viene valid_days, calcular valid_until
        if ($this->has('valid_days') && $this->valid_days) {
            $this->merge([
                'valid_until' => now()->addDays($this->valid_days)->toDateString(),
            ]);
        }

        // Limpiar valores vacíos
        $data = $this->all();
        foreach (['shipping_cost', 'packaging_cost', 'assembly_cost'] as $field) {
            if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = 0;
            }
        }
        
        // Limpiar strings vacíos a null
        foreach (['observations', 'internal_notes', 'terms_conditions', 'payment_terms', 'delivery_terms'] as $field) {
            if (isset($data[$field]) && trim($data[$field]) === '') {
                $data[$field] = null;
            }
        }
        
        $this->replace($data);
    }

    /**
     * Get validated data with only non-null values.
     */
    public function validatedOnly(): array
    {
        $validated = $this->validated();
        
        return array_filter($validated, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Si se actualiza el cliente, actualizar también el snapshot
        if ($this->has('customer_id') && !$this->has('customer_name')) {
            $customer = \App\Models\Entity::find($this->customer_id);
            
            if ($customer) {
                $this->merge([
                    'customer_name' => $customer->business_name ?? $customer->full_name,
                    'customer_document' => $customer->numero_documento,
                    'customer_email' => $customer->email,
                    'customer_phone' => $customer->phone,
                ]);
            }
        }
    }
}