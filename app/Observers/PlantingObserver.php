<?php

namespace App\Observers;

use App\Models\Core\CropVariety;
use App\Models\Core\Planting;
use App\Models\Core\Schedule;
use App\Models\Core\Task;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PlantingObserver
{
    /**
     * Handle the Planting "created" event.
     */
    public function created(Planting $planting): void
    {
        $this->setExpectedHarvestDate($planting);
        $this->generateTasksFromSchedule($planting);
    }

    /**
     * Calculate and set expected_harvest_date from the crop variety's maturity_days.
     */
    private function setExpectedHarvestDate(Planting $planting): void
    {
        if (! $planting->crop_variety_id || $planting->expected_harvest_date) {
            return;
        }

        $variety = CropVariety::find($planting->crop_variety_id);

        if (! $variety || ! $variety->maturity_days) {
            return;
        }

        $datePlanted = Carbon::parse($planting->date_planted);
        $planting->expected_harvest_date = $datePlanted->addDays($variety->maturity_days);
        $planting->saveQuietly(); // avoid retriggering observer
    }

    /**
     * Generate tasks from the planting's schedule activities.
     */
    private function generateTasksFromSchedule(Planting $planting): void
    {
        if (! $planting->schedule_id) {
            return;
        }

        $schedule = Schedule::with('activities')->find($planting->schedule_id);

        if (! $schedule || $schedule->activities->isEmpty()) {
            return;
        }

        $datePlanted = Carbon::parse($planting->date_planted);

        foreach ($schedule->activities as $activity) {
            Task::create([
                'uuid'                => (string) Str::orderedUuid(),
                'title'               => $activity->activity_name,
                'description'         => $activity->notes,
                'user_id'             => $planting->user_id,
                'due_date'            => $datePlanted->copy()->addDays($activity->days_since_planting),
                'priority'            => $activity->priority,
                'task_status'         => Task::STATUS_PENDING,
                'taskable_type'       => Planting::class,
                'taskable_id'         => $planting->id,
            ]);
        }
    }
}

