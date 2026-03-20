<?php

namespace App\Http\Resources\Farms\Farm\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlantingProfitLossResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'planting' => [
                'id' => $this->id,
                'uuid' => $this->uuid,
                'date_planted' => $this->date_planted,
                'purpose' => $this->purpose,
                'description' => $this->description,
                'crop' => $this->crop ? [
                    'id' => $this->crop->id,
                    'name' => $this->crop->name,
                ] : null,
                'farm' => $this->farm ? [
                    'id' => $this->farm->id,
                    'uuid' => $this->farm->uuid,
                    'name' => $this->farm->name,
                ] : null,
                'field' => $this->field ? [
                    'id' => $this->field->id,
                    'name' => $this->field->name,
                ] : null,
            ],
            'totals' => [
                'revenue' => round((float) ($this->revenue_total ?? 0), 2),
                'expenses' => round((float) ($this->expense_total ?? 0), 2),
                'net_profit' => round((float) (($this->revenue_total ?? 0) - ($this->expense_total ?? 0)), 2),
            ],
        ];
    }
}

