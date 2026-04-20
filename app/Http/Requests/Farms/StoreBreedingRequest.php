<?php

namespace App\Http\Requests\Farms;

use App\Models\Core\Animal;
use App\Models\Core\AnimalBreeding;
use App\Models\Core\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBreedingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'farm_uuid'            => ['nullable', 'uuid', 'exists:farms,uuid'],
            'dam_id'               => ['required', 'uuid', 'exists:animals,uuid'],
            'sire_id'              => ['nullable', 'uuid', 'exists:animals,uuid'],
            'sire_type'            => ['required', 'in:natural,ai,embryo'],
            'service_date'         => ['required', 'date'],
            'expected_birth_date'  => ['nullable', 'date', 'after:service_date'],
            'status'               => ['nullable', 'in:pending,born,aborted,failed'],
            'ai_straw_code'        => ['nullable', 'string', 'max:100'],
            'ai_bull_name'         => ['nullable', 'string', 'max:255'],
            'ai_technician'        => ['nullable', 'string', 'max:255'],
            'notes'                => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // AI breeding must not have a sire_id but should have AI details
            if ($this->input('sire_type') === 'ai' && $this->filled('sire_id')) {
                $validator->errors()->add('sire_id', 'AI breeding should not have a sire animal. Use ai_bull_name instead.');
            }

            // Natural/embryo breeding should have a sire_id
            if (in_array($this->input('sire_type'), ['natural', 'embryo']) && ! $this->filled('sire_id')) {
                $validator->errors()->add('sire_id', 'A sire animal is required for natural or embryo breeding.');
            }

            // Validate dam belongs to a farm the user owns
            $dam = Animal::where('uuid', $this->input('dam_id'))->first();
            if ($dam) {
                if (! Farm::farmerOwned($this->user()->id)->where('id', $dam->farm_id)->exists()) {
                    $validator->errors()->add('dam_id', 'You do not have access to the selected dam.');
                }

                // Validate dam is female
                if ($dam->gender !== null && $dam->gender !== 'female') {
                    $validator->errors()->add('dam_id', 'The dam must be a female animal.');
                }
            }

            // Validate sire belongs to a farm the user owns
            if ($this->filled('sire_id')) {
                $sire = Animal::where('uuid', $this->input('sire_id'))->first();
                if ($sire && ! Farm::farmerOwned($this->user()->id)->where('id', $sire->farm_id)->exists()) {
                    $validator->errors()->add('sire_id', 'You do not have access to the selected sire.');
                }
                if ($sire && $sire->gender !== null && $sire->gender !== 'male') {
                    $validator->errors()->add('sire_id', 'The sire must be a male animal.');
                }
            }
        });
    }
}

