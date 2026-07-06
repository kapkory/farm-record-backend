<?php

namespace App\Http\Resources\Farms\Farm;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnimalEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $date = $this->date ? Carbon::parse($this->date) : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'event_type' => $this->event_type,
            'date' => $date?->toDateString(),
            'date_human' => $date?->diffForHumans(),
            'quantity' => $this->quantity,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
