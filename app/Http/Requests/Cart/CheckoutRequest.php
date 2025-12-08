<?php
// app/Http/Requests/Cart/CheckoutRequest.php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo clientes autenticados y con rol 'customer'
        return Auth::check(); //&& Auth::user()->hasRole('customer');
    }

    public function rules(): array
    {
        return [
            // R3.1: Datos de Facturación
            'customer_data' => 'required|array',
            'customer_data.tipo_documento' => 'required|in:01,06',
            'customer_data.numero_documento' => ['required', 'numeric'],
            'customer_data.business_name' => 'nullable|required_if:customer_data.tipo_documento,06|string|max:200',
            'customer_data.first_name' => 'required_if:customer_data.tipo_documento,01|string|max:100',
            'customer_data.last_name' => 'required_if:customer_data.tipo_documento,01|string|max:100',
            'customer_data.email' => 'required|email|max:100',
            'customer_data.phone' => 'nullable|string|max:20',

            // R3.2: Dirección de Envío
            'address' => 'required|array',
            'address.address' => ['required', 'string', 'max:250'],
            'address.country_code' => ['nullable', 'string', 'size:2', 'exists:countries,code'],
            'address.ubigeo' => [
                'nullable',
                'required_if:address.country_code,PE', // Asumo PE por defecto
                'string',
                'size:6',
                'exists:ubigeos,ubigeo'
            ],
            'address.reference' => ['nullable', 'string', 'max:200'],
            'address.phone' => ['nullable', 'string', 'max:20'],
            'address.label' => ['nullable', 'string', 'max:50'],

            // Datos de la Orden
            'currency' => 'nullable|in:PEN,USD',
            'observations' => 'nullable|string|max:1000',
        ];
    }

    protected function prepareForValidation(): void
    {
        // R3.1: Inferir tipo de persona y agregar país por defecto
        if ($this->has('address')) {
            $address = $this->input('address');
            if (!isset($address['country_code'])) {
                $address['country_code'] = 'PE';
                $this->merge(['address' => $address]);
            }
        }
        if ($this->has('customer_data.tipo_documento')) {
            $tipoDoc = $this->input('customer_data.tipo_documento');
            $tipoPersona = $tipoDoc === '01' ? 'natural' : 'juridica';
            $this->merge(['customer_data.tipo_persona' => $tipoPersona]);
        }
    }
}