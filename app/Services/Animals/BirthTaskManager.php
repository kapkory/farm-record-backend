<?php

namespace App\Services\Animals;

use App\Models\Core\Animal;
use App\Models\Core\AnimalBreeding;
use App\Models\Core\Task;
use Illuminate\Support\Str;

/**
 * Owns the lifecycle of the "expected birth" reminder task for a pregnancy.
 *
 * The task hangs off the *dam* rather than the breeding record, so it shows up
 * in the animal's Tasks tab and on /api/v1/tasks/calendar without any routing
 * changes; the breeding keeps a `birth_task_id` pointer so we can find it again
 * without a morph lookup.
 *
 * Everything here is idempotent — syncFor() can be called on every save of a
 * breeding and will create at most one task, re-dating the existing one when
 * the expected birth date moves.
 */
class BirthTaskManager
{
    public function syncFor(AnimalBreeding $breeding): void
    {
        $task = $breeding->birth_task_id ? Task::find($breeding->birth_task_id) : null;

        // A task the farmer already closed themselves is theirs to keep.
        if ($task && in_array($task->task_status, [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED], true)
            && $breeding->status === 'pending') {
            return;
        }

        match ($breeding->status) {
            'born' => $this->close($task, Task::STATUS_COMPLETED),
            'aborted', 'failed' => $this->close($task, Task::STATUS_CANCELLED),
            default => $this->upsertPending($breeding, $task),
        };
    }

    /** Create the reminder, or move it to the current expected birth date. */
    protected function upsertPending(AnimalBreeding $breeding, ?Task $task): void
    {
        if (! $breeding->expected_birth_date) {
            return;
        }

        $dam = $breeding->relationLoaded('dam') ? $breeding->dam : Animal::find($breeding->dam_id);

        if (! $dam) {
            return;
        }

        if ($task) {
            if (! $task->due_date || ! $task->due_date->isSameDay($breeding->expected_birth_date)) {
                $task->update(['due_date' => $breeding->expected_birth_date]);
            }

            return;
        }

        $task = Task::create([
            'uuid' => (string) Str::orderedUuid(),
            'title' => $this->title($dam),
            'description' => $this->description($breeding),
            'user_id' => $breeding->user_id ?? $dam->user_id,
            'due_date' => $breeding->expected_birth_date,
            'priority' => Task::PRIORITY_HIGH,
            'task_status' => Task::STATUS_PENDING,
            // FQCN, not the morph alias: TasksController::listTasks scopes with
            // `forTaskable($taskable::class, ...)`, so a task stored under the
            // alias would never show up in the animal's Tasks tab.
            'taskable_type' => $dam::class,
            'taskable_id' => $dam->id,
        ]);

        // saveQuietly so writing the pointer back doesn't re-enter the observer.
        $breeding->birth_task_id = $task->id;
        $breeding->saveQuietly();
    }

    protected function close(?Task $task, int $status): void
    {
        if (! $task || in_array($task->task_status, [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED], true)) {
            return;
        }

        $task->update(['task_status' => $status]);
    }

    protected function title(Animal $dam): string
    {
        $label = $dam->name ?: $dam->tag_number ?: 'animal';

        if ($dam->tag_number && $dam->name && $dam->name !== $dam->tag_number) {
            $label .= " ({$dam->tag_number})";
        }

        return "Expected birth — {$label}";
    }

    protected function description(AnimalBreeding $breeding): string
    {
        $sire = $breeding->sire_type === 'ai'
            ? ($breeding->ai_bull_name ? "AI — {$breeding->ai_bull_name}" : 'Artificial insemination')
            : ($breeding->sire?->name ?? ucfirst($breeding->sire_type).' service');

        return sprintf(
            'Prepare for birthing. %s, served on %s.',
            $sire,
            $breeding->service_date?->format('d M Y') ?? 'an unrecorded date'
        );
    }
}
