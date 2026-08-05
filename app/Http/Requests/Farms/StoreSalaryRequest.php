<?php

namespace App\Http\Requests\Farms;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'farm_uuid' => ['required', 'uuid', 'exists:farms,uuid'],
            // Either pick a saved worker or type a name; both are optional.
            'farm_personnel_uuid' => ['nullable', 'uuid', 'exists:farm_personnels,uuid'],
            'worker_name' => ['nullable', 'string', 'max:255'],
            // Free-form period label, e.g. "2026-08" or "August 2026".
            'period' => ['nullable', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'payment_method' => ['required', 'in:cash,mobile_money,bank,credit'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
