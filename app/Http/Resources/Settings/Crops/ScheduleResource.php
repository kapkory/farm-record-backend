<?php
namespace App\Http\Resources\Settings\Crops;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'uuid'        => $this->uuid,
            'name'        => $this->name,
            'crop_id'     => $this->crop_id,
            'crop'        => $this->whenLoaded('crop', fn () => [
                'id'   => $this->crop->id,
                'name' => $this->crop->name,
            ]),
            'status'      => $this->status ? 'active' : 'inactive',
            'activities'  => ScheduleActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
