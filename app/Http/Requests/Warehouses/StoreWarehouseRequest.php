<?php

namespace App\Http\Requests\Warehouses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('warehouses', 'name')->whereNull('deleted_at'),
            ],
            'ubigeo' => [
                'required', // o 'sometimes' en Update
                'string',
                'size:6',
                Rule::exists('ubigeos', 'ubigeo'), // Cambiar 'code' por 'ubigeo'
            ],
            'address' => 'required|string|max:500',
            'phone' => 'nullable|string|max:20',
            'is_main' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'visible_online' => 'nullable|boolean',
            'picking_priority' => 'nullable|integer|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del almacén es obligatorio',
            'name.unique' => 'Ya existe un almacén con este nombre',
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'ubigeo.required' => 'El ubigeo es obligatorio',
            'ubigeo.size' => 'El ubigeo debe tener 6 dígitos',
            'ubigeo.exists' => 'El ubigeo ingresado no es válido',
            'address.required' => 'La dirección es obligatoria',
            'address.max' => 'La dirección no puede exceder 500 caracteres',
            'phone.max' => 'El teléfono no puede exceder 20 caracteres',
            'is_main.boolean' => 'El campo is_main debe ser verdadero o falso',
            'is_active.boolean' => 'El campo is_active debe ser verdadero o falso',
            'visible_online.boolean' => 'El campo visible_online debe ser verdadero o falso',
            'picking_priority.integer' => 'La prioridad debe ser un número entero',
            'picking_priority.min' => 'La prioridad mínima es 0',
            'picking_priority.max' => 'La prioridad máxima es 100',
        ];
    }
}
