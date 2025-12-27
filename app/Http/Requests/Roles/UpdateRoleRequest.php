<?php

namespace App\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * System roles that cannot have their name changed
     */
    private const SYSTEM_ROLES = ['super-admin', 'admin', 'vendor', 'customer'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super-admin');
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => strtolower(trim($this->name)),
            ]);
        }
    }

    /**
     * Get the validation rules.
     */
    public function rules(): array
    {
        $roleId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:100',
                'regex:/^[a-z0-9\-_]+$/',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'sanctum')
                    ->ignore($roleId),
            ],
            'color_hex' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'permissions' => [
                'sometimes',
                'array',
            ],
            'permissions.*' => [
                'string',
                'exists:permissions,name',
            ],
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un rol con este nombre',
            'name.regex' => 'El nombre solo puede contener letras minúsculas, números, guiones y guiones bajos',
            'permissions.array' => 'Los permisos deben ser una lista',
            'permissions.*.exists' => 'Uno o más permisos no son válidos',
        ];
    }

    /**
     * Custom attribute names
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'permissions' => 'permisos',
        ];
    }
}
