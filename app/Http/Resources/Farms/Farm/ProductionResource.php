<?php

namespace App\Http\Resources\Farms\Farm;

use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Hive;
use App\Models\Core\Planting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ProductionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'productionable_type' => $this->productionable_type ? Str::snake(class_basename($this->productionable_type)) : null,
            'productionable_id' => $this->productionable_id,
            'productionable_uuid' => $this->whenLoaded('productionable', fn () => $this->productionable?->uuid),
            'name' => $this->name,
            'source_label' => $this->whenLoaded('productionable', fn () => $this->sourceLabel()),
            'date' => optional($this->date)->toDateString(),
            'trace_number' => $this->trace_number,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'grade' => $this->grade,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /** Human name of where the collection came from ("Hive A3", "Zawadi"). */
    protected function sourceLabel(): ?string
    {
        $source = $this->productionable;

        return match (true) {
            $source instanceof Hive => trim('Hive '.($source->code ?? $source->name ?? '')),
            $source instanceof Animal => $source->name ?? $source->tag_number ?? null,
            $source instanceof AnimalGroup => $source->name,
            $source instanceof Planting => $source->crop?->name,
            default => null,
        };
    }
}
