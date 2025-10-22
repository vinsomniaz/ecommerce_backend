<?php

namespace App\Http\Requests\Categories;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // o auth()->check() si necesitas validar
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->where('parent_id', $this->input('parent_id'))
            ],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', // Solo minúsculas y guiones
                'unique:categories,slug'
            ],
            'description' => 'nullable|string|max:500',
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $parent = Category::find($value);
                        if ($parent && $parent->level >= 3) {
                            $fail('No se puede crear una categoría de nivel 4 o superior.');
                        }
                    }
                }
            ],
            'level' => [
                'nullable',
                'integer',
                'min:1',
                'max:3',
                function ($attribute, $value, $fail) {
                    // Si no hay parent_id, level debe ser 1
                    if (!$this->input('parent_id') && $value != 1) {
                        $fail('Una categoría sin padre debe ser de nivel 1.');
                    }

                    // Si hay parent_id, validar coherencia
                    if ($this->input('parent_id')) {
                        $parent = Category::find($this->input('parent_id'));
                        if ($parent && $value != $parent->level + 1) {
                            $fail("El nivel debe ser " . ($parent->level + 1) . " según la categoría padre.");
                        }
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
            'name.unique' => 'Ya existe una categoría con ese nombre en el mismo nivel',
            'slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones',
            'slug.unique' => 'El slug ya está en uso',
            'parent_id.exists' => 'La categoría padre no existe',
            'level.min' => 'El nivel mínimo es 1',
            'level.max' => 'El nivel máximo es 3',
        ];
    }
}
