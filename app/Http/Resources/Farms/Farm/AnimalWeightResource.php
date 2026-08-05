<?php

namespace App\Http\Resources\Farms\Farm;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnimalWeightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $measuredOn = $this->measured_on ? Carbon::parse($this->measured_on) : null;

        return [
            'uuid' => $this->uuid,
            'measured_on' => $measuredOn?->toDateString(),
            'measured_on_human' => $measuredOn?->format('d M Y'),
            // Canonical, per head, kilograms — what every trend is drawn from.
            'weight_kg' => $this->weight_kg !== null ? (float) $this->weight_kg : null,
            // What the farmer typed, so the UI can echo "850 g" not "0.85 kg".
            'entered_value' => $this->entered_value !== null ? (float) $this->entered_value : null,
            'entered_unit' => $this->entered_unit,
            'sample_size' => (int) $this->sample_size,
            'sample_total_kg' => $this->sample_total_kg !== null ? (float) $this->sample_total_kg : null,
            'is_sample' => $this->sample_size > 1,
            'notes' => $this->notes,
            'synced' => true,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
