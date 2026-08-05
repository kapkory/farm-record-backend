<?php

namespace App\Http\Requests\Farms;

use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\AnimalWeight;
use App\Models\Core\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAnimalWeightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'weighable_type' => ['required', 'in:animal,animal_group'],
            'weighable_uuid' => ['required', 'uuid'],
            'measured_on' => ['required', 'date', 'before_or_equal:today'],
            // For a group this is the TOTAL of the sample; for an individual
            // it is simply the animal's weight.
            'entered_value' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'entered_unit' => ['required', 'in:'.implode(',', AnimalWeight::UNITS)],
            'sample_size' => ['required', 'integer', 'min:1', 'max:1000'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'measured_on.before_or_equal' => 'A weighing cannot be dated in the future.',
            'entered_value.gt' => 'Enter a weight greater than zero.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $subject = match ($this->input('weighable_type')) {
                'animal_group' => AnimalGroup::where('uuid', $this->input('weighable_uuid'))->first(),
                'animal' => Animal::where('uuid', $this->input('weighable_uuid'))->first(),
                default => null,
            };

            if (! $subject) {
                $validator->errors()->add('weighable_uuid', 'The selected animal record could not be found.');

                return;
            }

            if (! Farm::farmerOwned($this->user()->id)->where('id', $subject->farm_id)->exists()) {
                $validator->errors()->add('weighable_uuid', 'You do not have access to the selected animal record.');

                return;
            }

            // Sampling only makes sense for a group; one animal is one animal.
            if ($this->input('weighable_type') === 'animal' && (int) $this->input('sample_size') > 1) {
                $validator->errors()->add('sample_size', 'An individual animal is weighed one at a time.');
            }

            if ($subject instanceof Animal && $subject->date_of_birth && $this->filled('measured_on')
                && strtotime((string) $this->input('measured_on')) < $subject->date_of_birth->getTimestamp()) {
                $validator->errors()->add('measured_on', 'A weighing cannot predate the animal’s date of birth.');
            }
        });
    }
}
