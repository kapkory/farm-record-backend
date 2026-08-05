<?php

namespace App\Services\Animals;

use App\Models\Core\AnimalWeight;
use App\Models\Core\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Keeps a weighing rhythm going: every reading closes the task that asked for
 * it and books the next one. Nothing else has to run on a schedule — the chain
 * is self-perpetuating, and it survives being recorded offline because it is
 * driven by the saved reading rather than by a cron sweep.
 *
 * The counterpart for pregnancies is BirthTaskManager.
 */
class WeighingTaskManager
{
    public function __construct(protected WeighingIntervalResolver $intervals) {}

    public function chainFrom(AnimalWeight $weight): void
    {
        $subject = $weight->relationLoaded('weighable') ? $weight->weighable : $weight->weighable()->first();

        if (! $subject) {
            return;
        }

        $this->closePreviousTask($weight);
        $this->scheduleNext($weight, $subject);
    }

    /**
     * Complete the task the previous reading booked. Found through that row's
     * `next_task_id` rather than by matching titles, so a farmer renaming a
     * task doesn't strand it.
     */
    protected function closePreviousTask(AnimalWeight $weight): void
    {
        $previous = AnimalWeight::query()
            ->where('weighable_type', $weight->weighable_type)
            ->where('weighable_id', $weight->weighable_id)
            ->where('id', '!=', $weight->id)
            ->whereNotNull('next_task_id')
            ->orderByDesc('measured_on')
            ->orderByDesc('id')
            ->first();

        if (! $previous) {
            return;
        }

        $task = Task::find($previous->next_task_id);

        // A task the farmer already closed or cancelled is theirs to keep.
        if (! $task || in_array($task->task_status, [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED], true)) {
            return;
        }

        $task->update(['task_status' => Task::STATUS_COMPLETED]);
    }

    protected function scheduleNext(AnimalWeight $weight, Model $subject): void
    {
        $dueDate = $weight->measured_on->copy()->addDays($this->intervals->daysFor($subject));

        $task = Task::create([
            'uuid' => (string) Str::orderedUuid(),
            'title' => 'Weigh '.$this->label($subject),
            'description' => $this->description($weight),
            'user_id' => $weight->user_id,
            'due_date' => $dueDate,
            'priority' => Task::PRIORITY_MEDIUM,
            'task_status' => Task::STATUS_PENDING,
            // FQCN, not the morph alias: TasksController::listTasks scopes with
            // `forTaskable($taskable::class, ...)`, so a task stored under the
            // alias would never surface in the animal's Tasks tab.
            'taskable_type' => $subject::class,
            'taskable_id' => $subject->id,
        ]);

        // saveQuietly so writing the pointer back doesn't re-enter the observer.
        $weight->next_task_id = $task->id;
        $weight->saveQuietly();
    }

    protected function label(Model $subject): string
    {
        $name = $subject->name ?: ($subject->tag_number ?? 'livestock');
        $tag = $subject->tag_number ?? null;

        return $tag && $tag !== $name ? "{$name} ({$tag})" : $name;
    }

    protected function description(AnimalWeight $weight): string
    {
        $last = rtrim(rtrim(number_format($weight->weight_kg, 2, '.', ''), '0'), '.');

        return $weight->is_sample
            ? "Sample the group again. Last reading: {$last} kg per head from {$weight->sample_size} head."
            : "Record the live weight. Last reading: {$last} kg.";
    }
}
