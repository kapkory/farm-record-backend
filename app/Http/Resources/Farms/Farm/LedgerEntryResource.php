<?php

namespace App\Http\Resources\Farms\Farm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ledger_account_id' => $this->ledger_account_id,
            'amount' => $this->amount !== null ? round((float) $this->amount, 2) : null,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_price !== null ? round((float) $this->unit_price, 2) : null,
            'ledger_account' => new LedgerAccountSummaryResource($this->whenLoaded('account')),
        ];
    }
}
