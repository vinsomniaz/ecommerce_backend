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
                    // No permitir cambiar padre si tiene hijos
                    $hasChildren = Category::where('parent_id', $categoryId)->exists();
                    if ($hasChildren) {
                        $fail('No se puede cambiar el padre de una categoría que tiene subcategorías.');
                    }
                }
            ],
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
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
        ];
    }
}
