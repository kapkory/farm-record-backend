<?php

namespace App\Http\Requests\Inputs;

use App\Models\Core\FarmInput;
use App\Models\Core\InputApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInputApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'quantity_used' => ['required', 'numeric', 'gt:0'],
            'allocation_basis' => ['nullable', 'in:'.implode(',', InputApplication::BASES)],
            'details' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'targets' => ['required', 'array', 'min:1', 'max:200'],
            'targets.*.type' => ['required', 'in:animal,animal_group'],
            'targets.*.uuid' => ['required', 'uuid'],
            'targets.*.manual_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'targets.required' => 'Choose at least one animal or group this covered.',
            'quantity_used.gt' => 'Enter how much you used.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $input = FarmInput::where('uuid', (string) $this->route('uuid'))->first();

            // Access and existence are the controller's 404 to give.
            if (! $input) {
                return;
            }

            if ((float) $this->input('quantity_used') > (float) $input->quantity_remaining) {
                $validator->errors()->add('quantity_used', sprintf(
                    'Only %s %s of %s is left.',
                    rtrim(rtrim(number_format((float) $input->quantity_remaining, 3, '.', ''), '0'), '.'),
                    $input->unit,
                    $input->name
                ));
            }

            // A manual split has to actually add up to what was used, or the
            // shares and the ledger drift apart.
            if ($this->input('allocation_basis') === InputApplication::BASIS_MANUAL) {
                $expected = round((float) $this->input('quantity_used') * (float) $input->unit_cost, 2);
                $entered = round(array_sum(array_map(
                    fn ($t) => (float) ($t['manual_cost'] ?? 0),
                    $this->input('targets') ?? []
                )), 2);

                if (abs($entered - $expected) > 0.009) {
                    $validator->errors()->add('targets', sprintf(
                        'The shares add up to %s but this application cost %s.',
                        number_format($entered, 2),
                        number_format($expected, 2)
                    ));
                }
            }
        });
    }
}
