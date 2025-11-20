<?php

namespace App\Http\Requests\Permissions;

use Illuminate\Foundation\Http\FormRequest;

class AssignPermissionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super-admin');
    }

    /**
     * Get the validation rules.
     */
    public function rules(): array
    {
        return [
            'permissions' => [
                'required',
                'array',
                'min:1',
            ],
            'permissions.*' => [
                'required',
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
            'permissions.required' => 'Debes proporcionar al menos un permiso',
            'permissions.array' => 'Los permisos deben ser un array',
            'permissions.min' => 'Debes seleccionar al menos un permiso',
            'permissions.*.exists' => 'Uno o más permisos no son válidos',
        ];
    }
}
