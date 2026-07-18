<?php

namespace App\Http\Requests\Bees;

use App\Services\Bees\BeeProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHarvestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Session uuid doubles as the idempotency key (offline replay).
            'uuid' => ['required', 'uuid'],
            'date' => ['required', 'date'],
            'grade' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product' => ['required', Rule::in(BeeProduct::keys())],
            'products.*.unit' => ['nullable', 'string', 'max:100'],
            'products.*.hives' => ['nullable', 'array', 'min:1'],
            'products.*.hives.*.hive_uuid' => ['required', 'uuid'],
            'products.*.hives.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'products.*.total_quantity' => [
                'nullable', 'numeric', 'min:0.01', 'required_without:products.*.hives',
            ],
            'products.*.split' => ['nullable', Rule::in(['even'])],
            'products.*.hive_uuids' => ['required_with:products.*.total_quantity', 'array', 'min:1'],
            'products.*.hive_uuids.*' => ['uuid'],
        ];
    }
}
