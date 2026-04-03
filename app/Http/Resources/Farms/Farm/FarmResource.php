<?php

namespace App\Http\Resources\Farms\Farm;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FarmResource extends JsonResource
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
            'name' => $this->name,
            'location' => $this->location,
            'farm_size' => $this->size.' '.$this->size_unit,
            'type' => ucwords($this->type) .' Farming',
            'total_plantings' => $this->whenLoaded('plantings', fn () => $this->plantings->count(), $this->plantings_count ?? 0),
            'total_fields' => $this->whenLoaded('fields', fn () => $this->fields->count(), $this->fields_count ?? 0),
            'total_livestocks' => $this->whenLoaded('animalGroups',
                fn () => $this->animalGroups->sum('current_count'),
                $this->animal_groups_sum_current_count ?? 0
            ) + $this->whenLoaded('standaloneAnimals',
                fn () => $this->standaloneAnimals->where('status', 'active')->count(),
                $this->standalone_animals_count ?? 0
            ),
            'total_area_planted' => $this->whenLoaded('plantings', function () {
                $total = $this->plantings
                    ->where('expected_harvest_date', '>=', now())
                    ->sum(fn ($planting) => optional($planting->field)->size ?? 0);

                return $total > 0 ? $total.' '.$this->size_unit : null;
            }),
            'next_harvest_date' => $this->whenLoaded('plantings', function () {
                $next = $this->plantings->where('expected_harvest_date', '>=', now())->sortBy('expected_harvest_date')->first();
                    return $next ?  Carbon::parse($next->expected_harvest_date)->diffForHumans() : null;
            }),
        ];
    }
}
