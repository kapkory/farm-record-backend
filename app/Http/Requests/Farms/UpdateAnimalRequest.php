<?php

namespace App\Http\Requests\Farms;

use App\Models\Core\Animal;
use App\Models\Core\AnimalBreed;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Editing an animal, as opposed to creating one.
 *
 * Every rule is `sometimes`: the controller applies only the keys that were
 * actually sent, so saving one field can't blank out the rest. StoreAnimalRequest
 * can't be reused here — it requires farm_uuid/animal_type_id, which an edit
 * form touching only the name has no reason to send.
 */
class UpdateAnimalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'animal_group_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:animal_groups,uuid'],
            'farm_uuid' => ['sometimes', 'uuid', 'exists:farms,uuid'],
            'animal_type_id' => ['sometimes', 'integer', 'exists:animal_types,id'],
            'animal_breed_id' => ['sometimes', 'nullable', 'integer', 'exists:animal_breeds,id'],
            'dam_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:animals,uuid'],
            'sire_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:animals,uuid'],
            'treatment_plan_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:treatment_plans,uuid'],
            'tag_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'in:male,female,unknown'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'acquisition_date' => ['sometimes', 'nullable', 'date'],
            'acquisition_type' => ['sometimes', 'in:born,purchased,donated,transferred'],
            'purchase_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999'],
            'gestation_adjustment_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'weighing_interval_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'status' => ['sometimes', 'in:active,sold,deceased,transferred'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $animal = Animal::where('uuid', (string) $this->route('uuid'))->first();

            // A missing or inaccessible animal is the controller's 404 to give.
            if (! $animal || ! Farm::farmerOwned($this->user()->id)->where('id', $animal->farm_id)->exists()) {
                return;
            }

            if ($this->filled('farm_uuid')) {
                $farm = Farm::where('uuid', $this->input('farm_uuid'))->first();
                if ($farm && ! Farm::farmerOwned($this->user()->id)->where('id', $farm->id)->exists()) {
                    $validator->errors()->add('farm_uuid', 'You do not have access to the selected farm.');
                }
            }

            if ($this->filled('animal_group_uuid')) {
                $group = AnimalGroup::where('uuid', $this->input('animal_group_uuid'))->first();
                if ($group && ! Farm::farmerOwned($this->user()->id)->where('id', $group->farm_id)->exists()) {
                    $validator->errors()->add('animal_group_uuid', 'You do not have access to the selected animal group.');
                }
            }

            if ($this->filled('animal_breed_id')) {
                $breed = AnimalBreed::find($this->input('animal_breed_id'));
                $expectedTypeId = $this->input('animal_type_id') ?? $animal->animal_type_id;
                if ($breed && $expectedTypeId && (int) $breed->animal_type_id !== (int) $expectedTypeId) {
                    $validator->errors()->add('animal_breed_id', 'The selected breed does not belong to the selected animal type.');
                }
            }

            if ($this->filled('dam_uuid') && $this->input('dam_uuid') === $animal->uuid) {
                $validator->errors()->add('dam_uuid', 'An animal cannot be its own mother.');
            }

            if ($this->filled('sire_uuid') && $this->input('sire_uuid') === $animal->uuid) {
                $validator->errors()->add('sire_uuid', 'An animal cannot be its own father.');
            }
        });
    }
}
