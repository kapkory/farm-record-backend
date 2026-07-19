<?php

namespace App\Http\Resources\Farms\Farm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'category' => $this->category,
            'product' => $this->product,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price !== null ? (float) $this->unit_price : null,
            'line_total' => (float) $this->line_total,
            'sellable_type' => $this->sellable_type,
            'sellable_uuid' => $this->whenLoaded('sellable', fn () => $this->sellable?->uuid),
        ];
    }
}
