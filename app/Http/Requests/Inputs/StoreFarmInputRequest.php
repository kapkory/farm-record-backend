<?php

namespace App\Http\Requests\Inputs;

use App\Models\Core\Farm;
use App\Models\Core\FarmInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFarmInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'farm_uuid' => ['required', 'uuid', 'exists:farms,uuid'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:'.implode(',', FarmInput::CATEGORIES)],
            'treatment_type_id' => ['nullable', 'integer', 'exists:treatment_types,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'unit' => ['required', 'string', 'max:20'],
            'total_cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.gt' => 'Enter how much you bought — it has to be more than zero.',
            'purchase_date.before_or_equal' => 'A purchase cannot be dated in the future.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $farm = Farm::where('uuid', $this->input('farm_uuid'))->first();

            if ($farm && ! Farm::farmerOwned($this->user()->id)->where('id', $farm->id)->exists()) {
                $validator->errors()->add('farm_uuid', 'You do not have access to the selected farm.');
            }
        });
    }
}
