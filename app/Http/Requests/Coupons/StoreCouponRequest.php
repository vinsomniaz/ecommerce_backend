<?php

namespace App\Http\Requests\Coupons;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:coupons,code',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'type' => 'required|string|in:percentage,amount',
            'value' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) {
                    if ($this->input('type') === 'percentage' && $value > 100) {
                        $fail('El porcentaje no puede ser mayor a 100%');
                    }
                }
            ],
            'max_discount' => [
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'min_amount' => 'required|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_per_user' => 'nullable|integer|min:1',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'applies_to' => 'nullable|string|in:all,categories,products',
            'active' => 'nullable|boolean',

            // Categorías o productos según applies_to
            'category_ids' => [
                'nullable',
                'array',
                'required_if:applies_to,categories',
            ],
            'category_ids.*' => 'exists:categories,id',

            'product_ids' => [
                'nullable',
                'array',
                'required_if:applies_to,products',
            ],
            'product_ids.*' => 'exists:products,id',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código del cupón es obligatorio',
            'code.unique' => 'Este código de cupón ya existe',
            'code.regex' => 'El código solo puede contener letras, números, guiones y guiones bajos',
            'name.required' => 'El nombre del cupón es obligatorio',
            'type.required' => 'El tipo de cupón es obligatorio',
            'type.in' => 'El tipo debe ser porcentaje o monto fijo',
            'value.required' => 'El valor del descuento es obligatorio',
            'value.min' => 'El valor debe ser mayor a 0',
            'max_discount.required_if' => 'El descuento máximo es requerido para cupones porcentuales',
            'min_amount.required' => 'El monto mínimo es obligatorio',
            'start_date.required' => 'La fecha de inicio es obligatoria',
            'start_date.after_or_equal' => 'La fecha de inicio debe ser hoy o posterior',
            'end_date.required' => 'La fecha de fin es obligatoria',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'category_ids.required_if' => 'Debe seleccionar al menos una categoría',
            'product_ids.required_if' => 'Debe seleccionar al menos un producto',
        ];
    }

    protected function prepareForValidation()
    {
        // Convertir código a mayúsculas
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper($this->input('code')),
            ]);
        }

        // Establecer valores por defecto
        $this->merge([
            'active' => $this->boolean('active', true),
            'applies_to' => $this->input('applies_to', 'all'),
        ]);
    }
}
