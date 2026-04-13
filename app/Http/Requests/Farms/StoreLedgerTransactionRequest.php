<?php

namespace App\Http\Requests\Farms;

use Illuminate\Foundation\Http\FormRequest;

class StoreLedgerTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,mobile_money,bank,credit'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'transaction_for' => ['required', 'in:planting,animal,animal_group'],
            'type' => ['required', 'in:expense,income,asset,liability,equity,revenue'],
            'transaction_uuid' => ['required', 'uuid'],
            'entries' => ['required', 'array', 'size:1'],
            'entries.*.ledger_account_id' => ['required', 'integer', 'exists:ledger_accounts,id'],
            'entries.*.amount' => ['required', 'numeric', 'min:0.01'],
            'entries.*.quantity' => ['nullable', 'integer', 'min:0'],
            'entries.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

