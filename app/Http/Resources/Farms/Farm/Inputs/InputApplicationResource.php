<?php

namespace App\Http\Resources\Farms\Farm\Inputs;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InputApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $date = $this->date ? Carbon::parse($this->date) : null;

        return [
            'uuid' => $this->uuid,
            'date' => $date?->toDateString(),
            'date_human' => $date?->format('d M Y'),
            'quantity_used' => (float) $this->quantity_used,
            // Staff see the usage, not the money it represents.
            'total_cost' => $request->user()?->canViewFinances() ? (float) $this->total_cost : null,
            'allocation_basis' => $this->allocation_basis,
            'details' => $this->details,
            'notes' => $this->notes,
            'targets' => $this->whenLoaded('targets', fn () => $this->targets->map(fn ($target) => [
                'uuid' => $target->uuid,
                'type' => $target->targetable_type === \App\Models\Core\AnimalGroup::class ? 'animal_group' : 'animal',
                'target_uuid' => $target->targetable?->uuid,
                'name' => $target->targetable?->name,
                'head_count' => (int) $target->head_count,
                'basis_value' => $target->basis_value !== null ? (float) $target->basis_value : null,
                'allocated_cost' => (float) $target->allocated_cost,
            ])->values()),
            'synced' => true,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
