<?php

namespace App\Http\Resources\Settings\Animals;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnimalBreedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'animal_type_id' => $this->animal_type_id,
            'animal_type' => $this->whenLoaded('animalType', fn () => $this->animalType ? [
                'id' => $this->animalType->id,
                'name' => $this->animalType->name,
            ] : null),
            'name' => $this->name,
            'purpose' => $this->purpose,
            'average_lifespan_months' => $this->average_lifespan_months,
            'gestation_days' => $this->gestation_days,
            'description' => $this->description,
            'status' => $this->status,
        ];
    }
}

