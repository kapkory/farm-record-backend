<?php

namespace App\Http\Requests\Farms;

use App\Models\Core\Animal;
use App\Models\Core\AnimalBreed;
use App\Models\Core\AnimalGroup;
use App\Models\Core\AnimalType;
use App\Models\Core\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAnimalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'animal_group_uuid' => ['nullable', 'uuid', 'exists:animal_groups,uuid'],
            'farm_uuid' => ['nullable', 'uuid', 'exists:farms,uuid'],
            'animal_type_id' => ['nullable', 'integer', 'exists:animal_types,id'],
            'animal_breed_id' => ['nullable', 'integer', 'exists:animal_breeds,id'],
            'dam_uuid' => ['nullable', 'uuid', 'exists:animals,uuid'],
            'sire_uuid' => ['nullable', 'uuid', 'exists:animals,uuid'],
            'treatment_plan_uuid' => ['nullable', 'uuid', 'exists:treatment_plans,uuid'],
            'tag_number' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female,unknown'],
            'date_of_birth' => ['nullable', 'date'],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_type' => ['nullable', 'in:born,purchased,donated,transferred'],
            'status' => ['nullable', 'in:active,sold,deceased,transferred'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('animal_group_uuid') && ! $this->filled('farm_uuid')) {
                $validator->errors()->add('farm_uuid', 'Either farm_uuid or animal_group_uuid is required.');
            }

            // Must provide at least tag_number or name

            if (! $this->filled('animal_group_uuid') && ! $this->filled('animal_type_id')) {
                $validator->errors()->add('animal_type_id', 'animal_type_id is required for standalone animals.');
            }

            $group = $this->filled('animal_group_uuid')
                ? AnimalGroup::with('animalType')->where('uuid', $this->input('animal_group_uuid'))->first()
                : null;

            $farm = $this->filled('farm_uuid')
                ? Farm::where('uuid', $this->input('farm_uuid'))->first()
                : null;

            if ($farm && ! Farm::farmerOwned($this->user()->id)->where('id', $farm->id)->exists()) {
                $validator->errors()->add('farm_uuid', 'You do not have access to the selected farm.');
            }

            if ($group && ! Farm::farmerOwned($this->user()->id)->where('id', $group->farm_id)->exists()) {
                $validator->errors()->add('animal_group_uuid', 'You do not have access to the selected animal group.');
            }

            if ($group && $farm && (int) $group->farm_id !== (int) $farm->id) {
                $validator->errors()->add('farm_uuid', 'The selected farm does not match the selected animal group.');
            }

            $animalType = $group?->animalType ?? AnimalType::find($this->input('animal_type_id'));
            if ($animalType && ! $animalType->allowsIndividuals()) {
                $validator->errors()->add('animal_type_id', $animalType->name.' is set to group-only tracking. You cannot add individual animal records — manage the herd count instead.');
            }

            if ($this->filled('animal_breed_id')) {
                $breed = AnimalBreed::find($this->input('animal_breed_id'));
                $expectedTypeId = $group?->animal_type_id ?? $this->input('animal_type_id');
                if ($breed && $expectedTypeId && (int) $breed->animal_type_id !== (int) $expectedTypeId) {
                    $validator->errors()->add('animal_breed_id', 'The selected breed does not belong to the selected animal type.');
                }
            }

            if ($this->filled('tag_id')) {
                $duplicateQuery = Animal::query()->where('tag_id', $this->input('tag_id'));

                $animalUuid = $this->route('uuid');
                if ($animalUuid) {
                    $existingAnimal = Animal::where('uuid', $animalUuid)->first();
                    if ($existingAnimal) {
                        $duplicateQuery->where('id', '!=', $existingAnimal->id);
                    }
                }

                if ($group) {
                    $duplicateQuery->where('animal_group_id', $group->id);
                    if ($duplicateQuery->exists()) {
                        $validator->errors()->add('tag_id', 'This tag ID already exists in the selected animal group.');
                    }
                } elseif ($farm) {
                    $duplicateQuery->whereNull('animal_group_id')->where('farm_id', $farm->id);
                    if ($duplicateQuery->exists()) {
                        $validator->errors()->add('tag_id', 'This tag ID already exists among standalone animals on the selected farm.');
                    }
                }
            }
        });
    }
}
