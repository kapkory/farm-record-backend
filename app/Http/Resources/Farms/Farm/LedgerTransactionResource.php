<?php

namespace App\Http\Resources\Farms\Farm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class LedgerTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'date' => optional($this->date)->toDateString(),
            'payment_method' => $this->payment_method,
            'description' => $this->description,
            'reference_number' => $this->reference_number,
            'transaction_for' => $this->transactionable_type ? Str::of(class_basename($this->transactionable_type))->lower()->value() : null,
            'transaction_uuid' => $this->whenLoaded('transactionable', fn () => $this->transactionable?->uuid),
            // Which part of the farm a whole-farm expense covers (general|livestock|crops).
            'scope' => $this->scope,
            // A human label for what the cost was posted against, for list views.
            'target_label' => $this->whenLoaded('transactionable', fn () => $this->targetLabel()),
            'ledger_entries' => LedgerEntryResource::collection($this->whenLoaded('entries')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /** A short human label for whatever this cost was posted against. */
    protected function targetLabel(): ?string
    {
        $target = $this->transactionable;

        if (! $target) {
            return null;
        }

        if ($target instanceof \App\Models\Core\Farm) {
            return match ($this->scope) {
                'livestock' => 'All livestock',
                'crops' => 'All crops',
                default => 'Whole farm',
            };
        }

        return $target->name
            ?? $target->group_name
            ?? $target->code
            ?? ($target->crop?->name)
            ?? class_basename($target);
    }
}
