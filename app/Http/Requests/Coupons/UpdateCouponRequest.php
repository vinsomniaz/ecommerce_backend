<?php

namespace App\Http\Requests\Coupons;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $couponId = $this->route('id');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('coupons', 'code')->ignore($couponId),
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:500',
            'type' => 'sometimes|required|string|in:percentage,amount',
            'value' => [
                'sometimes',
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) {
                    $type = $this->input('type');
                    if ($type === 'percentage' && $value > 100) {
                        $fail('El porcentaje no puede ser mayor a 100%');
                    }
                }
            ],
            'max_discount' => [
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'min_amount' => 'sometimes|required|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_per_user' => 'nullable|integer|min:1',
            'start_date' => 'sometimes|required|date',
            'end_date' => [
                'sometimes',
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    $startDate = $this->input('start_date');
                    if ($startDate && $value <= $startDate) {
                        $fail('La fecha de fin debe ser posterior a la fecha de inicio');
                    }
                }
            ],
            'applies_to' => 'sometimes|string|in:all,categories,products',
            'active' => 'nullable|boolean',

            // Categorías o productos
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Este código de cupón ya existe',
            'code.regex' => 'El código solo puede contener letras, números, guiones y guiones bajos',
            'type.in' => 'El tipo debe ser porcentaje o monto fijo',
            'value.min' => 'El valor debe ser mayor a 0',
        ];
    }

    protected function prepareForValidation()
    {
        // Convertir código a mayúsculas si se actualiza
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper($this->input('code')),
            ]);
        }
    }
}
