<?php

namespace App\Http\Resources\Settings\Crops;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        [$offsetValue, $offsetUnit] = $this->daysToOffset($this->days_since_planting);

        return [
            'id'           => $this->id,
            'uuid'         => $this->uuid,
            'title'        => $this->activity_name,
            'offset_value' => $offsetValue,
            'offset_unit'  => $offsetUnit,
            'priority'     => $this->priority,
            'description'  => $this->notes,
        ];
    }

    /**
     * Convert stored days back to the most natural offset unit.
     */
    private function daysToOffset(int $days): array
    {
        if ($days >= 30 && $days % 30 === 0) {
            return [$days / 30, 'months'];
        }

        if ($days >= 7 && $days % 7 === 0) {
            return [$days / 7, 'weeks'];
        }

        return [$days, 'days'];
    }
}

