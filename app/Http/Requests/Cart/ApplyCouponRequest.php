<?php
// app/Http/Requests/Cart/ApplyCouponRequest.php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class ApplyCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check(); // Solo usuarios logueados pueden usar cupones (R2.1)
    }

    public function rules(): array
    {
        return [
            'coupon_code' => 'required|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'coupon_code.required' => 'El código del cupón es obligatorio.',
        ];
    }
}