<?php

namespace App\Http\Requests\Farms;

use App\Models\Core\AnimalBreeding;
use App\Models\Core\AnimalEvent;
use App\Models\Core\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegisterBirthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Doubles as the birth event's uuid and the idempotency key for a
            // replayed offline create.
            'uuid' => ['nullable', 'uuid'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'stillborn_count' => ['nullable', 'integer', 'min:0', 'max:30'],
            'inherit_treatment_plan' => ['sometimes', 'boolean'],
            'offspring' => ['nullable', 'array', 'max:30'],
            'offspring.*.uuid' => ['nullable', 'uuid'],
            'offspring.*.tag_number' => ['nullable', 'string', 'max:100'],
            'offspring.*.name' => ['nullable', 'string', 'max:255'],
            'offspring.*.gender' => ['required', 'in:male,female,unknown'],
            'offspring.*.animal_breed_id' => ['nullable', 'integer', 'exists:animal_breeds,id'],
            'offspring.*.notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'offspring.*.gender.required' => 'Choose the sex of each offspring.',
            'birth_date.before_or_equal' => 'The birth date cannot be in the future.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $liveCount = count($this->input('offspring') ?? []);
            $stillbornCount = (int) ($this->input('stillborn_count') ?? 0);

            if ($liveCount + $stillbornCount < 1) {
                $validator->errors()->add('offspring', 'Record at least one offspring, live or stillborn.');
            }

            $breeding = AnimalBreeding::where('uuid', (string) $this->route('uuid'))->first();

            // A breeding the user cannot reach is a 404 from the controller,
            // not a validation error — say nothing about it here.
            if (! $breeding || ! Farm::farmerOwned($this->user()->id)->where('id', $breeding->farm_id)->exists()) {
                return;
            }

            if ($breeding->status !== 'pending') {
                // A replayed offline create is not an error: the birth event
                // already exists under this uuid and the controller answers
                // from it. Only a genuinely new request is rejected.
                if ($this->filled('uuid') && AnimalEvent::where('uuid', $this->input('uuid'))->exists()) {
                    return;
                }

                $validator->errors()->add('status', 'This breeding record has already been resolved.');

                return;
            }

            if ($breeding->service_date && $this->filled('birth_date')
                && strtotime((string) $this->input('birth_date')) < $breeding->service_date->getTimestamp()) {
                $validator->errors()->add('birth_date', 'The birth date cannot be before the service date.');
            }
        });
    }
}
