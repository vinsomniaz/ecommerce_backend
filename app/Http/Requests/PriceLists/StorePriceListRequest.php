<?php
// app/Http/Requests/PriceLists/StorePriceListRequest.php

namespace App\Http\Requests\PriceLists;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9_-]+$/',
                'unique:price_lists,code',
            ],
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código es obligatorio',
            'code.unique' => 'Este código ya está registrado',
            'code.regex' => 'El código solo puede contener letras mayúsculas, números, guiones y guiones bajos',
            'code.max' => 'El código no puede exceder 20 caracteres',

            'name.required' => 'El nombre es obligatorio',
            'name.max' => 'El nombre no puede exceder 100 caracteres',

            'description.max' => 'La descripción no puede exceder 500 caracteres',
        ];
    }

    protected function prepareForValidation()
    {
        // Convertir código a mayúsculas automáticamente
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper($this->code),
            ]);
        }

        // Establecer valor por defecto para is_active
        if (!$this->has('is_active')) {
            $this->merge([
                'is_active' => true,
            ]);
        }
    }
}

