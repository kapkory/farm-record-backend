<?php

namespace App\Http\Resources\Farms\Farm\Inputs;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FarmInputResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $purchaseDate = $this->purchase_date ? Carbon::parse($this->purchase_date) : null;
        $remaining = (float) $this->quantity_remaining;

        // What the farmer actually wants to know: how many more rounds this
        // will cover, based on how much they have been using each time.
        $typicalUse = $this->whenLoaded('applications', function () {
            $used = $this->applications->pluck('quantity_used')->filter(fn ($q) => $q > 0);

            return $used->isEmpty() ? null : round((float) $used->avg(), 3);
        }, null);

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'category' => $this->category,
            'treatment_type_id' => $this->treatment_type_id,
            'treatment_type' => $this->whenLoaded('treatmentType', fn () => $this->treatmentType?->name),
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'quantity_remaining' => $remaining,
            'quantity_used' => $this->quantity_used,
            'is_depleted' => $this->is_depleted,
            'total_cost' => (float) $this->total_cost,
            'unit_cost' => (float) $this->unit_cost,
            'purchase_date' => $purchaseDate?->toDateString(),
            'purchase_date_human' => $purchaseDate?->format('d M Y'),
            'supplier' => $this->supplier,
            'notes' => $this->notes,
            'farm_uuid' => $this->whenLoaded('farm', fn () => $this->farm?->uuid),
            'farm' => $this->whenLoaded('farm', fn () => $this->farm ? [
                'uuid' => $this->farm->uuid,
                'name' => $this->farm->name,
            ] : null),
            'typical_use' => $typicalUse,
            'applications_remaining' => $typicalUse && $typicalUse > 0
                ? (int) floor($remaining / $typicalUse)
                : null,
            'applications_count' => $this->whenLoaded('applications', fn () => $this->applications->count()),
            'applications' => InputApplicationResource::collection($this->whenLoaded('applications')),
            'synced' => true,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
