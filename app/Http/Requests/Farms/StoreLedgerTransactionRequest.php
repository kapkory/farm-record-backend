<?php

namespace App\Http\Requests\Farms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLedgerTransactionRequest extends FormRequest
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
            'payment_method' => ['required', 'in:cash,mobile_money,bank,credit'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'transaction_for' => ['required', 'in:planting,animal,animal_group,hive,farm'],
            // Which part of the farm a whole-farm expense covers. Only meaningful
            // when transaction_for is 'farm'; ignored otherwise.
            'scope' => ['nullable', 'in:general,livestock,crops'],
            'type' => ['required', 'in:expense,income,asset,liability,equity,revenue'],
            // Which way the recorded account moves. Defaults to 'increase';
            // 'decrease' is how equipment is sold, a loan repaid, or the owner
            // takes money out of the farm.
            'effect' => ['nullable', 'in:increase,decrease'],
            'transaction_uuid' => ['required', 'uuid'],
            'entries' => ['required', 'array', 'size:1'],
            'entries.*.ledger_account_id' => ['required', 'integer', 'exists:ledger_accounts,id'],
            'entries.*.amount' => ['required', 'numeric', 'min:0.01'],
            'entries.*.quantity' => ['nullable', 'integer', 'min:0'],
            'entries.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('effect') !== 'decrease') {
                    return;
                }

                if (in_array($this->input('type'), ['expense', 'income', 'revenue'], true)) {
                    $validator->errors()->add(
                        'effect',
                        'Money in and money out are corrected by reversing the entry, not by recording a backwards one.'
                    );
                }
            },
        ];
    }
}
