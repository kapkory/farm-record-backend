<?php

namespace App\Http\Requests\Farms;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,mobile_money,bank'],
        ];
    }
}
