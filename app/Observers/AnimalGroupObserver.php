<?php

namespace App\Observers;

use App\Models\Core\AnimalGroup;
use App\Services\Treatment\TreatmentPlanTaskGenerator;
use Carbon\Carbon;

class AnimalGroupObserver
{
    public function __construct(protected TreatmentPlanTaskGenerator $taskGenerator) {}

    /**
     * Turn each step of the flock's treatment plan into a dated Task, counting
     * from the flock's acquired_date. Mirrors PlantingObserver's task generation.
     */
    public function created(AnimalGroup $group): void
    {
        $this->taskGenerator->generate(
            $group,
            $group->treatment_plan_id,
            $group->acquired_date ? Carbon::parse($group->acquired_date) : null,
            $group->user_id
        );
    }
}
