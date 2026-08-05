<?php

namespace App\Http\Resources\Farms\Farm\Bees;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HiveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $readiness = $this->resource->harvestReadiness();

        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'hive_type' => $this->hive_type,
            'occupancy' => $this->occupancy,
            'has_bees' => $this->occupancy === \App\Models\Core\Hive::OCCUPANCY_OCCUPIED,
            'installed_date' => $this->installed_date?->toDateString(),
            'colonized_at' => $this->colonized_at?->toDateString(),
            'vacated_at' => $this->vacated_at?->toDateString(),
            'last_inspected_at' => $this->last_inspected_at?->toDateString(),
            'last_harvested_at' => $this->last_harvested_at?->toDateString(),
            'next_harvest_due' => $this->next_harvest_due?->toDateString(),
            'harvest_interval_days' => $this->harvest_interval_days,
            'effective_harvest_interval_days' => $this->resource->effectiveHarvestIntervalDays(),
            'harvest_status' => $readiness['status'],
            'days_remaining' => $readiness['days_remaining'],
            'notes' => $this->notes,
            'apiary_uuid' => $this->whenLoaded('apiary', fn () => $this->apiary?->uuid),
            'apiary_name' => $this->whenLoaded('apiary', fn () => $this->apiary?->name),
            'farm_id' => $this->farm_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
