<?php

namespace App\Http\Resources\Farms\Farm;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnimalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateOfBirth = $this->date_of_birth ? Carbon::parse($this->date_of_birth) : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tag_id' => $this->tag_id,
            'name' => $this->name,
            'tag_number' => $this->tag_number,
            'gender' => $this->gender,
            'date_of_birth' => $dateOfBirth?->toDateString(),
            'age_human' => $dateOfBirth?->diffForHumans(),
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'acquisition_type' => $this->acquisition_type,
            'weight' => $this->weight !== null ? (float) $this->weight : null,
            'weight_unit' => $this->weight_unit,
            'status' => $this->status,
            'notes' => $this->notes,
            'is_standalone' => $this->is_standalone,
            'animal_type' => $this->whenLoaded('animalType', fn () => $this->animalType?->name),
            'animal_breed' => $this->whenLoaded('animalBreed', fn () => $this->animalBreed?->name),
            'animal_group' => $this->whenLoaded('animalGroup', fn () => $this->animalGroup ? [
                'uuid' => $this->animalGroup->uuid,
                'name' => $this->animalGroup->name,
            ] : null),
            'farm' => $this->whenLoaded('farm', fn () => $this->farm ? [
                'uuid' => $this->farm->uuid,
                'name' => $this->farm->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
