<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
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
        return [
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'La imagen de avatar es requerida',
            'avatar.image' => 'El archivo debe ser una imagen',
            'avatar.mimes' => 'La imagen debe ser de tipo: jpg, jpeg, png o webp',
            'avatar.max' => 'La imagen no puede superar 2MB',
        ];
    }
}
