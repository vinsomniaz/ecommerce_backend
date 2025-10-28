<?php

namespace App\Http\Requests\Address;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Obtener el address desde el route binding
        $address = $this->route('address');

        // Determinar el country_code: el nuevo si se envía, sino el actual
        $countryCode = $this->input('country_code', $address?->country_code ?? 'PE');

        // Solo requerir ubigeo si:
        // 1. Se está cambiando el country_code a PE, O
        // 2. El país actual es PE Y se está actualizando el address (dirección física)
        $requiresUbigeo = false;

        if ($this->has('country_code')) {
            // Si se está enviando country_code en la petición
            $requiresUbigeo = $countryCode === 'PE';
        } elseif ($this->has('address') && $address?->country_code === 'PE') {
            // Si se actualiza la dirección física y el país actual es PE
            $requiresUbigeo = true;
        }

        return [
            'address' => ['sometimes', 'string', 'max:250'],
            'country_code' => ['sometimes', 'string', 'size:2', 'exists:countries,code'],
            'ubigeo' => [
                'nullable',
                $requiresUbigeo ? 'required' : 'nullable',
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

    public function messages(): array
    {
        return [
            'address.required' => 'La dirección es obligatoria.',
            'address.max' => 'La dirección no puede exceder 250 caracteres.',
            'country_code.exists' => 'El país no es válido.',
            'country_code.size' => 'El código de país debe tener 2 caracteres.',
            'ubigeo.required' => 'El ubigeo es obligatorio para direcciones en Perú.',
            'ubigeo.size' => 'El ubigeo debe tener 6 caracteres.',
            'ubigeo.exists' => 'El ubigeo no existe en nuestra base de datos.',
            'reference.max' => 'La referencia no puede exceder 200 caracteres.',
            'phone.max' => 'El teléfono no puede exceder 20 caracteres.',
            'label.max' => 'La etiqueta no puede exceder 50 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Obtener el address desde el route binding
        $address = $this->route('address');

        if (!$address) {
            return; // Si no hay address, salir
        }

        // Si se está cambiando el país a uno diferente de PE, anular ubigeo
        if ($this->has('country_code') && $this->input('country_code') !== 'PE') {
            $this->merge(['ubigeo' => null]);
        }
    }
}
