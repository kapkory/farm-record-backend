<?php

namespace App\Http\Requests\Farms;

use App\Models\Core\Animal;
use App\Models\Core\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBreedingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dam_id'               => ['sometimes', 'uuid', 'exists:animals,uuid'],
            'sire_id'              => ['sometimes', 'nullable', 'uuid', 'exists:animals,uuid'],
            'sire_type'            => ['sometimes', 'in:natural,ai,embryo'],
            'service_date'         => ['sometimes', 'date'],
            'expected_birth_date'  => ['sometimes', 'nullable', 'date'],
            'status'               => ['sometimes', 'in:pending,born,aborted,failed'],
            'ai_straw_code'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'ai_bull_name'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'ai_technician'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes'                => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Marking a pregnancy `born` has to go through Register Birth so
            // the offspring, birth event and task all get recorded with it.
            if ($this->input('status') === 'born') {
                $validator->errors()->add('status', 'Use Register Birth to record a birth.');
            }

            // Only cross-validate sire_type + sire_id when both are provided
            $sireType = $this->input('sire_type');
            $sireId   = $this->input('sire_id');

            if ($sireType === 'ai' && $this->filled('sire_id')) {
                $validator->errors()->add('sire_id', 'AI breeding should not have a sire animal.');
            }

            if (in_array($sireType, ['natural', 'embryo']) && $this->has('sire_id') && ! $this->filled('sire_id')) {
                $validator->errors()->add('sire_id', 'A sire animal is required for natural or embryo breeding.');
            }

            if ($this->filled('dam_id')) {
                $dam = Animal::where('uuid', $this->input('dam_id'))->first();
                if ($dam && ! Farm::farmerOwned($this->user()->id)->where('id', $dam->farm_id)->exists()) {
                    $validator->errors()->add('dam_id', 'You do not have access to the selected dam.');
                }
                if ($dam && $dam->gender !== null && $dam->gender !== 'female') {
                    $validator->errors()->add('dam_id', 'The dam must be a female animal.');
                }
            }

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

