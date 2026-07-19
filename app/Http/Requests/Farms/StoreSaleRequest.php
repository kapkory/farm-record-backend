<?php

namespace App\Http\Requests\Farms;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'farm_uuid' => ['required', 'uuid'],
            'date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,mobile_money,bank,credit'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'buyer_uuid' => ['nullable', 'uuid'],
            'buyer' => ['nullable', 'array'],
            'buyer.name' => ['required_with:buyer', 'string', 'max:255'],
            'buyer.phone' => ['nullable', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.uuid' => ['nullable', 'uuid'],
            'items.*.category' => ['required', 'in:animal,crop,animal_product,bee_product,other'],
            'items.*.product' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.line_total' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.sellable_type' => ['nullable', 'in:animal,animal_group,planting,hive'],
            'items.*.sellable_uuid' => ['nullable', 'uuid', 'required_with:items.*.sellable_type'],
            'items.*.production_uuid' => ['nullable', 'uuid'],
        ];
    }
}
