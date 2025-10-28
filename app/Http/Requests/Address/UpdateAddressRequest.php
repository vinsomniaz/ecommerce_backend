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
        // Determinar el country_code actual o el nuevo
        $countryCode = $this->input('country_code', $this->address->country_code);

        return [
            'address' => ['sometimes', 'required', 'string', 'max:250'],
            'country_code' => ['sometimes', 'string', 'size:2', 'exists:countries,code'],
            'ubigeo' => [
                'nullable', 
                'required_if:country_code,PE', // Solo requerido si el país (nuevo o existente) es PE
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
            'country_code.exists' => 'El país no es válido.',
            'ubigeo.required_if' => 'El ubigeo es obligatorio para direcciones en Perú.',
            'ubigeo.size' => 'El ubigeo debe tener 6 caracteres.',
            'ubigeo.exists' => 'El ubigeo no existe en nuestra base de datos.',
        ];
    }
}