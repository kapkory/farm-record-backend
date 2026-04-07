<?php

namespace App\Http\Resources\Tasks;

use App\Models\Core\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskCalendarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $due = $this->due_date ? Carbon::parse($this->due_date) : null;

        return [
            'uuid'           => $this->uuid,
            'title'          => $this->title,
            'description'    => $this->description,
            'date'           => $due?->toDateString(),

            'priority'       => Task::PRIORITY_LABELS[$this->priority] ?? 'medium',

            // The model/entity this task belongs to e.g. "Animal", "AnimalGroup", "Planting"
            'category'       => $this->taskable_type ? class_basename($this->taskable_type) : null,

            'status'         => Task::STATUS_LABELS[$this->task_status] ?? 'pending',

            'assignee'       => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id'   => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null),
        ];
    }
}

