<?php

namespace App\Http\Resources\Farms\Farm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ProductionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'productionable_type' => $this->productionable_type ? Str::snake(class_basename($this->productionable_type)) : null,
            'productionable_id' => $this->productionable_id,
            'productionable_uuid' => $this->whenLoaded('productionable', fn () => $this->productionable?->uuid),
            'name' => $this->name,
            'date' => optional($this->date)->toDateString(),
            'trace_number' => $this->trace_number,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'grade' => $this->grade,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
