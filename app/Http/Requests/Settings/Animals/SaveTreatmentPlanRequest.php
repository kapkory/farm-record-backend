<?php

namespace App\Http\Requests\Settings\Animals;

use Illuminate\Foundation\Http\FormRequest;

class SaveTreatmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'animal_type_id' => 'nullable|integer|exists:animal_types,id',
            'status' => 'nullable|string|in:active,inactive',
            'activities' => 'required|array|min:1',
            'activities.*.id' => 'nullable|integer',
            'activities.*.vaccine' => 'required|string|max:255',
            'activities.*.disease' => 'nullable|string|max:255',
            'activities.*.route' => 'nullable|string|max:255',
            // Day-old doses are common (Marek's at day 0/1), so min:0.
            'activities.*.offset_value' => 'required|integer|min:0',
            'activities.*.offset_unit' => 'required|string|in:days,weeks,months',
            'activities.*.priority' => 'required|integer|in:1,2,3,4',
            'activities.*.notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'activities.required' => 'At least one step is required.',
            'activities.min' => 'At least one step is required.',
            'activities.*.vaccine.required' => 'Each step must have a vaccine/treatment name.',
            'activities.*.offset_value.required' => 'Each step must have an age.',
            'activities.*.offset_unit.required' => 'Each step must have an age unit.',
        ];
    }
}
