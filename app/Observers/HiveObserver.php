<?php

namespace App\Observers;

use App\Models\Core\AnimalGroup;
use App\Models\Core\Hive;

/**
 * Keeps the apiary's (AnimalGroup) current_count equal to its number of
 * occupied hives so the existing group-count UI stays truthful.
 */
class HiveObserver
{
    public function created(Hive $hive): void
    {
        $this->syncApiaryCount($hive);
    }

    public function updated(Hive $hive): void
    {
        if ($hive->wasChanged(['occupancy', 'animal_group_id'])) {
            $this->syncApiaryCount($hive);

            if ($hive->wasChanged('animal_group_id')) {
                $this->syncApiaryCount($hive, (int) $hive->getOriginal('animal_group_id'));
            }
        }
    }

    public function deleted(Hive $hive): void
    {
        $this->syncApiaryCount($hive);
    }

    protected function syncApiaryCount(Hive $hive, ?int $animalGroupId = null): void
    {
        $apiary = $animalGroupId
            ? AnimalGroup::find($animalGroupId)
            : $hive->apiary()->first();

        if (! $apiary) {
            return;
        }

        $apiary->current_count = Hive::query()
            ->where('animal_group_id', $apiary->id)
            ->where('occupancy', Hive::OCCUPANCY_OCCUPIED)
            ->count();
        $apiary->saveQuietly();
    }
}
