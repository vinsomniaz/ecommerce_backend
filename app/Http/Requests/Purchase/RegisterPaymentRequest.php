<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class RegisterPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string', // cash, bank_transfer, etc.
            'reference' => 'nullable|string',
            'paid_at' => 'required|date',
            'notes' => 'nullable|string',
        ];
    }
}
