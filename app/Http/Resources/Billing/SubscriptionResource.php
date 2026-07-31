<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'effective_status' => $this->effectiveStatus(),
            'days_remaining' => $this->daysRemaining(),
            'started_at' => $this->started_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'notes' => $this->notes,
            'plan' => $this->whenLoaded('plan', fn () => $this->plan ? new PlanResource($this->plan) : null),
            'farmer' => $this->whenLoaded('farmer', fn () => $this->farmer ? [
                'uuid' => $this->farmer->uuid,
                'display_name' => $this->farmer->display_name,
            ] : null),
            'payments' => SubscriptionPaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
