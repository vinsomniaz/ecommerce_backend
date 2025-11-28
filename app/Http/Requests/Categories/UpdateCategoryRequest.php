<?php

namespace App\Http\Requests\Categories;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->where('parent_id', $this->input('parent_id'))
                    ->ignore($categoryId)
            ],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($categoryId)
            ],
            'description' => 'nullable|string|max:500',
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($categoryId) {
                    $hasChildren = Category::where('parent_id', $categoryId)->exists();
                    if ($hasChildren) {
                        $fail('No se puede cambiar el padre de una categoría que tiene subcategorías.');
                    }
                }
            ],
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',

            // ✅ NUEVOS CAMPOS DE MARGEN
            'min_margin_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'normal_margin_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                function ($attribute, $value, $fail) {
                    // Validar que normal >= mínimo si ambos están presentes
                    $minMargin = $this->input('min_margin_percentage');
                    if ($minMargin !== null && $value < $minMargin) {
                        $fail('El margen normal debe ser mayor o igual al margen mínimo');
                    }
                }
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio',
            'name.max' => 'El nombre no puede superar los 100 caracteres',
            'name.unique' => 'Ya existe una categoría con ese nombre',
            'slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones',
            'slug.unique' => 'El slug ya está en uso',
            'parent_id.exists' => 'La categoría padre no existe',

            // ✅ NUEVOS MENSAJES
            'min_margin_percentage.min' => 'El margen mínimo debe ser mayor o igual a 0',
            'min_margin_percentage.max' => 'El margen mínimo no puede superar 100%',
            'normal_margin_percentage.min' => 'El margen normal debe ser mayor o igual a 0',
            'normal_margin_percentage.max' => 'El margen normal no puede superar 100%',
        ];
    }
}
