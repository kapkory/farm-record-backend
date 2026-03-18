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
            'ledger_entries' => LedgerEntryResource::collection($this->whenLoaded('entries')),
        ];
    }
}

