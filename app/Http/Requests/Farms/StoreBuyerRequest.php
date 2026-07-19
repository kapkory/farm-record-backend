<?php

namespace App\Http\Requests\Farms;

use Illuminate\Foundation\Http\FormRequest;

class StoreBuyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
