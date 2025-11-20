<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email') && $this->email) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }

        if ($this->has('first_name') && $this->first_name) {
            $this->merge(['first_name' => trim($this->first_name)]);
        }

        if ($this->has('last_name') && $this->last_name) {
            $this->merge(['last_name' => trim($this->last_name)]);
        }

        if ($this->has('cellphone') && $this->cellphone) {
            $this->merge(['cellphone' => trim($this->cellphone)]);
        }
    }

    /**
     * Get the validation rules.
     */
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            // Datos personales (opcionales en actualización)
            'first_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'last_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'cellphone' => [
                'nullable',
                'string',
                'max:20',
            ],

            // Email (validar unicidad excluyendo el usuario actual)
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($userId)
                    ->whereNull('deleted_at'),
            ],

            // Password (opcional en actualización)
            'password' => [
                'nullable',
                'string',
                'min:8',
                'max:255',
            ],

            // Estado
            'is_active' => [
                'boolean',
            ],

            // Rol
            'role' => [
                'sometimes',
                'string',
                Rule::in(['super-admin', 'admin', 'vendor', 'customer']),
            ],

            // Almacén
            'warehouse_id' => [
                'nullable',
                'integer',
                'exists:warehouses,id',
            ],
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es obligatorio',
            'last_name.required' => 'El apellido es obligatorio',
            'email.email' => 'El email no tiene un formato válido',
            'email.unique' => 'Este email ya está en uso por otro usuario',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'role.in' => 'El rol seleccionado no es válido',
            'warehouse_id.exists' => 'El almacén seleccionado no existe',
        ];
    }

    /**
     * Custom attribute names
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'cellphone' => 'teléfono',
            'role' => 'rol',
            'is_active' => 'estado activo',
            'warehouse_id' => 'almacén',
        ];
    }
}
