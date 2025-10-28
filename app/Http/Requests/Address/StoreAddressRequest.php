<?php
namespace App\Http\Requests\Address;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address' => ['required', 'string', 'max:250'],
            'country_code' => ['nullable', 'string', 'size:2', 'exists:countries,code'],
            'ubigeo' => [
                'nullable', 
                'required_if:country_code,PE', // Solo requerido si es Perú
                'string', 
                'size:6', 
                'exists:ubigeos,ubigeo'
            ],
            'reference' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:20'],
            'label' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('country_code')) {
            $this->merge(['country_code' => 'PE']);
        }
         // Ensure ubigeo is explicitly null if country is not PE during preparation
         if ($this->input('country_code') !== 'PE') {
             $this->merge(['ubigeo' => null]);
         }
    }

    public function messages(): array
    {
        return [
            'address.required' => 'La dirección es obligatoria.',
            'country_code.exists' => 'El país no es válido.',
            'ubigeo.required_if' => 'El ubigeo es obligatorio para direcciones en Perú.',
            'ubigeo.size' => 'El ubigeo debe tener 6 caracteres.',
            'ubigeo.exists' => 'El ubigeo no existe en nuestra base de datos.',
        ];
    }
}