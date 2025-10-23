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
            'ubigeo' => ['required', 'string', 'size:6', 'exists:ubigeos,ubigeo'],
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
            'ubigeo.required' => 'El ubigeo es obligatorio.',
            'ubigeo.size' => 'El ubigeo debe tener 6 caracteres.',
            'ubigeo.exists' => 'El ubigeo no existe en nuestra base de datos.',
        ];
    }
}