<?php

namespace App\Http\Requests\Settings\Crops;

use Illuminate\Foundation\Http\FormRequest;

class SaveScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                      => 'required|string|max:255',
            'crop_id'                   => 'required|integer|exists:crops,id',
            'description'               => 'nullable|string|max:1000',
            'status'                    => 'nullable|string|in:active,inactive',
            'activities'                => 'required|array|min:1',
            'activities.*.id'           => 'nullable|integer',
            'activities.*.title'        => 'required|string|max:255',
            'activities.*.offset_value' => 'required|integer|min:1',
            'activities.*.offset_unit'  => 'required|string|in:days,weeks,months',
            'activities.*.priority'     => 'required|integer|in:1,2,3',
            'activities.*.description'  => 'nullable|string|max:1000',
            'activities.*.sort_order'   => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'activities.required'                => 'At least one activity is required.',
            'activities.min'                     => 'At least one activity is required.',
            'activities.*.title.required'        => 'Each activity must have a title.',
            'activities.*.offset_value.required' => 'Each activity must have an offset value.',
            'activities.*.offset_unit.required'  => 'Each activity must have an offset unit.',
            'activities.*.sort_order.required'   => 'Each activity must have a sort order.',
        ];
    }
}

