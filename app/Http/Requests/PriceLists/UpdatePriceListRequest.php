<?php
// app/Http/Requests/PriceLists/UpdatePriceListRequest.php

namespace App\Http\Requests\PriceLists;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $priceListId = $this->route('price_list') ?? $this->route('id');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('price_lists', 'code')->ignore($priceListId),
            ],
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
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
    }
}
