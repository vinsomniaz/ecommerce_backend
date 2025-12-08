<?php
// app/Http/Requests/Cart/AddItemRequest.php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'El ID del producto es obligatorio.',
            'product_id.exists' => 'El producto no existe.',
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.min' => 'La cantidad mínima es 1.',
        ];
    }
}