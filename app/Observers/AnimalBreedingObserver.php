<?php

namespace App\Observers;

use App\Models\Core\AnimalBreeding;
use App\Services\Animals\BirthTaskManager;

/**
 * Keeps each pregnancy's "expected birth" reminder task in step with the
 * breeding record: created with it, re-dated when the expected date moves,
 * and completed or cancelled when the pregnancy resolves.
 */
class AnimalBreedingObserver
{
    public function __construct(protected BirthTaskManager $birthTasks) {}

    public function created(AnimalBreeding $breeding): void
    {
        $this->birthTasks->syncFor($breeding);
    }

    public function updated(AnimalBreeding $breeding): void
    {
        if ($breeding->wasChanged(['expected_birth_date', 'status'])) {
            $this->birthTasks->syncFor($breeding);
        }
    }
}
