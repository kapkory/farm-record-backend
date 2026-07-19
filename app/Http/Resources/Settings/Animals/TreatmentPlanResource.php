<?php

namespace App\Http\Resources\Settings\Animals;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'animal_type_id' => $this->animal_type_id,
            'animal_type' => $this->whenLoaded('animalType', fn () => $this->animalType?->name),
            'status' => $this->status ? 'active' : 'inactive',
            'is_system' => (bool) $this->is_system,
            'activities' => TreatmentPlanActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
