<?php

namespace App\Http\Requests\Farms;

use App\Models\Core\AnimalBreed;
use App\Models\Core\AnimalType;
use App\Models\Core\Farm;
use App\Models\Core\Field;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAnimalGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'farm_uuid' => ['required', 'uuid', 'exists:farms,uuid'],
            'field_uuid' => ['nullable', 'uuid', 'exists:fields,uuid'],
            'animal_type_id' => ['required', 'integer', 'exists:animal_types,id'],
            'animal_breed_id' => ['nullable', 'integer', 'exists:animal_breeds,id'],
            'treatment_plan_uuid' => ['nullable', 'uuid', 'exists:treatment_plans,uuid'],
            'name' => ['required', 'string', 'max:255'],
            'initial_count' => ['required', 'integer', 'min:1'],
            'acquired_date' => ['required', 'date'],
            'acquisition_type' => ['nullable', 'in:born,purchased,donated,transferred'],
            'purpose' => ['nullable', 'in:commercial,subsistence,mixed'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'integer', 'in:0,1,2,3'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $farm = Farm::where('uuid', $this->input('farm_uuid'))->first();
            if ($farm && ! Farm::farmerOwned($this->user()->id)->where('id', $farm->id)->exists()) {
                $validator->errors()->add('farm_uuid', 'You do not have access to the selected farm.');
            }

            if ($this->filled('field_uuid')) {
                $field = Field::where('uuid', $this->input('field_uuid'))->first();
                if ($field && $farm && (int) $field->farm_id !== (int) $farm->id) {
                    $validator->errors()->add('field_uuid', 'The selected field does not belong to the selected farm.');
                }
            }

            $animalType = AnimalType::find($this->input('animal_type_id'));
            if ($animalType && ! $animalType->allowsGroups()) {
                $validator->errors()->add('animal_type_id', $animalType->name.' is set to individual-only tracking. Add animals directly instead of creating a group.');
            }

            if ($this->filled('animal_breed_id')) {
                $breed = AnimalBreed::find($this->input('animal_breed_id'));
                if ($breed && (int) $breed->animal_type_id !== (int) $this->input('animal_type_id')) {
                    $validator->errors()->add('animal_breed_id', 'The selected breed does not belong to the selected animal type.');
                }
            }
        });
    }
}
