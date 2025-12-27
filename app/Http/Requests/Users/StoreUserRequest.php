<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // return auth()->check() && auth()->user()->hasAnyRole(['admin', 'super-admin']);
        return true;
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email'      => $this->email ? strtolower(trim($this->email)) : null,
            'first_name' => $this->first_name ? trim($this->first_name) : null,
            'last_name'  => $this->last_name ? trim($this->last_name) : null,
            'cellphone'  => $this->cellphone ? trim($this->cellphone) : null,
        ]);
    }

    /**
     * Get the validation rules.
     */
    public function rules(): array
    {
        return [
            // Datos personales
            'first_name' => [
                'required',
                'string',
                'max:255',
            ],
            'last_name' => [
                'required',
                'string',
                'max:255',
            ],
            'cellphone' => [
                'nullable',
                'string',
                'max:20',
            ],

            // Email y acceso
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => [
                'required',
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
                'required',
                'string'
            ],

            // Almacén (opcional, para vendedores)
            'warehouse_id' => [
                'nullable',
                'integer',
                'exists:warehouses,id',
            ],

            // Comisión (para vendedores)
            'commission_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
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
            'password.required' => 'La contraseña es obligatoria',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'role.required' => 'El rol es obligatorio',
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
