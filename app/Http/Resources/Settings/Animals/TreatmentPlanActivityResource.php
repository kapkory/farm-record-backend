<?php

namespace App\Http\Resources\Settings\Animals;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentPlanActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'vaccine' => $this->vaccine,
            'disease' => $this->disease,
            'route' => $this->route,
            'age_days' => $this->age_days,
            'age_label' => $this->ageLabel($this->age_days),
            'priority' => $this->priority,
            'notes' => $this->notes,
        ];
    }

    /**
     * Human-friendly age, e.g. "Day 1", "7 days", "6 weeks".
     */
    private function ageLabel(int $days): string
    {
        if ($days <= 1) {
            return 'Day '.max(1, $days);
        }

        if ($days % 7 === 0) {
            $weeks = $days / 7;

            return $weeks.' '.($weeks === 1 ? 'week' : 'weeks');
        }

        return $days.' days';
    }
}
