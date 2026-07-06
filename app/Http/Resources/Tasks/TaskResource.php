<?php

namespace App\Http\Resources\Tasks;

use App\Models\Core\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $due = $this->due_date ? Carbon::parse($this->due_date) : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,

            // Status as integer + human label
            'task_status' => $this->task_status,
            'task_status_label' => Task::STATUS_LABELS[$this->task_status] ?? 'pending',

            // Priority as integer + human label
            'priority' => $this->priority,
            'priority_label' => Task::PRIORITY_LABELS[$this->priority] ?? 'medium',

            'due_date' => $due?->toDateString(),
            'due_date_human' => $due?->diffForHumans(),
            'is_overdue' => $due && $due->isPast()
                                    && ! in_array($this->task_status, [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED]),

            'parent_task_id' => $this->parent_task_id,
            'taskable_type' => $this->taskable_type ? class_basename($this->taskable_type) : null,
            'taskable_id' => $this->taskable_id,

            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ] : null),

            'sub_tasks_count' => $this->whenLoaded('subTasks', fn () => $this->subTasks->count(), $this->sub_tasks_count ?? 0),
            'sub_tasks' => TaskResource::collection($this->whenLoaded('subTasks')),

            'created_at' => Carbon::parse($this->created_at)->toDateTimeString(),
            'created_at_human' => Carbon::parse($this->created_at)->diffForHumans(),
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
        ];
    }
}
