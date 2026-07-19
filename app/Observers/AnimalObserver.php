<?php

namespace App\Observers;

use App\Models\Core\Animal;
use App\Services\Treatment\TreatmentPlanTaskGenerator;
use Carbon\Carbon;

class AnimalObserver
{
    public function __construct(protected TreatmentPlanTaskGenerator $taskGenerator) {}

    /**
     * Turn each step of the animal's treatment plan into a dated Task,
     * counting from its date of birth (or acquisition date for animals
     * whose birth date isn't known).
     */
    public function created(Animal $animal): void
    {
        $startDate = $animal->date_of_birth ?? $animal->acquisition_date;

        $this->taskGenerator->generate(
            $animal,
            $animal->treatment_plan_id,
            $startDate ? Carbon::parse($startDate) : null,
            $animal->user_id
        );
    }
}
