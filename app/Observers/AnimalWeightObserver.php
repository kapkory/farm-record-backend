<?php

namespace App\Observers;

use App\Models\Core\AnimalWeight;
use App\Services\Animals\WeighingTaskManager;

/**
 * Every recorded weight closes the task that asked for it and books the next
 * weighing, so the rhythm keeps itself going without a scheduled command.
 */
class AnimalWeightObserver
{
    public function __construct(protected WeighingTaskManager $weighingTasks) {}

    public function created(AnimalWeight $weight): void
    {
        $this->weighingTasks->chainFrom($weight);
    }
}
