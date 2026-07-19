<?php

namespace App\Http\Resources\Farms\Farm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'date' => $this->date?->toDateString(),
            'payment_method' => $this->payment_method,
            'amount_total' => (float) $this->amount_total,
            'amount_paid' => (float) $this->amount_paid,
            'balance_due' => $this->balanceDue(),
            'status' => $this->status,
            'notes' => $this->notes,
            'buyer' => $this->whenLoaded('buyer', fn () => $this->buyer ? [
                'uuid' => $this->buyer->uuid,
                'name' => $this->buyer->name,
                'phone' => $this->buyer->phone,
            ] : null),
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'payments' => SalePaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
