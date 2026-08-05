<?php

namespace App\Http\Requests\Bees;

use App\Models\Core\Hive;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreHiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Idempotency key for the batch so a replayed submit (double click,
            // flaky network) returns the first result instead of duplicating.
            'batch_uuid' => ['nullable', 'uuid'],
            'farm_uuid' => ['nullable', 'uuid'],
            'apiary_uuid' => ['required', 'uuid'],
            'count' => ['required', 'integer', 'min:1', 'max:200'],
            'hive_type' => ['nullable', Rule::in(Hive::HIVE_TYPES)],
            'occupancy' => ['nullable', Rule::in(Hive::OCCUPANCIES)],
            'installed_date' => ['nullable', 'date'],
            'harvest_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string'],
            // Total cost for the whole batch, posted as a single expense.
            'cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
