<?php

namespace App\Http\Resources\Farms\Farm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'date' => $this->date?->toDateString(),
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
