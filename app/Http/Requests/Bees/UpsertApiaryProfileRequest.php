<?php

namespace App\Http\Requests\Bees;

use App\Models\Core\ApiaryProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertApiaryProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'naming_prefix' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'naming_scheme' => ['sometimes', Rule::in(ApiaryProfile::SCHEMES)],
            // Optional start letter (alpha scheme) — only honored before any hive exists.
            'start_letter' => ['nullable', 'string', 'size:1', 'regex:/^[A-Za-z]$/'],
            'default_harvest_interval_days' => ['sometimes', 'integer', 'min:7', 'max:365'],
        ];
    }
}
