<?php

namespace App\Services\Treatment;

use App\Models\Core\Task;
use App\Models\Core\TreatmentPlan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Turns each step of a treatment plan into a dated Task on any taskable
 * (animal group, individual animal, …), counting age_days from the given
 * start date. Shared by AnimalGroupObserver and AnimalObserver.
 */
class TreatmentPlanTaskGenerator
{
    public function generate(Model $taskable, ?int $treatmentPlanId, ?Carbon $startDate, ?int $userId): void
    {
        if (! $treatmentPlanId || ! $startDate) {
            return;
        }

        $plan = TreatmentPlan::with('activities')->find($treatmentPlanId);

        if (! $plan || $plan->activities->isEmpty()) {
            return;
        }

        foreach ($plan->activities as $activity) {
            $descriptionParts = array_filter([
                $activity->disease ? "Protects against: {$activity->disease}" : null,
                $activity->route ? "Route: {$activity->route}" : null,
                $activity->notes,
            ]);

            // Create through the relationship so taskable_type uses the morph
            // alias, matching how $taskable->tasks() reads them back.
            $taskable->tasks()->create([
                'uuid' => (string) Str::orderedUuid(),
                'title' => $activity->vaccine,
                'description' => $descriptionParts ? implode(' | ', $descriptionParts) : null,
                'user_id' => $userId,
                'due_date' => $startDate->copy()->addDays($activity->age_days),
                'priority' => $activity->priority,
                'task_status' => Task::STATUS_PENDING,
            ]);
        }
    }
}
