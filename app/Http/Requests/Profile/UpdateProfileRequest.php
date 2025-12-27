<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = auth()->id();

        return [
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'cellphone'  => 'sometimes|nullable|string|max:20',
            'email'      => "sometimes|email|max:255|unique:users,email,{$userId}",
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.max' => 'El nombre no puede exceder 100 caracteres',
            'last_name.max' => 'El apellido no puede exceder 100 caracteres',
            'cellphone.max' => 'El teléfono no puede exceder 20 caracteres',
            'email.email' => 'El email debe tener un formato válido',
            'email.unique' => 'Este email ya está en uso por otro usuario',
        ];
    }
}
