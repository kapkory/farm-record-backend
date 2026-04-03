<?php

namespace App\Http\Resources\Farms\Farm;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnimalGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $acquiredDate = $this->acquired_date ? Carbon::parse($this->acquired_date) : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'animal_type' => $this->whenLoaded('animalType', fn () => $this->animalType?->name, $this->animal_type_name ?? null),
            'animal_breed' => $this->whenLoaded('animalBreed', fn () => $this->animalBreed?->name, $this->animal_breed_name ?? null),
            'current_count' => $this->current_count,
            'initial_count' => $this->initial_count,
            'acquired_date' => $acquiredDate?->toDateString(),
            'acquired_date_human' => $acquiredDate?->diffForHumans(),
            'acquisition_type' => $this->acquisition_type,
            'purpose' => $this->purpose,
            'description' => $this->description,
            'field' => $this->whenLoaded('field', fn () => $this->field?->name, $this->field_name ?? null),
            'status' => $this->status,
            'count_label' => $this->whenLoaded('animalType', fn () => $this->animalType?->count_label, $this->count_label ?? 'animals'),
            'total_animals_tracked' => $this->whenLoaded('animals', fn () => $this->animals->count(), $this->animals_count ?? 0),
            'recent_events_count' => $this->whenLoaded('events', fn () => $this->events->count(), $this->events_count ?? 0),
        ];
    }
}

