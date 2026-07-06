<?php

namespace App\Http\Requests\Farms;

use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAnimalEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'eventable_type' => ['required', 'in:animal_group,animal'],
            'eventable_uuid' => ['required', 'uuid'],
            'event_type' => ['required', 'in:birth,death,sale,purchase,weight_check,movement,other'],
            'date' => ['required', 'date'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $eventable = match ($this->input('eventable_type')) {
                'animal_group' => AnimalGroup::where('uuid', $this->input('eventable_uuid'))->first(),
                'animal' => Animal::where('uuid', $this->input('eventable_uuid'))->first(),
                default => null,
            };

            if (! $eventable) {
                $validator->errors()->add('eventable_uuid', 'The selected animal target could not be found.');

                return;
            }

            $allowedFarmIds = Farm::query()
                ->whereIn('farmer_id', $this->user()->farmers()->pluck('farmers.id'))
                ->pluck('id');

            if (! $allowedFarmIds->contains($eventable->farm_id)) {
                $validator->errors()->add('eventable_uuid', 'You do not have access to the selected animal record.');
            }
        });
    }
}
