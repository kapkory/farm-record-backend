<?php

namespace App\Http\Requests\Bees;

use App\Models\Core\Hive;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            // Optional: when omitted the farm is derived from the apiary.
            'farm_uuid' => ['nullable', 'uuid'],
            'apiary_uuid' => ['required', 'uuid'],
            'name' => ['nullable', 'string', 'max:255'],
            'hive_type' => ['nullable', Rule::in(Hive::HIVE_TYPES)],
            'occupancy' => ['nullable', Rule::in(Hive::OCCUPANCIES)],
            'installed_date' => ['nullable', 'date'],
            'harvest_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
