<?php

namespace App\Http\Requests\Farms;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'productionable_type' => ['required', 'in:planting'],
            'productionable_uuid' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'trace_number' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'record_expense' => ['nullable', 'boolean'],
            'expense_amount' => ['nullable', 'numeric', 'min:0.01', 'required_if:record_expense,1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('record_expense')) {
            $this->merge([
                'record_expense' => filter_var($this->input('record_expense'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
    }
}
