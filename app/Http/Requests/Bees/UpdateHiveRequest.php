<?php

namespace App\Http\Requests\Bees;

use App\Models\Core\Hive;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // code and sequence are deliberately absent: codes are painted on
        // physical boxes and immutable after allocation.
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'hive_type' => ['nullable', Rule::in(Hive::HIVE_TYPES)],
            'occupancy' => ['sometimes', Rule::in(Hive::OCCUPANCIES)],
            'installed_date' => ['nullable', 'date'],
            'last_inspected_at' => ['nullable', 'date'],
            'harvest_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
