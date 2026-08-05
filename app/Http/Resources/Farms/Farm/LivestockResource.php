<?php

namespace App\Http\Resources\Farms\Farm;

use App\Models\Core\AnimalGroup;
use App\Services\Animals\GestationEstimator;
use App\Services\Animals\WeighingIntervalResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LivestockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof AnimalGroup) {
            return $this->fromGroup();
        }

        return $this->fromAnimal();
    }

    private function fromAnimal(): array
    {
        return [
            'uuid' => $this->uuid,
            'farm_uuid' => $this->whenLoaded('farm', fn () => $this->farm?->uuid),
            'farm' => $this->whenLoaded('farm', fn () => $this->farm ? [
                'uuid' => $this->farm->uuid,
                'name' => $this->farm->name,
            ] : null),
            'animal_type' => $this->whenLoaded('animalType', fn () => $this->animalType ? [
                'id' => $this->animalType->id,
                'name' => $this->animalType->name,
            ] : null),
            'breed' => $this->whenLoaded('animalBreed', fn () => $this->animalBreed ? [
                'id' => $this->animalBreed->id,
                'name' => $this->animalBreed->name,
            ] : null),
            'tracking_type' => 'individual',
            // The edit form needs the current group to pre-select it.
            'animal_group' => $this->whenLoaded('animalGroup', fn () => $this->animalGroup ? [
                'uuid' => $this->animalGroup->uuid,
                'name' => $this->animalGroup->name,
            ] : null),
            'tag_number' => $this->tag_number,
            'name' => $this->name,
            'group_name' => null,
            'count' => 1,
            'gender' => $this->gender,
            // Effective gestation length so the breeding form can pre-fill
            // the expected birth date when a service date is chosen.
            'gestation_days' => app(GestationEstimator::class)->daysFor($this->resource),
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'acquisition_type' => $this->acquisition_type,
            'purchase_price' => $this->purchase_price !== null ? (float) $this->purchase_price : null,
            'purpose' => $this->whenLoaded('animalBreed', fn () => $this->animalBreed?->purpose),
            'status' => $this->status,
            'notes' => $this->notes,
            // Latest live weight (per head; for a group it is a sample average).
            'latest_weight_kg' => $this->whenLoaded('latestWeight', fn () => $this->latestWeight?->weight_kg !== null
                ? (float) $this->latestWeight->weight_kg
                : null),
            'latest_weighed_on' => $this->whenLoaded('latestWeight', fn () => $this->latestWeight?->measured_on?->toDateString()),
            // How often this stock should be weighed, so the Weights tab can
            // show when the next reading is due without another request.
            'weighing_interval_days' => app(WeighingIntervalResolver::class)->daysFor($this->resource),
            'last_checkup' => $this->treatments_max_date,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function fromGroup(): array
    {
        return [
            'uuid' => $this->uuid,
            'farm_uuid' => $this->whenLoaded('farm', fn () => $this->farm?->uuid),
            'farm' => $this->whenLoaded('farm', fn () => $this->farm ? [
                'uuid' => $this->farm->uuid,
                'name' => $this->farm->name,
            ] : null),
            'animal_type' => $this->whenLoaded('animalType', fn () => $this->animalType ? [
                'id' => $this->animalType->id,
                'name' => $this->animalType->name,
            ] : null),
            'breed' => $this->whenLoaded('animalBreed', fn () => $this->animalBreed ? [
                'id' => $this->animalBreed->id,
                'name' => $this->animalBreed->name,
            ] : null),
            'tracking_type' => 'group',
            'tag_number' => null,
            'name' => null,
            'group_name' => $this->name,
            'count' => $this->current_count,
            'gender' => 'mixed',
            'date_of_birth' => null,
            'acquisition_date' => $this->acquired_date?->toDateString(),
            'acquisition_type' => $this->acquisition_type,
            'purpose' => $this->whenLoaded('animalBreed', fn () => $this->animalBreed?->purpose),
            'status' => $this->groupStatus(),
            'notes' => $this->description,
            // Latest live weight (per head; for a group it is a sample average).
            'latest_weight_kg' => $this->whenLoaded('latestWeight', fn () => $this->latestWeight?->weight_kg !== null
                ? (float) $this->latestWeight->weight_kg
                : null),
            'latest_weighed_on' => $this->whenLoaded('latestWeight', fn () => $this->latestWeight?->measured_on?->toDateString()),
            // How often this stock should be weighed, so the Weights tab can
            // show when the next reading is due without another request.
            'weighing_interval_days' => app(WeighingIntervalResolver::class)->daysFor($this->resource),
            'last_checkup' => $this->treatments_max_date,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function groupStatus(): string
    {
        return match ((int) $this->resource->status) {
            0 => 'inactive',
            2 => 'sold_all',
            3 => 'archived',
            default => 'active',
        };
    }
}
