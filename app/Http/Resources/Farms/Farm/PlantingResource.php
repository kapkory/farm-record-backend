<?php

namespace App\Http\Resources\Farms\Farm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlantingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'farm' => $this->farm ? [
                'uuid' => $this->farm->uuid,
                'name' => $this->farm->name,
            ] : null,
            'field' => $this->field ? [
                'name' => $this->field->name,
                'size' => $this->field->size,
            ] : null,
            'crop' => $this->crop ? [
                'name' => $this->crop->name,
            ] : null,
            'crop_variety' => $this->cropVariety ? [
                'name' => $this->cropVariety->name,
            ] : null,
            'date_planted' => $this->date_planted,
            'expected_harvest_date' => $this->expected_harvest_date,
            'actual_harvest_date' => $this->actual_harvest_date,
            'quantity_planted' => $this->quantity_planted,
            'purpose' => $this->purpose,
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
